<?php

namespace App\Libraries;

use RuntimeException;

/**
 * Couche de chiffrement SERVEUR du coffre-fort.
 *
 * Dans le modèle par enveloppe, le serveur ne manipule aucun élément de clé
 * appartenant aux membres : il ne voit jamais de mot de passe, ni de clé
 * privée, ni de DEK en clair. Son unique rôle cryptographique est d'ajouter
 * une seconde couche de chiffrement au repos, avec une clé dérivée de
 * .vault_key, afin qu'une copie de la seule base de données soit
 * inexploitable — y compris par un destinataire légitime.
 *
 * Cette couche ne participe PAS au contrôle d'accès : celui-ci est assuré
 * par la table entry_key, qui détient les copies de la DEK encapsulées vers
 * les clés publiques des membres autorisés.
 */
class VaultCrypto
{
    public const CIPHER      = 'aes-256-gcm';
    public const NONCE_BYTES = 12;
    public const TAG_BYTES   = 16;
    public const KEY_BYTES   = 32;

    /**
     * Tours de HKDF pour dériver la clé serveur.
     *
     * Ce paramètre n'apporte aucune sécurité, et il est important de ne pas
     * se raconter le contraire : les deux entrées de cette dérivation
     * (.vault_key et l'identifiant d'entrée) sont déjà à haute entropie. Un
     * attaquant n'a rien à deviner ici, donc ralentir l'opération ne
     * ralentit aucune attaque.
     *
     * Le seul élément à faible entropie du système est le mot de passe
     * personnel de chaque membre, et c'est là qu'est placé le coût réel :
     * PBKDF2-SHA256, 600 000 itérations, exécuté dans le navigateur.
     */
    public const SERVER_KDF_ROUNDS = 50;

    private string $vault_key;

    public function __construct(?string $vault_key_hex = null)
    {
        $this->vault_key = $this->load_key($vault_key_hex);
    }

    /**
     * Charge la clé maîtresse serveur depuis .vault_key (256 bits en
     * hexadécimal). Le format est validé strictement : une clé tronquée ou
     * mal copiée doit provoquer une erreur bruyante, jamais un chiffrement
     * silencieusement affaibli.
     */
    private function load_key(?string $vault_key_hex): string
    {
        if ($vault_key_hex === null) {
            $path = ROOTPATH . '.vault_key';

            if (!is_readable($path)) {
                throw new RuntimeException(
                    'Coffre : .vault_key introuvable ou illisible à la racine du projet.'
                );
            }

            $vault_key_hex = (string) file_get_contents($path);
        }

        $vault_key_hex = trim($vault_key_hex);

        if (preg_match('/^[0-9a-fA-F]{64}$/', $vault_key_hex) !== 1) {
            throw new RuntimeException(
                'Coffre : .vault_key doit contenir exactement 64 caractères hexadécimaux (256 bits).'
            );
        }

        return (string) hex2bin($vault_key_hex);
    }

    /**
     * Clé de chiffrement de la couche serveur, propre à chaque entrée.
     *
     * L'identifiant de l'entrée entre dans le paramètre `info` du HKDF :
     * deux entrées n'ont donc jamais la même clé. C'est ce qui contient les
     * dégâts d'une éventuelle collision de nonce, catastrophique en GCM —
     * une collision ne peut se produire qu'à l'intérieur d'une seule entrée,
     * jamais entre deux.
     */
    public function derive_server_key(string $entry_id): string
    {
        $info = 'ck-vault:server:v1|' . $entry_id;
        $key  = $this->vault_key;

        for ($i = 0; $i < self::SERVER_KDF_ROUNDS; $i++) {
            $key = hash_hkdf('sha256', $key, self::KEY_BYTES, $info, '');
        }

        return $key;
    }

    /**
     * Applique la couche serveur.
     *
     * L'identifiant de l'entrée est lié en donnée authentifiée
     * supplémentaire (AAD) : un chiffré déplacé d'une entrée vers une autre
     * échoue à l'authentification au lieu d'être déchiffré et affiché sous
     * un mauvais libellé. GCM garantit qu'un chiffré n'a pas été modifié,
     * pas qu'il se trouve au bon endroit ; l'AAD comble cet écart.
     *
     * @return array{cipher: string, nonce: string, tag: string}
     */
    public function seal(string $plaintext, string $entry_id): array
    {
        $key   = $this->derive_server_key($entry_id);
        $nonce = random_bytes(self::NONCE_BYTES);
        $tag   = '';

        $cipher = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $entry_id,
            self::TAG_BYTES
        );

        if ($cipher === false || $tag === '') {
            throw new RuntimeException('Coffre : échec du chiffrement de la couche serveur.');
        }

        return ['cipher' => $cipher, 'nonce' => $nonce, 'tag' => $tag];
    }

    /**
     * Retire la couche serveur.
     *
     * Renvoie null si l'authentification GCM échoue, ce qui signale soit des
     * données altérées, soit une clé .vault_key qui n'est pas celle ayant
     * servi au chiffrement. Le contenu renvoyé reste chiffré sous la DEK de
     * l'entrée : le serveur ne voit toujours pas le mot de passe en clair.
     */
    public function open(string $cipher, string $nonce, string $tag, string $entry_id): ?string
    {
        if (strlen($nonce) !== self::NONCE_BYTES || strlen($tag) !== self::TAG_BYTES) {
            return null;
        }

        $plaintext = openssl_decrypt(
            $cipher,
            self::CIPHER,
            $this->derive_server_key($entry_id),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $entry_id
        );

        return $plaintext === false ? null : $plaintext;
    }

    /**
     * Identifiant d'entrée : 16 octets tirés du générateur cryptographique,
     * en hexadécimal. Jamais un auto-increment, car cet identifiant est lié
     * en AAD et doit être imprévisible.
     */
    public static function new_entry_id(): string
    {
        return bin2hex(random_bytes(16));
    }

    // -----------------------------------------------------------------
    //  Signature des autorisations
    // -----------------------------------------------------------------

    /**
     * Message canonique signé par le Commandant pour accorder un accès.
     *
     * Il couvre les trois éléments qui définissent l'autorisation : quelle
     * entrée, pour qui, et avec quelle clé encapsulée. Modifier l'un d'eux
     * invalide la signature, ce qui interdit de déplacer une ligne entry_key
     * vers une autre entrée comme de la réattribuer à un autre membre.
     *
     * L'hexadécimal est ramené en minuscules des deux côtés — ici et dans le
     * navigateur — car une simple différence de casse produirait un message
     * différent, donc une signature refusée.
     */
    public static function grant_message(string $entry_id, int $user_id, string $wrapped_dek_hex): string
    {
        return 'ck-vault:grant:v1|'
            . strtolower($entry_id) . '|'
            . $user_id . '|'
            . strtolower($wrapped_dek_hex);
    }

    /**
     * Vérifie une signature RSASSA-PKCS1-v1_5 / SHA-256.
     *
     * Le choix de PKCS#1 v1.5 plutôt que de PSS, pourtant préférable en
     * théorie, est imposé par PHP : openssl_verify() n'expose aucun réglage de
     * bourrage et n'applique que PKCS#1 v1.5. Avec un exposant public de
     * 65537 et l'implémentation d'OpenSSL, la vérification reste sûre ; les
     * attaques connues visent des implémentations qui analysent le bourrage
     * de façon laxiste, ou des exposants très petits.
     */
    public static function verify_signature(string $message, string $signature, string $spki_der): bool
    {
        $public_key = openssl_pkey_get_public(self::der_to_pem($spki_der));

        if ($public_key === false) {
            return false;
        }

        return openssl_verify($message, $signature, $public_key, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * Indique si une clé publique SPKI est exploitable par OpenSSL.
     */
    public static function is_valid_public_key(string $spki_der): bool
    {
        return openssl_pkey_get_public(self::der_to_pem($spki_der)) !== false;
    }

    /**
     * WebCrypto exporte les clés publiques en SPKI binaire (DER) ; OpenSSL les
     * attend en PEM. La conversion est purement un encodage base64 encadré.
     */
    private static function der_to_pem(string $der): string
    {
        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($der), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
    }
}
