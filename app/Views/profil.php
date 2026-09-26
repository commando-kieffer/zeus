<main class="profil">
    <div class="profil-head">
        <img src="<?php echo $user['avatar_urls']['l']; ?>" alt="Image de profil forum">
        <div class="profil-info">
            <h1><?php echo $user['username']; ?></h1>
            <h2><?php echo $profil['grade']->title ?></h2>
            <div class="troop-bordee">
                <div class="badge badge<?php echo $profil['troop_bordee_spe']['troop']['troop_id'] ?>"><?php echo $profil['troop_bordee_spe']['troop']['troop_title']->title ?></div>
                <div class="badge"><?php echo $profil['troop_bordee_spe']['bordee']['bordee_title']->title ?></div>
                <div class="badge badgespe"><?php echo $profil['troop_bordee_spe']['spe']['spe_title']->title ?></div>

            </div>
            <?php if ($is_own_profile) { ?>
            <button type="button" class="outline-btn-inverse profil-edit-trigger" data-target="edit-info-modal">Modifier mes informations</button>
            <?php } ?>
        </div>
    </div>
    <div class="profil-stats">
        <div class="ps-data">
            <h3>Points</h3>
            <p><?php echo $profil['stats']->panel_pts ?></p>
        </div>
        <div class="ps-data">
            <h3>Présences</h3>
            <p><?php echo $profil['stats']->panel_prs ?></p>
        </div>
        <div class="ps-data">
            <h3>Absences</h3>
            <p><?php echo $profil['stats']->panel_abs ?></p>
        </div>
        <div class="ps-data">
            <h3>OPEX</h3>
            <p><?php echo $profil['stats']->panel_opex ?></p>
        </div>
        <div class="ps-data">
            <h3>Taux de présence</h3>
            <p><?php echo $profil['stats']->panel_prs + $profil['stats']->panel_abs == 0 ? "0%" : ceil(100 * $profil['stats']->panel_prs / ($profil['stats']->panel_abs + $profil['stats']->panel_prs)) . "%" ?></p>
        </div>
        <div class="ps-data">
            <h3>Date d'entrée</h3>
            <p><?php echo $profil['joined_at'] !== null ? (new DateTime($profil['joined_at']))->format('d/m/Y') : 'Inconnue' ?></p>
        </div>
        <div class="ps-data">
            <h3>Plateforme</h3>
            <p class="ps-platform">
                <?php if ($panel_user !== null) { ?>
                <img src="/pictures/icons/<?php echo esc($platform_icon, 'attr') ?>" alt="<?php echo esc($panel_user->platform) ?>">
                <?php echo esc($panel_user->platform) ?>
                <?php } else { ?>
                Inconnue
                <?php } ?>
            </p>
        </div>
    </div>
    <div class="profil-metier">
        <h3>Les Métiers</h3>
        <div class="pm-container">
            <?php foreach ($profil['metiers'] as $metier) { ?>

                <div class="badge badgemetier"><?php echo $metier['group_id'] !== NULL || $is_own_profile ?  $metier['metier_title']->title : "Pas de métier" ?></div>

            <?php } ?>
        </div>
    </div>
    <div class="profil-presence-histo" id="historique-presences">
        <h3>Historique des présences</h3>
        <form method="get" action="<?php echo esc($profil_base_url . '#historique-presences', 'attr') ?>" class="presence-filter">
            <label>
                <span>Du</span>
                <input type="date" name="presence_start" value="<?php echo esc($presence['start'], 'attr') ?>" required>
            </label>
            <label>
                <span>Au</span>
                <input type="date" name="presence_end" value="<?php echo esc($presence['end'], 'attr') ?>" required>
            </label>
            <label>
                <span>Intervalle</span>
                <select name="presence_granularity">
                    <option value="week" <?php echo $presence['granularity'] === 'week' ? 'selected' : '' ?>>1 semaine</option>
                    <option value="month" <?php echo $presence['granularity'] === 'month' ? 'selected' : '' ?>>1 mois</option>
                </select>
            </label>
            <?php if ($history_page > 0) { ?>
            <input type="hidden" name="page" value="<?php echo (int) $history_page ?>">
            <?php } ?>
            <button type="submit" class="outline-btn-inverse">Filtrer</button>
        </form>
        <?php if (!$presence['has_data']) { ?>
        <p class="presence-empty">Aucune présence enregistrée sur cette période.</p>
        <?php } else { ?>
        <div class="presence-chart">
            <canvas id="chart-member-presence" role="img" aria-label="Historique des présences du membre sur la période"></canvas>
        </div>
        <script src="/chart.umd.min.js"></script>
        <script>
            (function () {
                const points = <?php echo json_encode($presence['points']) ?>;
                const granularity = <?php echo json_encode($presence['granularity']) ?>;
                // Par semaine, chaque point est binaire (présent ou absent) ; par
                // mois, c'est un taux de présence entre 0 et 1.
                const isMonthly = granularity === 'month';

                const labels = points.map(function (p) {
                    const parts = p.bucket.split('-');
                    return granularity === 'month' ? (parts[1] + '/' + parts[0]) : (parts[2] + '/' + parts[1] + '/' + parts[0].slice(2));
                });

                // Titre de l'infobulle : la semaine commence à la date du point,
                // le mois est celui de la clé (son 1er jour).
                function tooltipTitle(bucket) {
                    const parts = bucket.split('-').map(Number);
                    const date = new Date(parts[0], parts[1] - 1, parts[2]);
                    if (granularity === 'month') {
                        return date.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });
                    }
                    return 'Semaine du ' + date.toLocaleDateString('fr-FR');
                }

                new Chart(document.getElementById('chart-member-presence'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Présence',
                            data: points.map(function (p) { return p.value; }),
                            borderColor: '#0F0E33',
                            backgroundColor: '#0F0E33',
                            borderWidth: 2,
                            // Par semaine : signal carré, comme sur un analyseur
                            // logique, le niveau de chaque intervalle est tenu jusqu'à
                            // la moitié de l'écart avec le suivant, puis change à la
                            // verticale. Par mois : droites entre les taux.
                            stepped: isMonthly ? false : 'middle',
                            tension: 0,
                            pointRadius: 2,
                            pointHoverRadius: 4,
                            // Un intervalle sans rapport (null) interrompt la ligne
                            // au lieu de passer pour une absence.
                            spanGaps: false,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'nearest', axis: 'x', intersect: false },
                        scales: {
                            y: {
                                // Marge sous 0 et au-dessus de 1 pour que les points
                                // ne soient pas collés aux bords du graphique.
                                min: isMonthly ? -0.1 : -0.25,
                                max: isMonthly ? 1.1 : 1.25,
                                afterBuildTicks: function (axis) {
                                    const values = isMonthly ? [0, 0.25, 0.5, 0.75, 1] : [0, 1];
                                    axis.ticks = values.map(function (value) { return { value: value }; });
                                },
                                ticks: {
                                    callback: function (value) {
                                        if (isMonthly) {
                                            return Math.round(value * 100) + '%';
                                        }
                                        return value === 1 ? 'Présent' : 'Absent';
                                    },
                                },
                            },
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    title: function (items) { return tooltipTitle(points[items[0].dataIndex].bucket); },
                                    label: function (ctx) {
                                        const point = points[ctx.dataIndex];
                                        const detail = point.present + (point.present > 1 ? ' présences' : ' présence')
                                            + ' sur ' + point.reported + (point.reported > 1 ? ' opérations' : ' opération');
                                        if (isMonthly) {
                                            return Math.round(point.value * 100) + '% (' + detail + ')';
                                        }
                                        const label = point.value === 1 ? 'Présent' : 'Absent';
                                        return point.reported > 1 ? label + ' (' + detail + ')' : label;
                                    },
                                },
                            },
                        },
                    },
                });
            })();
        </script>
        <?php } ?>
    </div>
    <div class="profil-points-histo">
        <h3>Historique des points</h3>
        <div class="pm-container">
            <?php if (empty($points_history)) {
                echo "Pas d'historique pour le moment.";
            } else { ?>
                <div class="table">
                    <div class="thead trow">
                        <div class="tdata">Date</div>
                        <div class="tdata">Catégorie</div>
                        <div class="tdata">Message</div>
                        <div class="tdata">Points</div>
                    </div>
                    <?php foreach ($points_history as $row) { ?>
                        <div class="tbody trow">
                            <div class="tdata"><?php echo $row["date"] ?></div>
                            <div class="tdata"><?php echo $row["title"] ?></div>
                            <div class="tdata"><?php echo $row["message"] ?></div>
                            <div class="tdata"><?php echo $row["points"] ?></div>
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>

        </div>
        <?php if ($history_page_count > 1) { ?>
        <div class="histo-pagination">
            <?php if ($history_page > 0) { ?>
            <a class="outline-btn-inverse" href="<?php echo esc($profil_base_url . '?' . http_build_query(['page' => $history_page - 1] + $presence['query']), 'attr') ?>">&larr; Plus récent</a>
            <?php } ?>
            <span>Page <?php echo $history_page + 1 ?> / <?php echo $history_page_count ?></span>
            <?php if ($history_page < $history_page_count - 1) { ?>
            <a class="outline-btn-inverse" href="<?php echo esc($profil_base_url . '?' . http_build_query(['page' => $history_page + 1] + $presence['query']), 'attr') ?>">Plus ancien &rarr;</a>
            <?php } ?>
        </div>
        <?php } ?>
    </div>
    <div class="profil-medaille">
        <h3>Les Médailles</h3>
        <div class="pm-container">
      <?php
        if (empty($profil['medailles'])) {
          echo "Pas encore de médailles";
        } else {
          foreach ($profil['medailles'] as $medaille) {
      ?>
                <div class="pm-sub">
                    <h4><?php echo $medaille[0]->title ?></h4>
                    <img src="/pictures/medailles/<?php echo $medaille[0]->name ?>.jpg" alt="#">
                    <?php if (!empty($medaille[0]->attribution_date)) { ?>
                    <span class="pm-date">Reçue le <?php echo (new DateTime($medaille[0]->attribution_date))->format('d/m/Y') ?></span>
                    <?php } ?>
                    <p><?php echo $medaille[0]->description ?></p>
                    <?php if ($medaille[0]->attribution_description !== null && trim($medaille[0]->attribution_description) !== '') { ?>
                    <p class="pm-attribution"><?php echo esc($medaille[0]->attribution_description) ?></p>
                    <?php } ?>
                </div>
            <?php } } ?>
        </div>
    </div>
</main>

<?php if ($is_own_profile) { ?>
<div class="info-modal" id="edit-info-modal">
    <div class="info-modal-content">
        <h2>Modifier mes informations</h2>
        <?php if (!empty($info_errors)) { ?>
        <ul class="form-errors">
            <?php foreach ($info_errors as $error) { ?><li><?php echo esc($error) ?></li><?php } ?>
        </ul>
        <?php } ?>
        <form action="/profil/update_info" method="post">
            <label class="info-field">
                <span>Pseudo in-game</span>
                <input type="text" name="platform_username" maxlength="32" required value="<?php echo esc($panel_user->platform_username ?? '') ?>">
            </label>
            <label class="info-field">
                <span>Plateforme</span>
                <select name="platform" required>
                    <?php foreach ($platforms as $platform) { ?>
                    <option value="<?php echo esc($platform) ?>" <?php echo ($panel_user->platform ?? 'PC') === $platform ? 'selected' : '' ?>><?php echo esc($platform) ?></option>
                    <?php } ?>
                </select>
            </label>
            <div class="info-modal-actions">
                <button type="button" class="outline-btn-inverse info-modal-cancel">Annuler</button>
                <button type="submit" class="solid-btn">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.querySelectorAll('.profil-edit-trigger').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var modal = document.getElementById(btn.dataset.target);
            if (modal) modal.style.display = 'flex';
        });
    });

    document.querySelectorAll('.info-modal-cancel').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var modal = btn.closest('.info-modal');
            if (modal) modal.style.display = 'none';
        });
    });

    document.querySelectorAll('.info-modal').forEach(function(modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) modal.style.display = 'none';
        });
    });

    <?php if (!empty($info_errors)) { ?>
    document.getElementById('edit-info-modal').style.display = 'flex';
    <?php } ?>
</script>
<?php } ?>

