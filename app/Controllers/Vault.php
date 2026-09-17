<?php

namespace App\Controllers;

use CodeIgniter\HTTP\Exceptions\RedirectException;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

use App\Libraries\VaultCrypto;
use App\Models\VaultModel;

/**
 * Coffre-fort (Coffre) — gestionnaire de mots de passe à chiffrement par
 * enveloppe.
 *
 * Ce contrôleur ne voit JAMAIS : un mot de passe de coffre personnel, une clé
 * privée en clair, une DEK en clair, ni un mot de passe stocké en clair. Tout
 * le chiffrement utile a lieu dans le navigateur. Le serveur se contente
 * d'appliquer une couche de chiffrement au repos avec .vault_key, de faire
 * respecter la politique d'accès, et de journaliser.
 *
 * Si l'on se surprend un jour à vouloir ajouter ici une fonction qui déchiffre
 * un mot de passe, c'est que le modèle a été perdu de vue.
 */
class Vault extends BaseController
{
    /** Commandant : toujours destinataire d'une nouvelle entrée. */
    public const CPTC_GROUP_ID = 20;

    /** Tailles maximales acceptées, en octets, alignées sur le schéma SQL. */
    private const MAX_PUBLIC_KEY      = 512;
    private const MAX_PRIVATE_KEY_ENC = 2560;
    private const MAX_CIPHER          = 2048;
    private const MAX_WRAPPED_DEK     = 512;
    private const MAX_SIGNATURE       = 512;

    public function __construct()
    {
        if (!session('is_logged_in')) {
            $route = empty(uri_string()) ? '/' : uri_string();
            session()->set('after_login_url', $route);
            throw new RedirectException('login');
            exit;
        }
    }

    // -----------------------------------------------------------------
    //  Garde-fous communs
    // -----------------------------------------------------------------

    private function render_message(string $message): string
    {
        return view('generic/head')
            . view('generic/header')
            . view('404', ['message' => $message])
            . view('generic/footer')
            . view('generic/foot');
    }

    /**
     * Réponse JSON jamais mise en cache.
     *
     * Le jeton CSRF est renvoyé à chaque fois : la configuration le
     * régénère à chaque soumission (Config\Security::$regenerate), le
     * navigateur doit donc repartir du nouveau pour l'appel suivant.
     */
    private function json(array $payload, int $status = 200): ResponseInterface
    {
        $payload['csrf'] = csrf_hash();

        return $this->response
            ->setStatusCode($status)
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->setHeader('Pragma', 'no-cache')
            ->setHeader('Expires', '0')
            ->setJSON($payload);
    }

    /**
     * Contrôles applicables à toutes les actions : état-major, et IP non
     * bloquée. Renvoie null si l'accès est accordé.
     */
    private function deny(VaultModel $vault_model, bool $as_json): ?ResponseInterface
    {
        $user = session('user');
        $ip   = $this->request->getIPAddress();

        if (!is_team_leader($user)) {
            $vault_model->log((int) $user['user_id'], null, 'denied', false, $ip, 'non etat-major');

            return $as_json
                ? $this->json(['ok' => false, 'error' => "Vous n'avez pas la permission d'accéder au coffre."], 403)
                : $this->response->setBody($this->render_message("Vous n'avez pas la permission d'accéder à cette page."));
        }

        if ($vault_model->is_blacklisted($ip)) {
            $vault_model->log((int) $user['user_id'], null, 'blocked', false, $ip, null);

            $message = "Cette adresse IP est bloquée après plusieurs échecs consécutifs. "
                . "Contactez le Commandant pour la débloquer.";

            return $as_json
                ? $this->json(['ok' => false, 'error' => $message], 403)
                : $this->response->setBody($this->render_message($message));
        }

        return null;
    }

    /**
     * Valide une chaîne hexadécimale et la normalise en majuscules.
     *
     * Toutes les données binaires transitent en hexadécimal : le navigateur
     * n'a ainsi jamais à produire d'octets bruts dans du JSON, et la couche
     * SQL n'a jamais à en échapper.
     */
    private function valid_hex($value, int $exact_bytes = 0, int $max_bytes = 0): ?string
    {
        if (!is_string($value) || $value === '' || strlen($value) % 2 !== 0) {
            return null;
        }

        if (preg_match('/^[0-9a-fA-F]+$/', $value) !== 1) {
            return null;
        }

        $bytes = intdiv(strlen($value), 2);

        if ($exact_bytes > 0 && $bytes !== $exact_bytes) {
            return null;
        }

        if ($max_bytes > 0 && $bytes > $max_bytes) {
            return null;
        }

        return strtoupper($value);
    }

    // -----------------------------------------------------------------
    //  Page
    // -----------------------------------------------------------------

    public function index()
    {
        $vault_model = model(VaultModel::class);
        $user        = session('user');

        if (($denied = $this->deny($vault_model, false)) !== null) {
            return $denied->getBody();
        }

        $user_id = (int) $user['user_id'];
        $ip      = $this->request->getIPAddress();

        // Annuaire des destinataires possibles : état-major ET déjà enrôlé.
        // Partager avec quelqu'un suppose de disposer de sa clé publique,
        // donc qu'il se soit enrôlé au préalable.
        $team_leaders = $vault_model->get_team_leaders();
        $public_keys  = $vault_model->get_public_keys(
            array_map(static fn($row) => (int) $row->user_id, $team_leaders)
        );

        $recipients = [];
        foreach ($team_leaders as $row) {
            $id = (int) $row->user_id;
            if (!isset($public_keys[$id])) {
                continue;
            }

            $recipients[] = [
                'user_id'    => $id,
                'username'   => $row->username,
                'public_key' => $public_keys[$id],
                'mandatory'  => ((int) $row->user_group_id === self::CPTC_GROUP_ID) || $id === $user_id,
            ];
        }

        $own_keys = $vault_model->get_own_key_material($user_id);
        $entries  = $own_keys === null ? [] : $vault_model->list_entries($user_id);

        // Seul le Commandant administre les entrées ; lui seul a donc besoin de
        // connaître la répartition des accès.
        $is_cptc        = (int) $user['user_group_id'] === self::CPTC_GROUP_ID;
        $authorizations = ($is_cptc && $own_keys !== null) ? $vault_model->get_all_authorizations() : [];

        $vault_model->log($user_id, null, 'list', true, $ip, null);

        return view('generic/head')
            . view('generic/header')
            . view('vault', [
                'enrolled'        => $own_keys !== null,
                'own_keys'        => $own_keys,
                'entries'         => $entries,
                'recipients'      => $recipients,
                'current_user_id' => $user_id,
                'is_cptc'         => $is_cptc,
                'authorizations'  => $authorizations,
                'kdf_iterations'  => VaultModel::KDF_MIN_ITERATIONS,
            ])
            . view('generic/footer')
            . view('generic/foot');
    }

    // -----------------------------------------------------------------
    //  Enrôlement
    // -----------------------------------------------------------------

    /**
     * Enregistre la paire de clés créée dans le navigateur.
     *
     * Le serveur reçoit une clé publique en clair et un blob opaque : il ne
     * peut pas vérifier que ce blob contient bien la clé privée correspondante
     * ni qu'il est déchiffrable. C'est sans conséquence — un membre qui se
     * saborderait ainsi ne nuirait qu'à lui-même, et s'en apercevrait au
     * premier déchiffrement.
     */
    public function enrol()
    {
        $vault_model = model(VaultModel::class);

        if (($denied = $this->deny($vault_model, true)) !== null) {
            return $denied;
        }

        $user_id = (int) session('user')['user_id'];
        $ip      = $this->request->getIPAddress();
        $body    = $this->request->getJSON(true) ?? [];

        $public_key    = $this->valid_hex($body['public_key'] ?? null, 0, self::MAX_PUBLIC_KEY);
        $private_key   = $this->valid_hex($body['private_key_enc'] ?? null, 0, self::MAX_PRIVATE_KEY_ENC);
        $private_nonce = $this->valid_hex($body['private_key_nonce'] ?? null, VaultCrypto::NONCE_BYTES);
        $sign_public   = $this->valid_hex($body['sign_public_key'] ?? null, 0, self::MAX_PUBLIC_KEY);
        $sign_private  = $this->valid_hex($body['sign_private_key_enc'] ?? null, 0, self::MAX_PRIVATE_KEY_ENC);
        $sign_nonce    = $this->valid_hex($body['sign_private_key_nonce'] ?? null, VaultCrypto::NONCE_BYTES);
        $kdf_salt      = $this->valid_hex($body['kdf_salt'] ?? null, 32);
        $iterations    = (int) ($body['kdf_iterations'] ?? 0);

        if (
            $public_key === null || $private_key === null || $private_nonce === null
            || $sign_public === null || $sign_private === null || $sign_nonce === null
            || $kdf_salt === null
        ) {
            $vault_model->log($user_id, null, 'enrol', false, $ip, 'materiel de cle invalide');

            return $this->json(['ok' => false, 'error' => 'Matériel de clé invalide.'], 400);
        }

        // La clé publique de signature doit être exploitable par OpenSSL dès
        // maintenant : une clé illisible ne se manifesterait autrement qu'au
        // premier partage, bien plus tard et sans rapport apparent.
        if (!VaultCrypto::is_valid_public_key((string) hex2bin($sign_public))) {
            $vault_model->log($user_id, null, 'enrol', false, $ip, 'cle de signature illisible');

            return $this->json(['ok' => false, 'error' => 'Clé de signature invalide.'], 400);
        }

        // Un nombre d'itérations plus faible que le plancher affaiblirait la
        // protection du mot de passe personnel ; c'est la seule valeur choisie
        // par le client qui ait une portée cryptographique, donc la seule à
        // devoir être contrôlée ici.
        if ($iterations < VaultModel::KDF_MIN_ITERATIONS) {
            $vault_model->log($user_id, null, 'enrol', false, $ip, 'iterations insuffisantes');

            return $this->json(['ok' => false, 'error' => 'Paramètres de dérivation insuffisants.'], 400);
        }

        $revoked = $vault_model->enrol(
            $user_id,
            $public_key,
            $private_key,
            $private_nonce,
            $sign_public,
            $sign_private,
            $sign_nonce,
            $kdf_salt,
            $iterations
        );

        $vault_model->log(
            $user_id,
            null,
            'enrol',
            true,
            $ip,
            $revoked > 0 ? "reenrolement, $revoked acces invalides" : null
        );

        return $this->json(['ok' => true, 'revoked' => $revoked]);
    }

    // -----------------------------------------------------------------
    //  Autorisations signées
    // -----------------------------------------------------------------

    /**
     * Le Commandant en fonction, avec sa clé publique de signature.
     *
     * @return array{user_id: int, sign_public_key: string}|null
     */
    private function commandant(VaultModel $vault_model): ?array
    {
        $commandant = $vault_model->get_commandant(self::CPTC_GROUP_ID);

        if ($commandant === null) {
            return null;
        }

        $sign_public_key = $vault_model->get_sign_public_key((int) $commandant->user_id);

        if ($sign_public_key === null) {
            return null;
        }

        return ['user_id' => (int) $commandant->user_id, 'sign_public_key' => $sign_public_key];
    }

    /**
     * Valide un lot d'autorisations et contrôle la signature de chacune.
     *
     * C'est ici que se joue la règle « seul le Commandant décide des accès ».
     * Le contrôle de rôle qui précède ne fait qu'écarter poliment les autres :
     * la garantie réelle tient à la signature, qu'aucun autre membre ne peut
     * produire faute de détenir la clé privée du Commandant.
     *
     * @return array{error: ResponseInterface}|array{grants: array<int, array{wrapped_dek: string, signature: string}>}
     */
    private function collect_grants(
        VaultModel $vault_model,
        $keys,
        string $entry_id,
        int $user_id,
        string $ip,
        string $action
    ): array {
        if (!is_array($keys) || $keys === []) {
            $vault_model->log($user_id, $entry_id, $action, false, $ip, 'aucune autorisation fournie');

            return ['error' => $this->json(['ok' => false, 'error' => 'Requête invalide.'], 400)];
        }

        $commandant = $this->commandant($vault_model);

        if ($commandant === null) {
            $vault_model->log($user_id, $entry_id, $action, false, $ip, 'commandant absent ou non enrole');

            return ['error' => $this->json(
                ['ok' => false, 'error' => "Le Commandant n'est pas enrôlé : impossible de valider les accès."],
                409
            )];
        }

        $sign_public_key = (string) hex2bin($commandant['sign_public_key']);

        // Destinataires éligibles : état-major enrôlé, et personne d'autre.
        $team_leaders = $vault_model->get_team_leaders();
        $eligible     = [];
        foreach ($team_leaders as $row) {
            $eligible[(int) $row->user_id] = true;
        }
        $public_keys = $vault_model->get_public_keys(array_keys($eligible));

        $grants = [];
        foreach ($keys as $item) {
            if (!is_array($item)) {
                continue;
            }

            $recipient_id = (int) ($item['user_id'] ?? 0);
            $wrapped      = $this->valid_hex($item['wrapped_dek'] ?? null, 0, self::MAX_WRAPPED_DEK);
            $signature    = $this->valid_hex($item['signature'] ?? null, 0, self::MAX_SIGNATURE);

            if ($recipient_id <= 0 || $wrapped === null || $signature === null || !isset($public_keys[$recipient_id])) {
                $vault_model->log($user_id, $entry_id, $action, false, $ip, "destinataire invalide : $recipient_id");

                return ['error' => $this->json(['ok' => false, 'error' => 'Destinataire invalide.'], 400)];
            }

            $valid = VaultCrypto::verify_signature(
                VaultCrypto::grant_message($entry_id, $recipient_id, $wrapped),
                (string) hex2bin($signature),
                $sign_public_key
            );

            if (!$valid) {
                $vault_model->log($user_id, $entry_id, $action, false, $ip, "signature invalide pour $recipient_id");

                return ['error' => $this->json(
                    ['ok' => false, 'error' => "Autorisation non signée par le Commandant."],
                    403
                )];
            }

            $grants[$recipient_id] = ['wrapped_dek' => $wrapped, 'signature' => $signature];
        }

        // Le Commandant reste toujours destinataire : sans cela il perdrait
        // l'accès à une entrée qu'il est seul à pouvoir administrer, et plus
        // personne ne pourrait en modifier les accès.
        if (!isset($grants[$commandant['user_id']])) {
            $vault_model->log($user_id, $entry_id, $action, false, $ip, 'commandant absent des destinataires');

            return ['error' => $this->json(
                ['ok' => false, 'error' => 'Le Commandant doit figurer parmi les destinataires.'],
                400
            )];
        }

        return ['grants' => $grants, 'signed_by' => $commandant['user_id']];
    }

    /**
     * Réserve une action au Commandant en fonction.
     */
    private function require_commandant(VaultModel $vault_model, int $user_id, string $ip, string $action): ?ResponseInterface
    {
        if ((int) session('user')['user_group_id'] === self::CPTC_GROUP_ID) {
            return null;
        }

        $vault_model->log($user_id, null, 'denied', false, $ip, "$action reserve au commandant");

        return $this->json(
            ['ok' => false, 'error' => 'Seul le Commandant peut créer une entrée ou en modifier les accès.'],
            403
        );
    }

    // -----------------------------------------------------------------
    //  Création d'une entrée
    // -----------------------------------------------------------------

    public function create()
    {
        $vault_model = model(VaultModel::class);

        if (($denied = $this->deny($vault_model, true)) !== null) {
            return $denied;
        }

        $user_id = (int) session('user')['user_id'];
        $ip      = $this->request->getIPAddress();

        if (($refused = $this->require_commandant($vault_model, $user_id, $ip, 'creation')) !== null) {
            return $refused;
        }

        if (!$vault_model->is_enrolled($user_id)) {
            return $this->json(['ok' => false, 'error' => "Vous devez d'abord créer votre clé de coffre."], 403);
        }

        $body   = $this->request->getJSON(true) ?? [];
        $name   = trim((string) ($body['name'] ?? ''));
        $nonce  = $this->valid_hex($body['nonce'] ?? null, VaultCrypto::NONCE_BYTES);
        $cipher = $this->valid_hex($body['cipher'] ?? null, 0, self::MAX_CIPHER);

        if ($name === '' || mb_strlen($name) > 128 || $nonce === null || $cipher === null) {
            $vault_model->log($user_id, null, 'create', false, $ip, 'requete invalide');

            return $this->json(['ok' => false, 'error' => 'Requête invalide.'], 400);
        }

        // L'identifiant est tiré AVANT la signature des autorisations, car il
        // entre dans le message signé : une autorisation vaut pour une entrée
        // précise et ne peut pas être transposée à une autre.
        $entry_id = $this->valid_hex($body['entry_id'] ?? null, 16);

        if ($entry_id === null) {
            $vault_model->log($user_id, null, 'create', false, $ip, 'identifiant absent ou invalide');

            return $this->json(['ok' => false, 'error' => 'Requête invalide.'], 400);
        }

        $entry_id = strtolower($entry_id);

        if ($vault_model->entry_exists($entry_id)) {
            $vault_model->log($user_id, $entry_id, 'create', false, $ip, 'identifiant deja utilise');

            return $this->json(['ok' => false, 'error' => 'Identifiant déjà utilisé.'], 409);
        }

        $collected = $this->collect_grants($vault_model, $body['keys'] ?? null, $entry_id, $user_id, $ip, 'create');

        if (isset($collected['error'])) {
            return $collected['error'];
        }

        $submitted = $collected['grants'];

        try {
            $sealed = (new VaultCrypto())->seal((string) hex2bin($cipher), $entry_id);
        } catch (Throwable $e) {
            log_message('error', 'Coffre : ' . $e->getMessage());
            $vault_model->log($user_id, null, 'create', false, $ip, 'echec couche serveur');

            return $this->json(['ok' => false, 'error' => 'Erreur de chiffrement côté serveur.'], 500);
        }

        $created = $vault_model->create_entry(
            $entry_id,
            $name,
            $nonce,
            bin2hex($sealed['nonce']),
            bin2hex($sealed['tag']),
            bin2hex($sealed['cipher']),
            $user_id,
            $submitted,
            $collected['signed_by']
        );

        if (!$created) {
            $vault_model->log($user_id, $entry_id, 'create', false, $ip, 'echec transaction');

            return $this->json(['ok' => false, 'error' => "Échec de l'enregistrement."], 500);
        }

        $vault_model->log($user_id, $entry_id, 'create', true, $ip, count($submitted) . ' destinataires');

        return $this->json(['ok' => true, 'entry_id' => $entry_id]);
    }

    // -----------------------------------------------------------------
    //  Déchiffrement
    // -----------------------------------------------------------------

    /**
     * Retire la couche serveur et renvoie le chiffré interne accompagné de la
     * DEK encapsulée pour le demandeur.
     *
     * Aucun élément de clé n'est reçu du navigateur : contrairement au projet
     * initial, un mot de passe personnel erroné ne parvient jamais jusqu'ici,
     * il échoue localement. Cette route n'est donc pas un oracle de devinette,
     * et un refus peut être annoncé honnêtement — c'est une information dont
     * l'utilisateur légitime a besoin.
     */
    public function decrypt()
    {
        $vault_model = model(VaultModel::class);

        if (($denied = $this->deny($vault_model, true)) !== null) {
            return $denied;
        }

        $user_id  = (int) session('user')['user_id'];
        $ip       = $this->request->getIPAddress();
        $body     = $this->request->getJSON(true) ?? [];
        $entry_id = $this->valid_hex($body['entry_id'] ?? null, 16);

        if ($entry_id === null) {
            $vault_model->register_failure($ip);
            $vault_model->log($user_id, null, 'denied', false, $ip, 'identifiant invalide');

            return $this->json(['ok' => false, 'error' => 'Entrée inconnue.'], 400);
        }

        $entry_id = strtolower($entry_id);
        $entry    = $vault_model->get_entry_for_user($entry_id, $user_id);

        if ($entry === null) {
            // Entrée inexistante ou non autorisée : même réponse dans les deux
            // cas, pour ne pas transformer la route en moyen d'énumération.
            $blocked = $vault_model->register_failure($ip);
            $vault_model->log($user_id, $entry_id, 'denied', false, $ip, 'non autorise ou inconnu');

            return $this->json([
                'ok'      => false,
                'blocked' => $blocked,
                'error'   => "Vous n'avez pas accès à cette entrée.",
            ], 403);
        }

        // Contrôle de la signature AU MOMENT DE LA LECTURE.
        //
        // C'est la vérification qui compte vraiment. Celle faite à l'écriture
        // n'engage que le chemin applicatif ; celle-ci vaut quel que soit le
        // chemin par lequel la ligne est arrivée dans la table. Une
        // autorisation insérée directement en base, par injection SQL ou par
        // un accès administrateur, ne porte pas de signature du Commandant :
        // elle n'est donc jamais servie.
        $commandant = $this->commandant($vault_model);

        $signature_valid = $commandant !== null
            && (int) $entry->signed_by === $commandant['user_id']
            && VaultCrypto::verify_signature(
                VaultCrypto::grant_message($entry_id, $user_id, $entry->wrapped_dek),
                (string) hex2bin($entry->signature),
                (string) hex2bin($commandant['sign_public_key'])
            );

        if (!$signature_valid) {
            $vault_model->log($user_id, $entry_id, 'denied', false, $ip, 'signature d autorisation invalide');

            return $this->json([
                'ok'    => false,
                'error' => "Autorisation non validée par le Commandant en fonction. "
                    . "Si le Commandant a changé, les accès doivent être réattribués.",
            ], 403);
        }

        try {
            $inner = (new VaultCrypto())->open(
                (string) hex2bin($entry->cipher),
                (string) hex2bin($entry->server_nonce),
                (string) hex2bin($entry->server_tag),
                $entry_id
            );
        } catch (Throwable $e) {
            log_message('error', 'Coffre : ' . $e->getMessage());
            $inner = null;
        }

        if ($inner === null) {
            // L'authentification GCM de la couche serveur a échoué : données
            // altérées, ou .vault_key différente de celle du chiffrement.
            // Jamais un mot de passe erroné, qui ne passe pas par ici.
            $vault_model->log($user_id, $entry_id, 'decrypt', false, $ip, 'echec authentification couche serveur');

            return $this->json([
                'ok'    => false,
                'error' => "Données altérées ou clé serveur incorrecte. Prévenez le Commandant.",
            ], 500);
        }

        // Un déchiffrement autorisé et abouti atteste d'un usage légitime : le
        // compteur d'échecs consécutifs repart de zéro. Sans cette remise à
        // zéro côté serveur, des refus isolés et sans rapport entre eux
        // s'accumuleraient jusqu'au blocage d'un utilisateur parfaitement
        // normal — « consécutifs » n'aurait alors plus aucun sens.
        $vault_model->reset_failures($ip);
        $vault_model->log($user_id, $entry_id, 'decrypt', true, $ip, null);

        return $this->json([
            'ok'          => true,
            'name'        => $entry->name,
            'cipher'      => bin2hex($inner),
            'nonce'       => $entry->nonce,
            'wrapped_dek' => $entry->wrapped_dek,
        ]);
    }

    /**
     * Modification d'une entrée : nouveau mot de passe et/ou nouvelle liste
     * d'accès. Réservé au Commandant.
     *
     * Le navigateur renvoie une entrée entièrement re-chiffrée sous une DEK
     * NEUVE, accompagnée d'une encapsulation par destinataire retenu. Le
     * serveur ne fait que remplacer l'existant : il ne peut ni vérifier le
     * contenu, ni fabriquer lui-même ces éléments.
     *
     * Le renouvellement systématique de la DEK est ce qui donne un effet réel
     * au retrait d'un accès : l'ancienne encapsulation du membre écarté, même
     * conservée, n'ouvre plus rien.
     */
    public function update()
    {
        $vault_model = model(VaultModel::class);

        if (($denied = $this->deny($vault_model, true)) !== null) {
            return $denied;
        }

        $user    = session('user');
        $user_id = (int) $user['user_id'];
        $ip      = $this->request->getIPAddress();

        if (($refused = $this->require_commandant($vault_model, $user_id, $ip, 'modification')) !== null) {
            return $refused;
        }

        $body     = $this->request->getJSON(true) ?? [];
        $entry_id = $this->valid_hex($body['entry_id'] ?? null, 16);
        $nonce    = $this->valid_hex($body['nonce'] ?? null, VaultCrypto::NONCE_BYTES);
        $cipher   = $this->valid_hex($body['cipher'] ?? null, 0, self::MAX_CIPHER);

        if ($entry_id === null || $nonce === null || $cipher === null) {
            $vault_model->log($user_id, null, 'share', false, $ip, 'requete invalide');

            return $this->json(['ok' => false, 'error' => 'Requête invalide.'], 400);
        }

        $entry_id = strtolower($entry_id);

        if (!$vault_model->entry_exists($entry_id)) {
            return $this->json(['ok' => false, 'error' => 'Entrée inconnue.'], 404);
        }

        $collected = $this->collect_grants($vault_model, $body['keys'] ?? null, $entry_id, $user_id, $ip, 'share');

        if (isset($collected['error'])) {
            return $collected['error'];
        }

        $submitted = $collected['grants'];

        try {
            $sealed = (new VaultCrypto())->seal((string) hex2bin($cipher), $entry_id);
        } catch (Throwable $e) {
            log_message('error', 'Coffre : ' . $e->getMessage());
            $vault_model->log($user_id, $entry_id, 'share', false, $ip, 'echec couche serveur');

            return $this->json(['ok' => false, 'error' => 'Erreur de chiffrement côté serveur.'], 500);
        }

        $previous = $vault_model->get_authorized_user_ids($entry_id);
        $updated  = $vault_model->update_entry(
            $entry_id,
            $nonce,
            bin2hex($sealed['nonce']),
            bin2hex($sealed['tag']),
            bin2hex($sealed['cipher']),
            $submitted,
            $collected['signed_by']
        );

        if (!$updated) {
            $vault_model->log($user_id, $entry_id, 'share', false, $ip, 'echec transaction');

            return $this->json(['ok' => false, 'error' => "Échec de l'enregistrement."], 500);
        }

        $removed = array_diff($previous, array_keys($submitted));
        $added   = array_diff(array_keys($submitted), $previous);

        $vault_model->log(
            $user_id,
            $entry_id,
            'share',
            true,
            $ip,
            sprintf(
                '%d destinataires (ajout : %s ; retrait : %s)',
                count($submitted),
                $added === [] ? 'aucun' : implode(',', $added),
                $removed === [] ? 'aucun' : implode(',', $removed)
            )
        );

        foreach ($removed as $removed_id) {
            $vault_model->log($user_id, $entry_id, 'revoke', true, $ip, "acces retire a $removed_id");
        }

        return $this->json(['ok' => true]);
    }

    /**
     * Signalement, par le navigateur, d'un déchiffrement local ayant échoué —
     * mot de passe personnel erroné, dans l'immense majorité des cas.
     *
     * Déclaratif par nature : un attaquant s'abstiendrait de le signaler. Ce
     * n'est pas gênant, car il n'aurait rien à y gagner — le serveur ne
     * détient aucun élément permettant d'ouvrir quoi que ce soit. Cela sert la
     * traçabilité et le verrouillage après saisies erronées répétées.
     */
    public function report_failure()
    {
        $vault_model = model(VaultModel::class);

        if (($denied = $this->deny($vault_model, true)) !== null) {
            return $denied;
        }

        $user_id  = (int) session('user')['user_id'];
        $ip       = $this->request->getIPAddress();
        $body     = $this->request->getJSON(true) ?? [];
        $entry_id = $this->valid_hex($body['entry_id'] ?? null, 16);

        $blocked = $vault_model->register_failure($ip);
        $vault_model->log(
            $user_id,
            $entry_id === null ? null : strtolower($entry_id),
            'decrypt_failed',
            false,
            $ip,
            'echec dechiffrement local'
        );

        return $this->json(['ok' => true, 'blocked' => $blocked]);
    }

    /**
     * Remet à zéro le compteur d'échecs après un déchiffrement local réussi.
     */
    public function report_success()
    {
        $vault_model = model(VaultModel::class);

        if (($denied = $this->deny($vault_model, true)) !== null) {
            return $denied;
        }

        $vault_model->reset_failures($this->request->getIPAddress());

        return $this->json(['ok' => true]);
    }
}
