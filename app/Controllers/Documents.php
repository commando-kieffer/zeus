<?php

namespace App\Controllers;

use CodeIgniter\HTTP\Exceptions\RedirectException;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

use App\Libraries\DocumentStorage;
use App\Models\DocumentModel;

/**
 * Bibliothèque de documents : livrets, guides, archives... que tout membre
 * connecté peut consulter et que seul l'état-major peut ajouter, modifier et
 * supprimer.
 *
 * Les fichiers sont stockés hors de la racine web (voir DocumentStorage) et
 * servis par ce contrôleur, après contrôle de la connexion : un lien de
 * document ne fonctionne donc pas pour quelqu'un qui n'est pas connecté.
 */
class Documents extends BaseController
{
    /** Tailles maximales voulues par l'application, en octets. PHP peut imposer moins. */
    private const DOCUMENT_MAX_BYTES = 50 * 1024 * 1024;
    private const PREVIEW_MAX_BYTES  = 5 * 1024 * 1024;

    /** Marge réservée, dans post_max_size, aux champs du formulaire autres que les deux fichiers. */
    private const POST_OVERHEAD_BYTES = 64 * 1024;

    private const TITLE_MAX_LENGTH = 128;

    /** Formats d'image acceptés pour l'aperçu. */
    private const PREVIEW_MIME_TYPES = ['image/png', 'image/jpeg', 'image/webp'];

    protected $helpers = ['role', 'job_points', 'date', 'document'];

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
    //  Consultation (tous les membres)
    // -----------------------------------------------------------------

    public function index()
    {
        return $this->render('documents', [
            'documents'  => model(DocumentModel::class)->get_all_documents(),
            'can_manage' => can_manage_documents(session('user')),
            'success'    => session()->getFlashdata('success'),
        ]);
    }

    /**
     * Envoie le document. Les types sûrs (PDF, images) s'affichent dans
     * l'onglet ouvert par le lien ; tous les autres sont téléchargés.
     */
    public function file(int $id)
    {
        $document = model(DocumentModel::class)->get_document($id);
        $path     = $document === null ? null : (new DocumentStorage())->path($document->file_path);

        if ($path === null) {
            return $this->refuse(404, "Ce document est introuvable.");
        }

        $this->detach_from_session();

        $inline = document_is_inline_mime($document->file_mime);

        // Les fichiers peuvent peser plusieurs dizaines de Mo : DownloadResponse
        // les envoie par blocs d'1 Mo au lieu de les charger en mémoire.
        $response = $this->response->download($path, null);

        // Un type non listé est servi en octet-stream et forcé en téléchargement :
        // jamais interprété par le navigateur, même si le fichier se prétend HTML.
        $response->setContentType($inline ? $document->file_mime : 'application/octet-stream', '');
        $response->setHeader('Content-Disposition', document_content_disposition($inline ? 'inline' : 'attachment', $document->file_name));
        $response->setHeader('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    /**
     * Envoie l'image d'aperçu. L'URL porte la date de dernière modification
     * (?v=) : l'image peut donc être gardée longtemps par le navigateur, un
     * remplacement changeant l'URL.
     */
    public function preview(int $id)
    {
        $document = model(DocumentModel::class)->get_document($id);
        $path     = $document === null ? null : (new DocumentStorage())->path($document->preview_path);

        if ($path === null) {
            return $this->refuse(404, "Cet aperçu est introuvable.");
        }

        $this->detach_from_session();

        $mime = in_array($document->preview_mime, self::PREVIEW_MIME_TYPES, true)
            ? $document->preview_mime
            : 'application/octet-stream';

        return $this->response
            ->setContentType($mime, '')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Cache-Control', 'private, max-age=86400')
            ->setBody((string) file_get_contents($path));
    }

    // -----------------------------------------------------------------
    //  Gestion (état-major)
    // -----------------------------------------------------------------

    public function create()
    {
        if (($denied = $this->require_manager()) !== null) {
            return $denied;
        }

        return $this->render('document_form', $this->form_data(null));
    }

    public function edit(int $id)
    {
        if (($denied = $this->require_manager()) !== null) {
            return $denied;
        }

        $document = model(DocumentModel::class)->get_document($id);

        if ($document === null) {
            return $this->refuse(404, "Ce document est introuvable.");
        }

        return $this->render('document_form', $this->form_data($document));
    }

    public function store()
    {
        if (($denied = $this->require_manager()) !== null) {
            return $denied;
        }

        $errors = $this->validate_submission(true);

        if ($errors !== []) {
            return $this->back_with_errors($errors, '/documents/ajouter');
        }

        $title   = $this->posted_title();
        $storage = new DocumentStorage();
        $stored  = [];

        try {
            $file    = $this->save_upload($this->request->getFile('document'), $storage, $stored);
            $preview = $this->save_upload($this->request->getFile('preview'), $storage, $stored);

            model(DocumentModel::class)->create_document($title, $file, $preview);
        } catch (Throwable $e) {
            return $this->fail_save($e, $storage, $stored, '/documents/ajouter');
        }

        session()->setFlashdata('success', sprintf('Le document « %s » a été ajouté.', $title));

        return $this->redirect_to('/documents');
    }

    public function update(int $id)
    {
        if (($denied = $this->require_manager()) !== null) {
            return $denied;
        }

        $model    = model(DocumentModel::class);
        $document = $model->get_document($id);

        if ($document === null) {
            return $this->refuse(404, "Ce document est introuvable.");
        }

        $edit_url = '/documents/' . $document->id . '/modifier';
        $errors   = $this->validate_submission(false);

        if ($errors !== []) {
            return $this->back_with_errors($errors, $edit_url);
        }

        $title   = $this->posted_title();
        $storage = new DocumentStorage();
        $stored  = [];

        try {
            // Un fichier laissé vide n'est pas remplacé : on conserve l'actuel.
            $file    = $this->has_upload('document') ? $this->save_upload($this->request->getFile('document'), $storage, $stored) : null;
            $preview = $this->has_upload('preview') ? $this->save_upload($this->request->getFile('preview'), $storage, $stored) : null;

            $model->update_document($id, $title, $file, $preview);
        } catch (Throwable $e) {
            return $this->fail_save($e, $storage, $stored, $edit_url);
        }

        // Les anciens fichiers ne sont supprimés qu'une fois la base à jour :
        // un échec plus tôt laisse le document intact.
        if ($file !== null) {
            $storage->delete($document->file_path);
        }

        if ($preview !== null) {
            $storage->delete($document->preview_path);
        }

        session()->setFlashdata('success', sprintf('Le document « %s » a été modifié.', $title));

        return $this->redirect_to('/documents');
    }

    public function delete(int $id)
    {
        if (($denied = $this->require_manager()) !== null) {
            return $denied;
        }

        $model    = model(DocumentModel::class);
        $document = $model->get_document($id);

        if ($document === null) {
            return $this->refuse(404, "Ce document est introuvable.");
        }

        // La ligne d'abord : un fichier orphelin sur le disque est sans
        // conséquence, une ligne qui pointe vers un fichier disparu est un lien mort.
        $model->delete_document($id);

        $storage = new DocumentStorage();
        $storage->delete($document->file_path);
        $storage->delete($document->preview_path);

        session()->setFlashdata('success', sprintf('Le document « %s » a été supprimé.', $document->title));

        return $this->redirect_to('/documents');
    }

    // -----------------------------------------------------------------
    //  Rendu
    // -----------------------------------------------------------------

    private function render(string $view, array $data = []): string
    {
        return view('generic/head')
            . view('generic/header')
            . view($view, $data)
            . view('generic/footer')
            . view('generic/foot');
    }

    /**
     * Réserve une action à l'état-major : renvoie la réponse de refus (403),
     * ou null si l'utilisateur a le droit de gérer les documents.
     */
    private function require_manager(): ?ResponseInterface
    {
        return can_manage_documents(session('user'))
            ? null
            : $this->refuse(403, "Vous n'avez pas la permission d'accéder à cette page.");
    }

    /**
     * Page de message avec le code HTTP qui convient (403, 404...).
     */
    private function refuse(int $status, string $message): ResponseInterface
    {
        return $this->response
            ->setStatusCode($status)
            ->setBody($this->render('404', ['message' => $message]));
    }

    /**
     * Redirection vers une URL propre (/documents) plutôt que vers
     * /index.php/documents, que produirait redirect()->to() avec la
     * configuration actuelle (App::$indexPage).
     */
    private function redirect_to(string $path): ResponseInterface
    {
        return redirect()->to(base_url($path));
    }

    private function form_data(?object $document): array
    {
        return [
            'document'           => $document,
            'errors'             => session()->getFlashdata('errors') ?? [],
            'old'                => session()->getFlashdata('old') ?? [],
            'max_document_bytes' => $this->document_limit(),
            'max_preview_bytes'  => $this->preview_limit(),
            'title_max_length'   => self::TITLE_MAX_LENGTH,
        ];
    }

    /**
     * Ré-affiche le formulaire avec les erreurs et le titre saisi. Les
     * fichiers, eux, ne peuvent pas être pré-remplis : le membre les resélectionne.
     */
    private function back_with_errors(array $errors, string $url): ResponseInterface
    {
        session()->setFlashdata('errors', $errors);
        session()->setFlashdata('old', ['title' => $this->posted_title()]);

        return $this->redirect_to($url);
    }

    /**
     * Échec d'enregistrement : défait ce qui a été écrit sur le disque et
     * renvoie au formulaire. Le détail technique va dans le journal, pas à l'écran.
     *
     * @param string[] $stored noms des fichiers déjà stockés par cette requête
     */
    private function fail_save(Throwable $e, DocumentStorage $storage, array $stored, string $url): ResponseInterface
    {
        foreach ($stored as $name) {
            $storage->delete($name);
        }

        log_message('error', 'Documents : ' . $e->getMessage());

        return $this->back_with_errors(["L'enregistrement du document a échoué. Veuillez réessayer."], $url);
    }

    /**
     * À appeler par les actions qui envoient un fichier, une fois les droits
     * vérifiés (et seulement si elles n'écrivent rien en session) :
     *
     *  - libère le verrou de la session, que PHP garde sinon jusqu'à la fin de
     *    la requête : les vignettes d'une page et un gros téléchargement en
     *    cours se feraient attendre les uns les autres ;
     *  - retire les en-têtes « ne pas mettre en cache » que PHP prépare pour
     *    toute requête à session. Envoyés en plus des nôtres, ils les annulent
     *    (deux Cache-Control contradictoires), alors que les aperçus doivent
     *    pouvoir être gardés par le navigateur.
     */
    private function detach_from_session(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        header_remove('Cache-Control');
        header_remove('Expires');
        header_remove('Pragma');
    }

    // -----------------------------------------------------------------
    //  Fichiers envoyés
    // -----------------------------------------------------------------

    /** Limite effective d'un document : la plus stricte de celle de l'application et de PHP. */
    private function document_limit(): int
    {
        return document_effective_upload_limit(
            self::DOCUMENT_MAX_BYTES,
            document_parse_ini_size((string) ini_get('upload_max_filesize')),
            document_parse_ini_size((string) ini_get('post_max_size')),
            self::PREVIEW_MAX_BYTES + self::POST_OVERHEAD_BYTES
        );
    }

    private function preview_limit(): int
    {
        return document_effective_upload_limit(
            self::PREVIEW_MAX_BYTES,
            document_parse_ini_size((string) ini_get('upload_max_filesize')),
            document_parse_ini_size((string) ini_get('post_max_size'))
        );
    }

    /**
     * Titre saisi, débarrassé des blancs de bord. Les octets UTF-8 invalides
     * sont remplacés : un navigateur n'en envoie jamais, mais MySQL, configuré
     * ici sans mode strict, couperait silencieusement le titre au premier.
     */
    private function posted_title(): string
    {
        return trim(mb_scrub((string) $this->request->getPost('title')));
    }

    /** Un fichier a-t-il été choisi dans ce champ du formulaire ? */
    private function has_upload(string $field): bool
    {
        $file = $this->request->getFile($field);

        return $file !== null && $file->getError() !== UPLOAD_ERR_NO_FILE;
    }

    /**
     * Valide le formulaire d'ajout ou de modification.
     *
     * @param bool $files_required vrai à l'ajout (les deux fichiers sont
     *                             obligatoires), faux à la modification (un
     *                             champ vide conserve le fichier actuel)
     *
     * @return array<string, string> messages d'erreur par champ, vide si tout est valide
     */
    private function validate_submission(bool $files_required): array
    {
        $rules = [
            'title' => [
                'label' => 'Titre',
                'rules' => 'required|max_length[' . self::TITLE_MAX_LENGTH . ']',
            ],
        ];
        $errors = [];

        $uploads = [
            'document' => [
                'label' => 'Document',
                'limit' => $this->document_limit(),
                'rules' => 'uploaded[document]|max_size[document,%d]',
                'errors' => [
                    'uploaded' => "Le fichier du document n'a pas pu être reçu.",
                    'max_size' => 'Le document dépasse la taille maximale autorisée (%s).',
                ],
            ],
            'preview' => [
                'label' => 'Aperçu',
                'limit' => $this->preview_limit(),
                'rules' => 'uploaded[preview]|is_image[preview]|mime_in[preview,' . implode(',', self::PREVIEW_MIME_TYPES) . ']|max_size[preview,%d]',
                'errors' => [
                    'uploaded' => "L'image d'aperçu n'a pas pu être reçue.",
                    'is_image' => "L'aperçu doit être une image PNG, JPEG ou WebP.",
                    'mime_in'  => "L'aperçu doit être une image PNG, JPEG ou WebP.",
                    'max_size' => "L'image d'aperçu dépasse la taille maximale autorisée (%s).",
                ],
            ],
        ];

        foreach ($uploads as $field => $upload) {
            $file  = $this->request->getFile($field);
            $error = $file?->getError() ?? UPLOAD_ERR_NO_FILE;

            if ($error === UPLOAD_ERR_NO_FILE) {
                if ($files_required) {
                    $errors[$field] = $field === 'document'
                        ? 'Veuillez choisir le document à ajouter.'
                        : "Veuillez choisir une image d'aperçu.";
                }
                continue;
            }

            if ($error !== UPLOAD_ERR_OK) {
                $errors[$field] = $this->upload_error_message($upload['label'], $error, $upload['limit']);
                continue;
            }

            // La taille est exprimée en Ko par la règle max_size ; le message
            // d'erreur l'annonce en unités lisibles.
            $size_message = document_format_size($upload['limit']);
            $rules[$field] = [
                'label'  => $upload['label'],
                'rules'  => sprintf($upload['rules'], intdiv($upload['limit'], 1024)),
                'errors' => array_map(static fn(string $message) => str_replace('%s', $size_message, $message), $upload['errors']),
            ];
        }

        if (!$this->validate($rules)) {
            $errors = array_merge($this->validator->getErrors(), $errors);
        }

        return $errors;
    }

    /**
     * Message pour un fichier que PHP lui-même a refusé (avant toute règle de
     * validation) : le plus souvent un fichier plus gros que la limite du serveur.
     */
    private function upload_error_message(string $label, int $error, int $limit): string
    {
        switch ($error) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return sprintf('Le fichier du champ %s dépasse la taille maximale autorisée (%s).', $label, document_format_size($limit));
            case UPLOAD_ERR_PARTIAL:
                return sprintf("Le fichier du champ %s n'a été reçu que partiellement. Veuillez réessayer.", $label);
            default:
                // Dossier temporaire absent, disque plein... : un problème du
                // serveur, à retrouver dans les journaux.
                log_message('error', 'Documents : erreur d\'envoi PHP ' . $error . ' (' . $label . ')');

                return sprintf("Le fichier du champ %s n'a pas pu être reçu. Veuillez réessayer.", $label);
        }
    }

    /**
     * Enregistre un fichier envoyé et décrit ce qui doit être conservé en base.
     *
     * Le type et la taille sont lus AVANT le déplacement (ensuite le fichier
     * n'est plus à son emplacement d'origine). Le type est celui détecté par le
     * serveur à partir du contenu, non celui que déclare le navigateur.
     *
     * @param string[] $stored reçoit le nom stocké, pour pouvoir le défaire en cas d'échec ultérieur
     *
     * @return array{name: string, path: string, mime: string, size: int}
     */
    private function save_upload(UploadedFile $upload, DocumentStorage $storage, array &$stored): array
    {
        $description = [
            'name' => document_clean_file_name($upload->getClientName()),
            'mime' => $upload->getMimeType(),
            'size' => (int) $upload->getSize(),
        ];

        $stored[] = $description['path'] = $storage->store($upload);

        return $description;
    }
}
