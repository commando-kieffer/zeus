<?php

namespace App\Controllers;

use CodeIgniter\HTTP\Exceptions\RedirectException;

use App\Models\MedalModel;
use App\Models\PointsModel;

class Medal extends BaseController
{
    public function __construct()
    {
        if (!session('is_logged_in')) {
            $route = empty(uri_string()) ? '/' : uri_string();
            session()->set('after_login_url', $route);
            throw new RedirectException('login');
            exit;
        }
    }

    private function render_message(string $message): string
    {
        return view('generic/head')
            . view('generic/header')
            . view('404', ['message' => $message])
            . view('generic/footer')
            . view('generic/foot');
    }

    /**
     * Date du vendredi le plus récent, aujourd'hui inclus (format Y-m-d) :
     * valeur par défaut de la date d'attribution d'une médaille, les
     * décorations étant généralement remises lors du training du vendredi.
     * Si on est déjà vendredi, c'est donc aujourd'hui, pas le vendredi
     * précédent.
     */
    private function default_medal_date(): string
    {
        $date = new \DateTime('today');
        $offset = ((int) $date->format('N') - 5 + 7) % 7; // N : 1 (lun.) .. 7 (dim.), vendredi = 5
        $date->modify('-' . $offset . ' days');
        return $date->format('Y-m-d');
    }

    public function index()
    {
        if (!is_team_leader(session('user'))) {
            return $this->render_message("Vous n'avez pas la permission d'accéder à cette page.");
        }

        $points_model = model(PointsModel::class);
        $medal_model = model(MedalModel::class);

        $members_by_troop = $points_model->get_active_members_by_troop($points_model->get_active_members());

        $member_ids = [];
        foreach ($members_by_troop as $troop) {
            foreach ($troop['members'] as $member) {
                $member_ids[] = $member->user_id;
            }
        }

        return view('generic/head')
            . view('generic/header')
            . view('medals', [
                'members_by_troop' => $members_by_troop,
                'all_medals' => $medal_model->get_all_medals(),
                'medals_by_member' => $medal_model->get_medal_ids_by_member($member_ids),
                'default_medal_date' => $this->default_medal_date(),
            ])
            . view('generic/footer')
            . view('generic/foot');
    }

    /**
     * Bascule une médaille pour un membre : l'ajoute s'il ne l'a pas encore,
     * la retire s'il l'a déjà.
     */
    public function toggle()
    {
        if (!is_team_leader(session('user'))) {
            return $this->render_message("Vous n'avez pas la permission d'accéder à cette page.");
        }

        $medal_model = model(MedalModel::class);
        $points_model = model(PointsModel::class);

        $valid_medal_ids = array_map(fn($medal) => $medal->id, $medal_model->get_all_medals());
        $valid_member_ids = array_map(fn($member) => $member->user_id, $points_model->get_active_members());

        $rules = [
            'member_id' => 'required|in_list[' . implode(',', $valid_member_ids) . ']',
            'medal_id' => 'required|in_list[' . implode(',', $valid_medal_ids) . ']',
            'medal_description' => 'permit_empty|string',
            'medal_date' => 'permit_empty|valid_date[Y-m-d]',
        ];

        if (!$this->validate($rules)) {
            return $this->render_message("Requête invalide.");
        }

        $member_id = $_POST['member_id'];
        $medal_id = $_POST['medal_id'];

        if ($medal_model->member_has_medal($member_id, $medal_id)) {
            $medal_model->remove_medal($member_id, $medal_id);
        } else {
            $description = trim((string) ($_POST['medal_description'] ?? ''));
            $date = $_POST['medal_date'] ?? '';
            if ($date === '') {
                $date = $this->default_medal_date();
            }
            $medal_model->add_medal($member_id, $medal_id, $description === '' ? null : $description, $date);
        }

        return redirect()->to('/medals');
    }
}
