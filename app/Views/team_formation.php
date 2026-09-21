<main class="training team-formation">
    <?php if ($teams !== null) {
        $rank_titles = ['etat_major' => 'État-Major', 'qm' => 'Quartier-Maîtres', 'troupe' => "Reste de l'équipage"];
    ?>
    <section class="tf-results">
        <h2>Équipes formées</h2>
        <div class="teams-grid">
            <?php foreach ($teams as $i => $team) { ?>
            <div class="team-card">
                <h3>Équipe <?php echo $i + 1 ?><span class="team-count"><?php echo count($team) ?></span></h3>
                <ul>
                    <?php $previous_rank_bucket = null; foreach ($team as $member) {
                        if ($member->rank_bucket !== $previous_rank_bucket) { ?>
                    <li class="team-rank-title"><?php echo esc($rank_titles[$member->rank_bucket]) ?></li>
                    <?php } ?>
                    <li>
                        <img src="/pictures/jackets/<?php echo $member->user_group_id ?>.png" alt="">
                        <span class="team-member-name"><?php echo esc($member->username) ?></span>
                        <span class="badge badge<?php echo $member->troop_id ?>"><?php echo esc($member->troop_title) ?></span>
                        <?php if ($member->spe_title !== null) { ?>
                        <span class="badge badgespe"><?php echo esc($member->spe_title) ?></span>
                        <?php } ?>
                    </li>
                    <?php $previous_rank_bucket = $member->rank_bucket; } ?>
                </ul>
            </div>
            <?php } ?>
        </div>
    </section>
    <?php } ?>

    <form action="/outils/formation-equipes" method="post" class="training-form">
        <div class="tf-head">
            <h1>Formation d'équipes</h1>
            <?php if (!empty($errors)) { ?>
            <ul class="form-errors">
                <?php foreach ($errors as $error) { ?><li><?php echo esc($error) ?></li><?php } ?>
            </ul>
            <?php } ?>
            <div class="tf-sub">
                <label class="tf-field">
                    <span>Nombre d'équipes</span>
                    <input type="number" name="team_count" min="2" value="<?php echo $team_count ?>">
                </label>
                <label class="tf-checkbox">
                    <input type="checkbox" name="consider_rank" value="1" <?php echo $consider_rank ? 'checked' : '' ?>>
                    <span>Répartir équitablement les QM et l'État-Major</span>
                </label>
                <label class="tf-checkbox">
                    <input type="checkbox" name="consider_spe" value="1" <?php echo $consider_spe ? 'checked' : '' ?>>
                    <span>Répartir équitablement les spécialités</span>
                </label>
            </div>
        </div>
        <div class="tf-content">
            <?php foreach ($troops as $troop) { ?>
            <div class="tfc-container">
                <label class="troop-toggle">
                    <input type="checkbox" class="troop-select-all" data-troop="<?php echo $troop['id'] ?>">
                    <span class="badge badge<?php echo $troop['id'] ?>"><?php echo esc($troop['title']) ?></span>
                </label>
                <table>
                    <tr>
                        <th></th>
                        <th>Nom</th>
                        <th>Grade</th>
                        <th>Spécialité</th>
                    </tr>
                    <?php foreach ($troop['members'] as $member) { ?>
                    <tr class="tfcc-member">
                        <td><input type="checkbox" class="member-checkbox" data-troop="<?php echo $troop['id'] ?>" name="member_<?php echo $member->user_id ?>" value="1" <?php echo $member->checked ? 'checked' : '' ?>></td>
                        <td><?php echo esc($member->username) ?></td>
                        <td><img src="/pictures/jackets/<?php echo $member->user_group_id ?>.png" alt=""></td>
                        <td><?php echo $member->spe_title !== null ? esc($member->spe_title) : '—' ?></td>
                    </tr>
                    <?php } ?>
                </table>
            </div>
            <?php } ?>
        </div>
        <div class="tf-foot">
            <button type="submit" class="outline-btn-inverse">FORMER LES ÉQUIPES</button>
        </div>
    </form>
</main>

<script>
    function syncTroopToggle(troopId) {
        var members = document.querySelectorAll('.member-checkbox[data-troop="' + troopId + '"]');
        var toggle = document.querySelector('.troop-select-all[data-troop="' + troopId + '"]');
        if (!toggle || !members.length) return;

        var checkedCount = Array.prototype.filter.call(members, function (c) { return c.checked; }).length;
        toggle.checked = checkedCount === members.length;
        toggle.indeterminate = checkedCount > 0 && checkedCount < members.length;
    }

    document.querySelectorAll('.troop-select-all').forEach(function (toggle) {
        syncTroopToggle(toggle.dataset.troop);

        toggle.addEventListener('change', function () {
            document.querySelectorAll('.member-checkbox[data-troop="' + toggle.dataset.troop + '"]').forEach(function (checkbox) {
                checkbox.checked = toggle.checked;
            });
            toggle.indeterminate = false;
        });
    });

    document.querySelectorAll('.member-checkbox').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            syncTroopToggle(checkbox.dataset.troop);
        });
    });
</script>
