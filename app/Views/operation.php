<main class="operations">
    <section class="operation-detail">
        <h1><?php echo esc($operation->name) ?></h1>
        <div class="operation-meta">
            <span><?php echo (new DateTime($operation->date))->format('d/m/Y') ?></span>
            <span><?php echo esc($operation->location) ?></span>
        </div>
        <div class="operation-description">
            <?php if (!empty($operation->description)) { ?>
                <?php echo nl2br(esc($operation->description)) ?>
            <?php } else { ?>
                <p>Aucune description fournie.</p>
            <?php } ?>
        </div>
        <a href="/operations" class="outline-btn-inverse">Retour à la liste</a>
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
