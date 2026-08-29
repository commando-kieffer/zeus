<main class="operations">
    <section class="operation-detail">
        <a href="/operations" class="outline-btn-inverse operation-back">&larr; Retour à la liste</a>
        <h1><?php echo esc($operation->name) ?></h1>
        <div class="operation-meta">
            <span><?php echo (new DateTime($operation->date))->format('d/m/Y') ?></span>
            <span><?php echo esc($operation->location) ?></span>
        </div>

        <?php if ($can_vote) { ?>
        <div class="operation-vote-card">
            <h2>Notez ce scénario</h2>
            <form action="/operations/<?php echo $operation->id ?>/vote" method="post">
                <?php foreach ($vote_criteria as $key => $label) { ?>
                <div class="vote-criterion">
                    <span class="vote-criterion-label"><?php echo esc($label) ?></span>
                    <div class="star-rating">
                        <?php for ($i = 5; $i >= 1; $i--) { ?>
                        <input type="radio" name="<?php echo $key ?>" value="<?php echo $i ?>" id="<?php echo $key ?>_<?php echo $i ?>" required>
                        <label for="<?php echo $key ?>_<?php echo $i ?>">&#9733;</label>
                        <?php } ?>
                    </div>
                </div>
                <?php } ?>
                <button type="submit" class="solid-btn">Envoyer ma note</button>
            </form>
        </div>
        <?php } elseif ($can_view_averages) { ?>
        <div class="operation-vote-card operation-vote-results">
            <h2>Notes du scénario</h2>
            <?php if ($vote_averages->voter_count > 0) { ?>
            <ul class="vote-results-list">
                <?php foreach ($vote_criteria as $key => $label) { $avg_field = $key . '_avg'; ?>
                <li>
                    <span class="vote-criterion-label"><?php echo esc($label) ?></span>
                    <span class="vote-criterion-score">&#9733; <?php echo $vote_averages->$avg_field ?></span>
                </li>
                <?php } ?>
            </ul>
            <p class="vote-voter-count"><?php echo $vote_averages->voter_count ?> vote<?php echo $vote_averages->voter_count > 1 ? 's' : '' ?></p>
            <?php } else { ?>
            <p class="vote-empty">Aucun vote pour le moment.</p>
            <?php } ?>
        </div>
        <?php } ?>

        <div class="operation-description">
            <?php if (!empty($operation->description)) { ?>
                <?php echo nl2br(esc($operation->description)) ?>
            <?php } else { ?>
                <p>Aucune description fournie.</p>
            <?php } ?>
        </div>
    </section>

    <?php if ($can_view_report) { ?>
    <section class="operation-report">
        <h2>Rapport de présence</h2>
        <?php if ($is_officer) { ?>
        <form action="/operations/<?php echo $operation->id ?>/update_report" method="post">
        <?php } ?>
            <div class="operation-report-grid">
                <?php foreach ($report_troops as $troop) { if (!empty($troop['members'])) { ?>
                <div class="tfc-container">
                    <div class="badge badge<?php echo $troop['id'] ?>"><?php echo $troop['title']; ?></div>
                    <table>
                        <tr>
                            <th>Nom</th>
                            <th>Grade</th>
                            <th>Présence</th>
                        </tr>
                        <?php foreach ($troop['members'] as $member) {
                            $present = !empty($report_map[$member->user_id]);
                        ?>
                        <tr class="tfcc-member">
                            <td><?php echo $member->username ?></td>
                            <td><img src="/pictures/jackets/<?php echo $member->user_group_id ?>.png" alt=""></td>
                            <td>
                                <?php if ($is_officer) { ?>
                                <input type="checkbox" name="<?php echo $member->user_id ?>" <?php echo $present ? 'checked' : '' ?>>
                                <?php } else { ?>
                                <input type="checkbox" disabled <?php echo $present ? 'checked' : '' ?>>
                                <?php } ?>
                            </td>
                        </tr>
                        <?php } ?>
                    </table>
                </div>
                <?php }} ?>
            </div>
        <?php if ($is_officer) { ?>
            <div class="operation-report-actions">
                <button type="submit" class="outline-btn-inverse">METTRE À JOUR</button>
            </div>
        </form>
        <?php } ?>
    </section>
    <?php } ?>
</main>
