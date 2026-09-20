<?php

namespace App\Models;

use CodeIgniter\Model;

class MedalModel extends Model
{
    public function __construct()
    {
        parent::__construct();
        $this->table = "-";
    }

    /**
     * Médailles dont les conditions sont objectivement vérifiables.
     *
     * medal_id => [critère, seuil]. Le seuil s'entend toujours « atteint ou
     * dépassé » : les libellés en « plus de N » sont donc enregistrés à N+1,
     * ce qui évite d'avoir deux opérateurs de comparaison à maintenir.
     *
     * Volontairement absentes :
     *  - la Légion d'honneur (5 ans MAIS « faits d'armes exceptionnels ») et
     *    toutes les décorations au mérite, à la conduite ou à la connaissance :
     *    leur appréciation est humaine, les suggérer automatiquement
     *    reviendrait à les dévaluer ;
     *  - les décorations liées aux matchs et tournois : le panel ne tient
     *    aucun compteur de participation.
     */
    public const AUTO_RULES = [
        2  => ['points', 400],       // Médaille de bronze
        3  => ['points', 900],       // Médaille d'argent
        4  => ['points', 1500],      // Médaille d'or
        21 => ['presences', 11],     // Défense nationale : plus de 10 trainings
        22 => ['presences', 21],     // Croix du combattant : plus de 20
        23 => ['presences', 51],     // Combattant volontaire 39/45 : plus de 50
        8  => ['anciennete', 1],     // Médaille militaire : 1 an
        9  => ['anciennete', 2],     // Mérite maritime : 2 ans
        10 => ['anciennete', 3],     // Mérite militaire : 3 ans
        11 => ['anciennete', 4],     // Chevalier ONM : 4 ans
        24 => ['messages', 750],     // Médaille des évadés
        25 => ['messages', 2000],    // France Libérée
    ];

    /**
     * Statistiques des membres actifs nécessaires aux règles automatiques.
     *
     * MIN(ir.date) : un membre peut figurer plusieurs fois dans
     * infos_recrutement s'il a repostulé. L'ancienneté se compte depuis sa
     * PREMIÈRE arrivée, ce que dit le mot « ancien ».
     */
    public function get_members_award_stats(): array
    {
        $query = "SELECT u.user_id,
                         u.username,
                         u.user_group_id,
                         u.panel_pts,
                         u.panel_prs,
                         u.message_count,
                         MIN(ir.date) AS joined_at
                  FROM xf_user u
                  LEFT JOIN infos_recrutement ir ON ir.user_id = u.user_id
                  WHERE (u.user_group_id BETWEEN 5 AND 20) OR u.user_group_id IN (50, 54)
                  GROUP BY u.user_id, u.username, u.user_group_id,
                           u.panel_pts, u.panel_prs, u.message_count
                  ORDER BY u.username ASC";

        return $this->db->query($query)->getResult();
    }

    /**
     * Décorations qu'un membre a méritées mais ne détient pas encore.
     *
     * Le calcul est fait EN PHP à partir de données déjà chargées, plutôt
     * qu'en une requête par règle. La page d'attribution lit de toute façon
     * déjà la liste des membres et la totalité de medal_attribut : le contrôle
     * ne coûte donc qu'une seule requête supplémentaire, celle des
     * statistiques, là où la variante paresseuse en imposerait une par
     * décoration — douze allers-retours pour des données déjà en mémoire.
     * Le volume rend la boucle négligeable : quelques dizaines de membres
     * multipliés par douze règles.
     *
     * Les décorations déjà détenues sont relues ICI, pour exactement les
     * membres évalués. Réutiliser la liste constituée pour les tableaux par
     * troop serait tentant mais faux : elle ne couvre que les membres rattachés
     * à une troop, et tout membre qui n'en a pas se verrait attribuer une liste
     * vide — donc proposer des décorations qu'il possède déjà.
     *
     * @param object[] $all_medals toutes les décorations
     * @return array<int, array{member: object, medal: object, reason: string}>
     */
    public function get_pending_awards(array $all_medals): array
    {
        $medals_by_id = [];
        foreach ($all_medals as $medal) {
            $medals_by_id[(int) $medal->id] = $medal;
        }

        $members = $this->get_members_award_stats();
        $medals_by_member = $this->get_medal_ids_by_member(
            array_map(static fn($member) => (int) $member->user_id, $members)
        );

        $today = new \DateTime('today');
        $pending = [];

        foreach ($members as $member) {
            $owned = $medals_by_member[$member->user_id] ?? [];

            $years = null;
            if (!empty($member->joined_at)) {
                $years = (int) $today->diff(new \DateTime($member->joined_at))->y;
            }

            foreach (self::AUTO_RULES as $medal_id => [$criterion, $threshold]) {
                if (!isset($medals_by_id[$medal_id]) || in_array($medal_id, $owned)) {
                    continue;
                }

                switch ($criterion) {
                    case 'points':
                        $value = (int) $member->panel_pts;
                        $reason = $value . ' points';
                        break;
                    case 'presences':
                        $value = (int) $member->panel_prs;
                        $reason = $value . ' présences';
                        break;
                    case 'messages':
                        $value = (int) $member->message_count;
                        $reason = $value . ' messages';
                        break;
                    case 'anciennete':
                        // Sans date de recrutement connue, l'ancienneté ne peut
                        // pas être établie : mieux vaut ne rien suggérer que
                        // suggérer à tort.
                        if ($years === null) {
                            continue 2;
                        }
                        $value = $years;
                        $reason = $years . ($years > 1 ? ' ans' : ' an') . " d'ancienneté";
                        break;
                    default:
                        continue 2;
                }

                if ($value >= $threshold) {
                    $pending[] = [
                        'member' => $member,
                        'medal'  => $medals_by_id[$medal_id],
                        'reason' => $reason,
                    ];
                }
            }
        }

        return $pending;
    }

    public function get_all_medals()
    {
        $query = "SELECT id, name, title, description FROM medal ORDER BY title ASC";
        $result = $this->db->query($query);
        return $result->getResult();
    }

    /**
     * Médailles détenues par un ensemble de membres, en une seule requête.
     *
     * @return array<int, int[]> id_user => [id_medal, ...]
     */
    public function get_medal_ids_by_member(array $member_ids)
    {
        if (empty($member_ids)) return [];

        $placeholders = implode(',', array_fill(0, count($member_ids), '?'));
        $query = "SELECT id_user, id_medal FROM medal_attribut WHERE id_user IN ($placeholders)";
        $result = $this->db->query($query, $member_ids);

        $medals_by_member = [];
        foreach ($result->getResult() as $row) {
            $medals_by_member[$row->id_user][] = $row->id_medal;
        }

        return $medals_by_member;
    }

    public function member_has_medal($member_id, $medal_id): bool
    {
        $query = "SELECT COUNT(*) AS total FROM medal_attribut WHERE id_user = ? AND id_medal = ?";
        $result = $this->db->query($query, array($member_id, $medal_id));
        return $result->getResult()[0]->total > 0;
    }

    /**
     * Attribue une médaille à un membre. $description est le texte affiché en
     * complément de la description générique de la médaille sur la page de
     * profil (null pour n'afficher que la description générique) ; $date est la
     * date d'attribution au format Y-m-d.
     */
    public function add_medal($member_id, $medal_id, ?string $description = null, ?string $date = null)
    {
        $query = "INSERT INTO medal_attribut (id_medal, id_user, description, `date`) VALUES (?, ?, ?, ?)";
        $this->db->query($query, array($medal_id, $member_id, $description, $date));
    }

    public function remove_medal($member_id, $medal_id)
    {
        $query = "DELETE FROM medal_attribut WHERE id_medal = ? AND id_user = ?";
        $this->db->query($query, array($medal_id, $member_id));
    }
}
