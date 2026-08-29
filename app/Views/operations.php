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
        </a>
        <?php }} ?>
    </section>
</main>
