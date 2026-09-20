<main class="training">
    <div class="training-form">
        <div class="tf-head">
            <h1>Appliquer des points de métiers</h1>
        </div>

        <form action="/points/work" method="get" class="job-picker">
            <label for="job">Métier à récompenser</label>
            <select name="job" id="job">
                <option value="0">— Choisir un métier —</option>
                <?php foreach ($jobs as $job_id => $job_title) { ?>
                <option value="<?php echo (int) $job_id ?>" <?php echo $selected_job === $job_id ? 'selected' : '' ?>>
                    <?php echo esc($job_title) ?>
                </option>
                <?php } ?>
            </select>
            <button type="submit" class="outline-btn-inverse">Afficher les membres</button>
        </form>

        <?php if ($selected_job === 0) { ?>
        <p class="job-empty">Choisissez un métier pour voir les membres à récompenser.</p>
        <?php } elseif (empty($members)) { ?>
        <p class="job-empty">Aucun membre actif n'exerce ce métier.</p>
        <?php } else { ?>

        <form action="/points/add_work" method="post">
            <input type="hidden" name="job" value="<?php echo (int) $selected_job ?>">
            <div class="tf-content">
                <div class="tfc-container">
                    <div class="job-summary">
                        <strong><?php echo esc($jobs[$selected_job]) ?></strong>
                        — <?php echo count($members) ?> membre(s),
                        <?php echo (int) $points_per_member ?> points chacun.
                        Décochez ceux qui ne doivent pas être récompensés.
                    </div>
                    <table>
                        <tr>
                            <th>Nom</th>
                            <th>Grade</th>
                            <th>À récompenser</th>
                        </tr>
                        <?php foreach ($members as $member) { ?>
                        <tr class="tfcc-member">
                            <td><?php echo esc($member->username) ?></td>
                            <td><img src="/pictures/jackets/<?php echo $member->user_group_id ?>.png" alt=""></td>
                            <td><input name="<?php echo (int) $member->user_id ?>" type="checkbox" checked></td>
                        </tr>
                        <?php } ?>
                    </table>
                </div>
            </div>
            <div class="tf-foot">
                <button type="submit" class="outline-btn-inverse">ENVOYER LES POINTS</button>
            </div>
        </form>

        <?php } ?>
    </div>
</main>
