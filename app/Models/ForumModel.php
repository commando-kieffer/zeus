<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Intégration avec l'API REST du forum XenForo pour la publication des
 * rapports d'opération. Voir ProfileModel::get_user_profile() pour un autre
 * exemple d'appel à cette même API.
 */
class ForumModel extends Model
{
    public function __construct()
    {
        parent::__construct();
        $this->table = "-";
    }

    /**
     * Crée un message dans le sujet donné. Retourne l'ID XenForo du message
     * créé, ou null en cas d'échec (réseau, sujet invalide, etc.).
     */
    public function create_post(int $thread_id, string $message, int $current_user_id): ?int
    {
        $data = $this->call_api('/api/posts', $current_user_id, [
            'thread_id' => $thread_id,
            'message' => $message,
        ]);

        if (empty($data['success']) || empty($data['post']['post_id'])) {
            return null;
        }

        return (int) $data['post']['post_id'];
    }

    /**
     * Met à jour un message existant. Retourne true en cas de succès.
     */
    public function update_post(int $post_id, string $message, int $current_user_id): bool
    {
        $data = $this->call_api("/api/posts/$post_id/", $current_user_id, [
            'message' => $message,
            'silent' => '0',
            'clear_edit' => '0',
        ]);

        return !empty($data['success']);
    }

    /**
     * Appelle l'API du forum avec l'utilisateur courant (XF-Api-User), pour
     * que le message posté lui soit bien attribué. Retourne le corps JSON
     * décodé, ou null si l'appel a échoué (erreur réseau ou réponse
     * "errors" de XenForo).
     */
    private function call_api(string $path, int $current_user_id, array $body): ?array
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => env("FORUM_BASE_URI") . "/index.php" . $path,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_TIMEOUT => 0,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => http_build_query($body),
            CURLOPT_HTTPHEADER => array(
                'XF-Api-User: ' . $current_user_id,
                'XF-Api-Key: ' . env("XEN_API_KEY"),
            ),
        ));

        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            log_message('error', "Erreur cURL vers l'API du forum ($path) : $error");
            return null;
        }

        $data = json_decode($response, true);

        if (!empty($data['errors'])) {
            log_message('error', "Erreur API forum ($path) : " . json_encode($data['errors']));
            return null;
        }

        return $data;
    }
}
