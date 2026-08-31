<?php

namespace App\Controllers;

use CodeIgniter\HTTP\Exceptions\RedirectException;

use App\Models\OperationModel;
use App\Models\OperationVoteModel;
use App\Models\PointsModel;

class Operation extends BaseController
{
    public function __construct()
    {
        if (!session('is_logged_in')) {
            $route = empty(uri_string()) ? '/' : uri_string();
            session()->set('after_login_url', $route);
            throw new RedirectException('login');
            exit;
        }

        helper('date');
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
     * Récupère la troop (titre + membres actifs) de l'utilisateur courant,
     * ou null si celui-ci n'appartient à aucune troop connue.
     */
    private function get_own_troop($user)
    {
        $operation_model = model(OperationModel::class);
        $points_model = model(PointsModel::class);

        $troop_id = $operation_model->get_member_troop_id($user['secondary_group_ids']);
        if ($troop_id === null) {
            return null;
        }

        $troops = $points_model->get_active_members_by_troop($points_model->get_active_members());

        return $troops[$troop_id] ?? null;
    }

    public function index()
    {
        $operation_model = model(OperationModel::class);
        $vote_model = model(OperationVoteModel::class);
        $operations = $operation_model->get_all_operations();

        $operation_ids = array_map(fn($op) => $op->id, $operations);
        $visible_averages = $vote_model->get_visible_averages_for_member(session('user')['user_id'], $operation_ids);

        return view('generic/head')
            . view('generic/header')
            . view('operations', [
                'operations' => $operations,
                'visible_averages' => $visible_averages,
                'vote_criteria_short' => OperationVoteModel::CRITERIA_SHORT,
            ])
            . view('generic/footer')
            . view('generic/foot');
    }

    public function show($operation_id)
    {
        $operation_model = model(OperationModel::class);
        $operation = $operation_model->get_operation($operation_id);

        if (empty($operation)) {
            return $this->render_message("L'opération recherchée n'existe pas.");
        }

        $user = session('user');
        $can_view_report = is_squad_or_team_leader($user);
        $report_troops = [];
        $report_map = [];

        if ($can_view_report) {
            $points_model = model(PointsModel::class);
            $report_troops = $points_model->get_active_members_by_troop($points_model->get_active_members());
            $report_map = $operation_model->get_operation_report($operation_id);
        }

        $vote_model = model(OperationVoteModel::class);
        $presence = $operation_model->get_member_presence($operation_id, $user['user_id']);
        $has_voted = $vote_model->has_voted($operation_id, $user['user_id']);
        $can_vote = $presence === true && !$has_voted;
        $can_view_averages = $presence !== true || $has_voted;

        return view('generic/head')
            . view('generic/header')
            . view('operation', [
                'operation' => $operation,
                'can_view_report' => $can_view_report,
                'operation_done' => is_operation_done($operation->date),
                'is_officer' => is_officer($user),
                'report_troops' => $report_troops,
                'report_map' => $report_map,
                'can_vote' => $can_vote,
                'can_view_averages' => $can_view_averages,
                'vote_criteria' => OperationVoteModel::CRITERIA,
                'vote_averages' => $vote_model->get_averages($operation_id),
            ])
            . view('generic/footer')
            . view('generic/foot');
    }

    public function create()
    {
        if (!can_create_operations(session('user'))) {
            return $this->render_message("Vous n'avez pas la permission d'accéder à cette page.");
        }

        $points_model = model(PointsModel::class);

        return view('generic/head')
            . view('generic/header')
            . view('create_operation', ['members' => $points_model->get_active_members()])
            . view('generic/footer')
            . view('generic/foot');
    }

    public function store()
    {
        if (!can_create_operations(session('user'))) {
            return $this->render_message("Vous n'avez pas la permission d'accéder à cette page.");
        }

        $rules = [
            'name' => 'required|max_length[255]',
            'date' => 'required|valid_date[Y-m-d]',
            'location' => 'required|max_length[255]',
            'description' => 'permit_empty|max_length[5000]',
            'scenarist_id' => 'required|is_natural_no_zero',
        ];

        if (!$this->validate($rules)) {
            session()->setFlashdata('errors', $this->validator->getErrors());
            session()->setFlashdata('old', $_POST);
            return redirect()->to('/operations/create');
        }

        $operation_model = model(OperationModel::class);
        $operation_id = $operation_model->create_operation(
            $_POST['name'],
            $_POST['date'],
            $_POST['location'],
            $_POST['description'] ?? '',
            session('user')['user_id'],
            (int) $_POST['scenarist_id']
        );

        return redirect()->to('/operations/' . $operation_id);
    }

    public function report()
    {
        $user = session('user');
        if (!is_squad_or_team_leader($user)) {
            return $this->render_message("Vous n'avez pas la permission d'accéder à cette page.");
        }

        $troop = $this->get_own_troop($user);
        if ($troop === null) {
            return $this->render_message("Vous n'êtes rattaché à aucune troop, vous ne pouvez donc pas rédiger de rapport.");
        }

        $operation_model = model(OperationModel::class);
        $member_ids = array_map(fn($member) => $member->user_id, $troop['members']);
        $operations = $operation_model->get_pending_operations_for_troop($member_ids);
        $operations = array_values(array_filter($operations, fn($op) => is_operation_done($op->date)));

        return view('generic/head')
            . view('generic/header')
            . view('select_operation_report', ['operations' => $operations])
            . view('generic/footer')
            . view('generic/foot');
    }

    public function report_form($operation_id)
    {
        $user = session('user');
        if (!is_squad_or_team_leader($user)) {
            return $this->render_message("Vous n'avez pas la permission d'accéder à cette page.");
        }

        $operation_model = model(OperationModel::class);
        $operation = $operation_model->get_operation($operation_id);
        if (empty($operation)) {
            return $this->render_message("L'opération recherchée n'existe pas.");
        }

        if (!is_operation_done($operation->date)) {
            return $this->render_message("Le rapport de cette opération ne peut être rédigé qu'à partir de 21h le jour de l'opération.");
        }

        $troop = $this->get_own_troop($user);
        if ($troop === null) {
            return $this->render_message("Vous n'êtes rattaché à aucune troop, vous ne pouvez donc pas rédiger de rapport.");
        }

        $member_ids = array_map(fn($member) => $member->user_id, $troop['members']);
        if ($operation_model->has_troop_reported($operation_id, $member_ids)) {
            return $this->render_message("Un rapport a déjà été soumis pour votre troop pour cette opération.");
        }

        return view('generic/head')
            . view('generic/header')
            . view('operation_report', ['operation' => $operation, 'troop' => $troop])
            . view('generic/footer')
            . view('generic/foot');
    }

    public function submit_report($operation_id)
    {
        $user = session('user');
        if (!is_squad_or_team_leader($user)) {
            return $this->render_message("Vous n'avez pas la permission d'accéder à cette page.");
        }

        $operation_model = model(OperationModel::class);
        $operation = $operation_model->get_operation($operation_id);
        if (empty($operation)) {
            return $this->render_message("L'opération recherchée n'existe pas.");
        }

        if (!is_operation_done($operation->date)) {
            return $this->render_message("Le rapport de cette opération ne peut être rédigé qu'à partir de 21h le jour de l'opération.");
        }

        $troop = $this->get_own_troop($user);
        if ($troop === null) {
            return $this->render_message("Vous n'êtes rattaché à aucune troop, vous ne pouvez donc pas rédiger de rapport.");
        }

        $member_ids = array_map(fn($member) => $member->user_id, $troop['members']);
        if ($operation_model->has_troop_reported($operation_id, $member_ids)) {
            return $this->render_message("Un rapport a déjà été soumis pour votre troop pour cette opération.");
        }

        $operation_array = (array) $operation;

        foreach ($troop['members'] as $member) {
            if (isset($_POST[$member->user_id])) {
                $operation_model->set_operation_presence($member->user_id, $operation_array, $user['user_id']);
            } else {
                $operation_model->set_operation_absence($member->user_id, $operation_array, $user['user_id']);
            }
        }

        return redirect('operation_success');
    }

    /**
     * Correction du rapport complet d'une opération (toutes troops confondues),
     * réservée aux officiers. Seules les cases modifiées par rapport à l'état
     * actuel entraînent une écriture (rapport + compteurs + historique de points).
     */
    public function update_report($operation_id)
    {
        $user = session('user');
        if (!is_officer($user)) {
            return $this->render_message("Vous n'avez pas la permission d'accéder à cette page.");
        }

        $operation_model = model(OperationModel::class);
        $operation = $operation_model->get_operation($operation_id);
        if (empty($operation)) {
            return $this->render_message("L'opération recherchée n'existe pas.");
        }

        if (!is_operation_done($operation->date)) {
            return $this->render_message("Le rapport de cette opération ne peut être rédigé qu'à partir de 21h le jour de l'opération.");
        }

        $operation_array = (array) $operation;
        $current_report = $operation_model->get_operation_report($operation_id);

        $points_model = model(PointsModel::class);
        $members = $points_model->get_active_members();

        foreach ($members as $member) {
            $new_present = isset($_POST[$member->user_id]);
            $had_report = array_key_exists($member->user_id, $current_report);
            $was_present = $current_report[$member->user_id] ?? false;

            if (!$had_report) {
                // Une case laissée décochée pour un membre jamais rapporté n'est
                // pas une modification : on ne crée un rapport que s'il est coché.
                if ($new_present) {
                    $operation_model->set_operation_presence($member->user_id, $operation_array, $user['user_id']);
                }
                continue;
            }

            if ($was_present && !$new_present) {
                $operation_model->correct_to_absent($member->user_id, $operation_array, $user['user_id']);
            } elseif (!$was_present && $new_present) {
                $operation_model->correct_to_present($member->user_id, $operation_array, $user['user_id']);
            }
        }

        return redirect()->to('/operations/' . $operation_id);
    }

    /**
     * Vote d'un membre sur les 3 critères d'une opération. Réservé aux membres
     * ayant été rapportés présents à cette opération, et une seule fois chacun.
     */
    public function submit_vote($operation_id)
    {
        $user = session('user');
        $operation_model = model(OperationModel::class);
        $operation = $operation_model->get_operation($operation_id);

        if (empty($operation)) {
            return $this->render_message("L'opération recherchée n'existe pas.");
        }

        $vote_model = model(OperationVoteModel::class);
        $presence = $operation_model->get_member_presence($operation_id, $user['user_id']);

        if ($presence !== true) {
            return $this->render_message("Vous n'avez pas participé à cette opération, vous ne pouvez donc pas la noter.");
        }

        if ($vote_model->has_voted($operation_id, $user['user_id'])) {
            return $this->render_message("Vous avez déjà noté cette opération.");
        }

        $rules = [
            'map_rating' => 'required|in_list[1,2,3,4,5]',
            'mapping_rating' => 'required|in_list[1,2,3,4,5]',
            'defense_rating' => 'required|in_list[1,2,3,4,5]',
            'attack_rating' => 'required|in_list[1,2,3,4,5]',
        ];

        if (!$this->validate($rules)) {
            return $this->render_message("Merci de donner une note (de 1 à 5) pour chaque critère.");
        }

        $vote_model->submit_vote(
            $operation_id,
            $user['user_id'],
            (int) $_POST['map_rating'],
            (int) $_POST['mapping_rating'],
            (int) $_POST['defense_rating'],
            (int) $_POST['attack_rating']
        );

        return redirect()->to('/operations/' . $operation_id);
    }
}
