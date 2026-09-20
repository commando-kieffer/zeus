<main class="medal-catalogue">
    <section class="medals-head">
        <h1>Décorations</h1>
        <p class="catalogue-intro">Les décorations du Commando et leurs conditions d'obtention.</p>
    </section>

    <section class="catalogue-grid">
        <?php foreach ($all_medals as $medal) { ?>
        <article class="catalogue-card">
            <div class="catalogue-visual">
                <img src="/pictures/medailles/<?php echo esc($medal->name) ?>.jpg"
                     alt="<?php echo esc($medal->title) ?>">
            </div>
            <h2><?php echo esc($medal->title) ?></h2>
            <p><?php echo esc($medal->description) ?></p>
        </article>
        <?php } ?>
    </section>
</main>
