<main class="operations">
    <section class="operations-head">
        <h1>Opérations</h1>
    </section>
    <section class="operations-list">
        <?php if (empty($operations)) { ?>
        <p class="empty-state">Aucune opération n'a encore été créée.</p>
        <?php } else { foreach ($operations as $op) { ?>
        <a class="operation-card" href="/operations/<?php echo $op->id ?>">
            <h2><?php echo esc($op->name) ?></h2>
            <p class="operation-date"><?php echo (new DateTime($op->date))->format('d/m/Y') ?></p>
            <p class="operation-location"><?php echo esc($op->location) ?></p>
            <?php if (!empty($op->scenarist_name)) { ?>
            <p class="operation-scenarist">Par <?php echo esc($op->scenarist_name) ?></p>
            <?php } ?>
            <?php if (isset($visible_averages[$op->id])) { $avg = $visible_averages[$op->id]; ?>
            <div class="card-ratings">
                <?php foreach ($vote_criteria_short as $key => $label) { $avg_field = $key . '_avg'; ?>
                <div class="card-rating-row">
                    <span><?php echo esc($label) ?></span>
                    <span class="card-rating-value">&#9733; <?php echo $avg->$avg_field ?></span>
                </div>
                <?php } ?>
            </div>
            <?php } ?>
        </a>
        <?php }} ?>
    </section>
</main>
