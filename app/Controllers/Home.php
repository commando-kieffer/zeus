<?php

namespace App\Controllers;

use CodeIgniter\HTTP\Exceptions\RedirectException;

use \App\Models\ProfileModel;
use \App\Models\PointsModel;
use \App\Models\OperationModel;
use \App\Models\OperationVoteModel;
use \App\Models\PanelUserModel;
use \App\Models\MetierModel;
use \App\Models\StatisticsModel;

class Home extends BaseController
{
    /**
     * Étendue maximale, en années, de la période du graphique de présences.
     * Ce graphique est visible de tous les membres avec des dates libres : sans
     * borne, une période de plusieurs siècles produirait des dizaines de
     * milliers de points à calculer et à envoyer au navigateur.
     */
    private const PRESENCE_MAX_YEARS = 10;

    public function __construct()
    {
        if (!session('is_logged_in')) {
            $route = empty(uri_string()) ? '/' : uri_string();
            session()->set('after_login_url', $route);
            throw new RedirectException('login');
            exit;
        }

        helper('date');
        helper('job_points');
        helper('period');
    }

    public function index(): string
    {
        $points_model = model(PointsModel::class);
        $operation_model = model(OperationModel::class);
        $vote_model = model(OperationVoteModel::class);
        $panel_user_model = model(PanelUserModel::class);

        $members = $points_model->get_active_members_by_troop($points_model->get_active_members_with_points());

        $member_ids = [];
        foreach ($members as $troop) {
            foreach ($troop['members'] as $member) {
                $member_ids[] = (int) $member->user_id;
            }
        }
        $platforms_by_member = $panel_user_model->get_by_user_ids($member_ids);

        // Badges "Métiers" (2 lettres, colorés par métier) et plateforme de
        // jeu (icône), affichés dans le tableau des membres de chaque troop.
        foreach ($members as $troop) {
            foreach ($troop['members'] as $member) {
                $member->metier_badges = MetierModel::badges_for_groups($member->secondary_group_ids);
                $member->platform = $platforms_by_member[(int) $member->user_id]->platform ?? null;
                $member->platform_icon = $member->platform !== null ? (PanelUserModel::PLATFORM_ICONS[$member->platform] ?? null) : null;
            }
        }

        $last_operation = $operation_model->get_last_operation();
        $next_operation = $operation_model->get_next_operation();

        $operations = array_filter([$last_operation, $next_operation]);
        $visible_averages = $vote_model->get_visible_averages_for_member(session('user')['user_id'], $operations);

        return view('generic/head')
            . view('generic/header')
            . view('home', [
                'members' => $members,
                'last_operation' => $last_operation,
                'next_operation' => $next_operation,
                'next_operation_today' => !empty($next_operation) && is_today($next_operation->date),
                'visible_averages' => $visible_averages,
                'vote_criteria_short' => OperationVoteModel::CRITERIA_SHORT,
            ])
            . view('generic/footer')
            . view('generic/foot');
    }

    public function not_found(): string
    {
        return view('generic/head')
        . view('generic/header')
        . view('404', [
            'message' => "J'ai cherché mais je n'ai pas trouvé la page que vous avez demandée... =(",
        ])
        . view('generic/footer')
        . view('generic/foot');
    }

    public function profil($user_id = -1)
    {
        $profil_model = model(ProfileModel::class);
        $points_model = model(PointsModel::class);
        $user = session("user");
        $is_own_profile = $user_id === -1;

        if ($user_id != -1) {
            $user = $profil_model->get_user_profile($user_id);
            if (empty($user) || !$profil_model->is_member($user)) {
                return view('generic/head')
                    . view('generic/header')
                    . view('404', [
                        'message' => "Le membre recherché n'existe pas ou ne fait plus partie du commando Kieffer.",
                    ])
                    . view('generic/footer')
                    . view('generic/foot');
            }
        }

        if (empty($user["avatar_urls"]["l"])) {
          $user["avatar_urls"]["l"] = "https://zeus.commandokieffer.com/pictures/medailles/beret_vert.jpg";
        }

        $profil = [
            'grade' => $profil_model->get_profil_title($user['user_group_id']),
            'stats' => $profil_model->get_profil_stats($user['user_id']),
            'joined_at' => $profil_model->get_profil_joined_at($user['user_id']),
            'troop_bordee_spe' => $profil_model->get_profil_troop_bordee_spe($user['secondary_group_ids']),
            'metiers' => $profil_model->get_profil_metier($user['secondary_group_ids']),
            'medailles' => $profil_model->get_profil_medal($user['user_id']),
        ];

        $history_page = max(0, (int) ($_GET['page'] ?? 0));
        $history_page_count = max(1, (int) ceil($points_model->get_history_count($user['user_id']) / 10));
        $history_page = min($history_page, $history_page_count - 1);
        $points_history = $points_model->get_history($user['user_id'], $history_page);

        $panel_user_model = model(PanelUserModel::class);
        $panel_user = $panel_user_model->get_by_user_id($user['user_id']);

        return view('generic/head')
            . view('generic/header')
            . view('profil', [
                'is_own_profile' => $is_own_profile,
                'user' => $user,
                'profil' => $profil,
                'presence' => $this->presence_history((int) $user['user_id']),
                'points_history' => $points_history,
                'history_page' => $history_page,
                'history_page_count' => $history_page_count,
                'profil_base_url' => $is_own_profile ? '/profil' : '/profil/' . $user['user_id'],
                'panel_user' => $panel_user,
                'platform_icon' => $panel_user !== null ? (PanelUserModel::PLATFORM_ICONS[$panel_user->platform] ?? null) : null,
                'platforms' => PanelUserModel::PLATFORMS,
                'info_errors' => session()->getFlashdata('info_errors') ?? [],
            ])
            . view('generic/footer')
            . view('generic/foot');
    }

    /**
     * Données du graphique "Historique des présences" d'un profil, pour la
     * période et l'intervalle demandés dans l'URL (presence_start,
     * presence_end, presence_granularity) : la dernière année par semaine
     * quand rien n'est demandé, ou quand une valeur est invalide.
     *
     * "query" reprend les paramètres du graphique quand l'URL en contenait,
     * pour que la pagination de l'historique des points ne les remette pas à
     * zéro en changeant de page.
     */
    private function presence_history(int $user_id): array
    {
        // Jour de Paris, comme le reste de l'application : le serveur tourne
        // en UTC, et juste après minuit à Paris la date UTC a un jour de retard.
        $today = new \DateTime('today', new \DateTimeZone('Europe/Paris'));

        $start = parse_period_date($_GET['presence_start'] ?? null) ?? (clone $today)->modify('-1 year')->format('Y-m-d');
        $end = parse_period_date($_GET['presence_end'] ?? null) ?? $today->format('Y-m-d');
        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        $earliest_start = (new \DateTime($end))->modify('-' . self::PRESENCE_MAX_YEARS . ' years')->format('Y-m-d');
        $start = max($start, $earliest_start);

        // Une opération par semaine au plus : un intervalle d'un jour n'aurait
        // aucun sens (comme pour le taux de présence des statistiques).
        $granularity = parse_period_granularity($_GET['presence_granularity'] ?? null, ['week', 'month'], 'week');

        $points = StatisticsModel::build_member_presence_series(
            model(StatisticsModel::class)->get_member_presence($user_id, $start, $end),
            $start,
            $end,
            $granularity
        );

        $is_filtered = isset($_GET['presence_start']) || isset($_GET['presence_end']) || isset($_GET['presence_granularity']);

        return [
            'start' => $start,
            'end' => $end,
            'granularity' => $granularity,
            'points' => $points,
            'has_data' => array_sum(array_column($points, 'reported')) > 0,
            'query' => $is_filtered ? [
                'presence_start' => $start,
                'presence_end' => $end,
                'presence_granularity' => $granularity,
            ] : [],
        ];
    }

    /**
     * Modifie le pseudo in-game et la plateforme de jeu du membre connecté
     * (modale "Modifier mes informations" de son propre profil).
     */
    public function update_info()
    {
        $rules = [
            'platform_username' => 'required|max_length[32]',
            'platform' => 'required|in_list[' . implode(',', PanelUserModel::PLATFORMS) . ']',
        ];

        if (!$this->validate($rules)) {
            session()->setFlashdata('info_errors', $this->validator->getErrors());
            return redirect()->to('/profil');
        }

        $user = session('user');
        model(PanelUserModel::class)->upsert($user['user_id'], trim($_POST['platform_username']), $_POST['platform']);

        return redirect()->to('/profil');
    }

    public function correct_point()
    {
        $points_model = model(PointsModel::class);

        $members = $points_model->get_active_members_by_troop($points_model->get_active_members());

        return view('generic/head')
            . view('generic/header')
            . view('correct_point', ['members' => $members])
            . view('generic/footer')
            . view('generic/foot');
    }

    public function blame()
    {
        $points_model = model(PointsModel::class);

        $members = $points_model->get_active_members_by_troop($points_model->get_active_members());

        return view('generic/head')
            . view('generic/header')
            . view('blame', ['members' => $members])
            . view('generic/footer')
            . view('generic/foot');
    }

    public function opex()
    {
        $points_model = model(PointsModel::class);

        $members = $points_model->get_active_members_by_troop($points_model->get_active_members());

        return view('generic/head')
            . view('generic/header')
            . view('opex', ['members' => $members])
            . view('generic/footer')
            . view('generic/foot');
    }

    public function warning()
    {
        $points_model = model(PointsModel::class);

        $members = $points_model->get_active_members_by_troop($points_model->get_active_members());

        return view('generic/head')
            . view('generic/header')
            . view('warning', ['members' => $members])
            . view('generic/footer')
            . view('generic/foot');
    }

    /**
     * Attribution des points de métier.
     *
     * La page se parcourt en deux temps : on choisit d'abord le métier à
     * récompenser, puis on valide la liste de ses membres. Un chef de service
     * ne se voit proposer que le ou les métiers qu'il dirige ; l'état-major et
     * le staff les voient tous.
     */
    public function work()
    {
        $user = session('user');
        $jobs = awardable_jobs($user);

        if ($jobs === []) {
            return view('generic/head')
                . view('generic/header')
                . view('404', ['message' => "Vous n'avez pas la permission d'accéder à cette page."])
                . view('generic/footer')
                . view('generic/foot');
        }

        // Un métier absent de la liste autorisée est traité comme non choisi :
        // le paramètre vient de l'URL et ne prouve rien.
        $selected_job = (int) ($_GET['job'] ?? 0);
        if (!isset($jobs[$selected_job])) {
            $selected_job = 0;
        }

        $members = $selected_job === 0
            ? []
            : model(PointsModel::class)->get_members_by_job(MetierModel::job_group_ids($selected_job));

        return view('generic/head')
            . view('generic/header')
            . view('work', [
                'jobs' => $jobs,
                'selected_job' => $selected_job,
                'members' => $members,
                'points_per_member' => PointsModel::WORK_POINTS,
            ])
            . view('generic/footer')
            . view('generic/foot');
    }

    public function select_training()
    {
        $points_model = model(PointsModel::class);

        $trainings = $points_model->get_training_list();

        return view('generic/head')
            . view('generic/header')
            . view('select_training', ['trainings' => $trainings])
            . view('generic/footer')
            . view('generic/foot');
    }

    public function modify_training()
    {
        $points_model = model(PointsModel::class);

        $members = $points_model->get_active_members_by_troop($points_model->get_active_members());
        $presence = $points_model->get_training_presence($_POST['training_id']);
        $training = $points_model->get_specific_training($_POST['training_id']);


        return view('generic/head')
            . view('generic/header')
            . view('modify_training', [
                'members' => $members,
                'presence' => $presence,
                'training' => $training
            ])
            . view('generic/footer')
            . view('generic/foot');
    }
}
