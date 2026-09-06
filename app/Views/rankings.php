<main class="rankings">
    <section class="rankings-head">
        <h1>Classements</h1>
        <form method="get" class="rankings-filter">
            <select name="ranking" onchange="this.form.submit()">
                <?php foreach ($rankings as $key => $def) { ?>
                <option value="<?php echo $key ?>" <?php echo $key === $ranking ? 'selected' : '' ?>><?php echo esc($def['label']) ?></option>
                <?php } ?>
            </select>
        </form>
    </section>

    <section class="podium<?php echo $is_negative ? ' podium-negative' : '' ?>">
        <?php foreach ($podium_steps as $rank => $step_members) { ?>
        <div class="podium-spot podium-place-<?php echo $rank ?>">
            <div class="podium-cards">
                <?php foreach ($step_members as $member) { ?>
                <div class="podium-entry" onclick="window.location.href = '/profil/<?php echo $member->user_id ?>'">
                    <?php if ($is_negative) { ?>
                    <img class="podium-cap" src="/pictures/bonnet-ane.png" alt="Bonnet d'âne">
                    <?php } ?>
                    <div class="podium-card">
                        <img class="podium-grade" src="/pictures/jackets/<?php echo $member->user_group_id ?>.png" alt="">
                        <p class="podium-name"><?php echo esc($member->username) ?></p>
                        <p class="podium-value"><?php echo format_ranking_value($ranking, $member) ?></p>
                    </div>
                </div>
                <?php } ?>
            </div>
            <div class="podium-step">#<?php echo $rank ?></div>
        </div>
        <?php } ?>
    </section>

    <section class="rankings-list">
        <div class="tfc-container">
            <table>
                <tr>
                    <th>#</th>
                    <th>Nom</th>
                    <th>Grade</th>
                    <th>Points</th>
                    <th>Présences</th>
                    <th>Absences</th>
                    <th>Taux de présence</th>
                    <th>Médailles</th>
                    <th>Date d'adhésion</th>
                </tr>
                <?php foreach ($rest as $entry) { $member = $entry['member']; ?>
                <tr class="member" onclick="window.location.href = '/profil/<?php echo $member->user_id ?>'">
                    <td><?php echo $entry['rank'] ?></td>
                    <td><?php echo esc($member->username) ?></td>
                    <td><img src="/pictures/jackets/<?php echo $member->user_group_id ?>.png" alt=""></td>
                    <td><?php echo $member->panel_pts ?></td>
                    <td><?php echo $member->panel_prs ?></td>
                    <td><?php echo $member->panel_abs ?></td>
                    <td><?php echo ceil($member->presence_rate * 100) ?>%</td>
                    <td><?php echo $member->medal_count ?></td>
                    <td><?php echo $member->joined_at !== null ? (new DateTime($member->joined_at))->format('d/m/y') : '-' ?></td>
                </tr>
                <?php } ?>
            </table>
        </div>
    </section>
</main>
