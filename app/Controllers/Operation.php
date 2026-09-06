<?php

namespace App\Controllers;

use CodeIgniter\HTTP\Exceptions\RedirectException;

use App\Models\OperationModel;
use App\Models\OperationReportNoteModel;
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

        $visible_averages = $vote_model->get_visible_averages_for_member(session('user')['user_id'], $operations);

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

        $points_model = model(PointsModel::class);
        $report_troops = $points_model->get_active_members_by_troop($points_model->get_active_members());
        $report_map = $operation_model->get_operation_report($operation_id);

        // Un membre rapporté (présent ou absent) doit rester visible même s'il
        // n'appartient plus aux membres actifs (parti du commando, changé de
        // troop...) : on complète les troops avec les membres manquants.
        $known_ids = [];
        foreach ($report_troops as $troop) {
            foreach ($troop['members'] as $member) {
                $known_ids[] = (int) $member->user_id;
            }
        }
        $missing_ids = array_diff(array_map('intval', array_keys($report_map)), $known_ids);
        if (!empty($missing_ids)) {
            $former_members = $operation_model->get_members_by_ids(array_values($missing_ids));
            if (!empty($former_members)) {
                $report_troops['former'] = [
                    'title' => 'ANCIENS MEMBRES',
                    'id' => 'former',
                    'members' => $former_members,
                ];
            }
        }

        $vote_model = model(OperationVoteModel::class);
        $presence = $operation_model->get_member_presence($operation_id, $user['user_id']);
        $has_voted = $vote_model->has_voted($operation_id, $user['user_id']);
        $voting_closed = is_voting_closed($operation->date);
        $can_vote = $presence === 'present' && !$has_voted && !$voting_closed;
        // Une fois le vote fermé, même un participant qui n'a jamais noté voit
        // le résultat : il ne peut de toute façon plus voter.
        $can_view_averages = $presence !== 'present' || $has_voted || $voting_closed;

        $note_model = model(OperationReportNoteModel::class);

        return view('generic/head')
            . view('generic/header')
            . view('operation', [
                'operation' => $operation,
                'operation_done' => is_operation_done($operation->date),
                'is_officer' => is_officer($user),
                'is_team_leader' => is_team_leader($user),
                'is_squad_leader' => is_squad_leader($user),
                'own_troop_id' => $operation_model->get_member_troop_id($user['secondary_group_ids']),
                'report_troops' => $report_troops,
                'report_map' => $report_map,
                'report_notes' => $note_model->get_notes_for_operation($operation_id),
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
            $status = $_POST[$member->user_id] ?? 'absent';
            if (!in_array($status, OperationModel::STATUSES, true)) {
                $status = 'absent';
            }

            $operation_model->record_operation_status($member->user_id, $operation_array, $user['user_id'], $status);
        }

        $note_content = trim($_POST['note'] ?? '');
        if ($note_content !== '') {
            $note_model = model(OperationReportNoteModel::class);
            $note_model->set_note($operation_id, (int) $troop['id'], $note_content, $user['user_id']);
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
            $new_status = $_POST[$member->user_id] ?? 'absent';
            if (!in_array($new_status, OperationModel::STATUSES, true)) {
                $new_status = 'absent';
            }

            $had_report = array_key_exists($member->user_id, $current_report);
            $old_status = $current_report[$member->user_id] ?? 'absent';

            if (!$had_report) {
                // Un statut laissé sur "absent" (valeur par défaut) pour un membre
                // jamais rapporté n'est pas une modification : on ne crée un
                // rapport que si un autre statut a été choisi.
                if ($new_status !== 'absent') {
                    $operation_model->record_operation_status($member->user_id, $operation_array, $user['user_id'], $new_status);
                }
                continue;
            }

            if ($new_status !== $old_status) {
                $operation_model->correct_operation_status($member->user_id, $operation_array, $user['user_id'], $old_status, $new_status);
            }
        }

        $note_model = model(OperationReportNoteModel::class);
        $troops = $points_model->get_active_members_by_troop($members);

        foreach (array_keys($troops) as $troop_id) {
            $field = 'note_' . $troop_id;
            if (!array_key_exists($field, $_POST)) {
                continue;
            }

            $content = trim($_POST[$field]);
            $existing_note = $note_model->get_note($operation_id, (int) $troop_id);

            if ($existing_note === null) {
                if ($content !== '') {
                    $note_model->set_note($operation_id, (int) $troop_id, $content, $user['user_id']);
                }
            } elseif ($content !== $existing_note->content) {
                $note_model->update_note($operation_id, (int) $troop_id, $content, $user['user_id']);
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

        if (is_voting_closed($operation->date)) {
            return $this->render_message("La période de notation de cette opération est terminée (deux semaines après l'opération).");
        }

        $vote_model = model(OperationVoteModel::class);
        $presence = $operation_model->get_member_presence($operation_id, $user['user_id']);

        if ($presence !== 'present') {
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
