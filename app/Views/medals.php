<main class="medals">
    <section class="medals-head">
        <h1>Décorations</h1>
    </section>
    <?php if (!empty($pending_awards)) { ?>
    <!-- Décorations méritées d'après les compteurs du panel (points, présences,
         ancienneté, messages) et pas encore attribuées. Purement indicatif :
         rien n'est attribué sans un clic. -->
    <section class="medals-pending">
        <h2>À attribuer<span class="pending-count"><?php echo count($pending_awards) ?></span></h2>
        <table>
            <tr>
                <th>Membre</th>
                <th>Grade</th>
                <th>Décoration</th>
                <th>Condition remplie</th>
                <th></th>
            </tr>
            <?php foreach ($pending_awards as $award) { ?>
            <tr>
                <td><?php echo esc($award['member']->username) ?></td>
                <td><img src="/pictures/jackets/<?php echo $award['member']->user_group_id ?>.png" alt=""></td>
                <td>
                    <div class="pending-medal">
                        <img src="/pictures/medailles/<?php echo esc($award['medal']->name) ?>.jpg"
                             title="<?php echo esc($award['medal']->description) ?>"
                             alt="<?php echo esc($award['medal']->title) ?>">
                        <span><?php echo esc($award['medal']->title) ?></span>
                    </div>
                </td>
                <td class="pending-reason"><?php echo esc($award['reason']) ?></td>
                <td>
                    <form action="/medals/toggle" method="post" class="pending-form">
                        <input type="hidden" name="member_id" value="<?php echo (int) $award['member']->user_id ?>">
                        <input type="hidden" name="medal_id" value="<?php echo (int) $award['medal']->id ?>">
                        <input type="hidden" name="medal_date" value="<?php echo esc($default_medal_date) ?>">
                        <button type="button" class="outline-btn-inverse pending-ask">Attribuer</button>
                        <span class="pending-confirm" hidden>
                            <button type="submit" class="outline-btn-inverse pending-yes" title="Confirmer l'attribution">&#10003;</button>
                            <button type="button" class="outline-btn-inverse pending-no" title="Annuler">&#10007;</button>
                        </span>
                    </form>
                </td>
            </tr>
            <?php } ?>
        </table>
    </section>
    <?php } ?>

    <section class="medals-list">
        <?php foreach ($members_by_troop as $troop) { if (!empty($troop['members'])) { ?>
        <div class="tfc-container">
            <div class="badge badge<?php echo $troop['id'] ?>"><?php echo $troop['title']; ?></div>
            <table>
                <tr>
                    <th>Nom</th>
                    <th>Grade</th>
                    <th>Médailles</th>
                    <th></th>
                </tr>
                <?php foreach ($troop['members'] as $member) {
                    $owned_ids = $medals_by_member[$member->user_id] ?? [];
                ?>
                <tr>
                    <td><?php echo $member->username ?></td>
                    <td><img src="/pictures/jackets/<?php echo $member->user_group_id ?>.png" alt=""></td>
                    <td>
                        <div class="medal-icons">
                            <?php foreach ($all_medals as $medal) { if (in_array($medal->id, $owned_ids)) { ?>
                            <img src="/pictures/medailles/<?php echo esc($medal->name) ?>.jpg" title="<?php echo esc($medal->title) ?>" alt="<?php echo esc($medal->title) ?>">
                            <?php }} ?>
                        </div>
                    </td>
                    <td>
                        <button type="button" class="outline-btn-inverse medal-modal-trigger" data-target="medal-modal-<?php echo $member->user_id ?>">Gérer</button>
                    </td>
                </tr>
                <?php } ?>
            </table>
        </div>
        <?php }} ?>
    </section>

    <?php foreach ($members_by_troop as $troop) { foreach ($troop['members'] as $member) {
        $owned_ids = $medals_by_member[$member->user_id] ?? [];
    ?>
    <div class="medal-modal" id="medal-modal-<?php echo $member->user_id ?>">
        <div class="medal-modal-content">
            <h2><?php echo esc($member->username) ?></h2>
            <form action="/medals/toggle" method="post">
                <input type="hidden" name="member_id" value="<?php echo $member->user_id ?>">

                <h3>Ajouter une médaille</h3>
                <input type="text" class="medal-filter" placeholder="Filtrer...">
                <ul class="medal-options">
                    <?php foreach ($all_medals as $medal) { if (!in_array($medal->id, $owned_ids)) { ?>
                    <li class="medal-option" data-label="<?php echo esc(mb_strtolower($medal->title)) ?>">
                        <label>
                            <input type="radio" name="medal_id" value="<?php echo $medal->id ?>" required>
                            <?php echo esc($medal->title) ?>
                        </label>
                    </li>
                    <?php }} ?>
                </ul>
                <label class="medal-field">
                    <span>Date d'attribution</span>
                    <input type="date" name="medal_date" value="<?php echo esc($default_medal_date) ?>">
                </label>
                <label class="medal-field">
                    <span>Description</span>
                    <textarea name="medal_description" rows="3" placeholder="Laisser vide pour utiliser la description par défaut de la médaille"></textarea>
                </label>

                <h3>Retirer une médaille</h3>
                <ul class="medal-options">
                    <?php if (empty($owned_ids)) { ?>
                    <li class="medal-option-empty">Aucune médaille.</li>
                    <?php }
                    foreach ($all_medals as $medal) { if (in_array($medal->id, $owned_ids)) { ?>
                    <li class="medal-option">
                        <label>
                            <input type="radio" name="medal_id" value="<?php echo $medal->id ?>" required>
                            <?php echo esc($medal->title) ?>
                        </label>
                    </li>
                    <?php }} ?>
                </ul>

                <div class="medal-modal-actions">
                    <button type="button" class="outline-btn-inverse medal-modal-cancel">Annuler</button>
                    <button type="submit" class="solid-btn">Valider</button>
                </div>
            </form>
        </div>
    </div>
    <?php }} ?>
</main>

<script>
    // Une attribution ne se défait qu'en rouvrant la fiche du membre : le
    // bouton demande donc confirmation plutôt que d'agir au premier clic.
    document.querySelectorAll('.pending-form').forEach(function (form) {
        const ask = form.querySelector('.pending-ask');
        const confirm = form.querySelector('.pending-confirm');

        ask.addEventListener('click', function () {
            // Une seule confirmation ouverte à la fois : sinon deux lignes
            // restent armées et le clic suivant porte sur la mauvaise.
            document.querySelectorAll('.pending-form').forEach(function (other) {
                other.querySelector('.pending-ask').hidden = false;
                other.querySelector('.pending-confirm').hidden = true;
            });

            ask.hidden = true;
            confirm.hidden = false;
        });

        form.querySelector('.pending-no').addEventListener('click', function () {
            confirm.hidden = true;
            ask.hidden = false;
        });
    });

    document.querySelectorAll('.medal-modal-trigger').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var modal = document.getElementById(btn.dataset.target);
            if (modal) modal.style.display = 'flex';
        });
    });

    document.querySelectorAll('.medal-modal-cancel').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var modal = btn.closest('.medal-modal');
            if (modal) modal.style.display = 'none';
        });
    });

    document.querySelectorAll('.medal-modal').forEach(function(modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) modal.style.display = 'none';
        });
    });

    document.querySelectorAll('.medal-filter').forEach(function(input) {
        input.addEventListener('keyup', function() {
            var query = input.value.trim().toLowerCase();
            var list = input.nextElementSibling;
            list.querySelectorAll('.medal-option').forEach(function(li) {
                li.style.display = li.dataset.label.includes(query) ? '' : 'none';
            });
        });
    });
</script>
