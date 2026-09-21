<main class="statistics">
    <section class="statistics-head">
        <h1>Statistiques</h1>
        <form method="get" class="statistics-filter">
            <label>
                <span>Du</span>
                <input type="date" name="start" value="<?php echo esc($start) ?>">
            </label>
            <label>
                <span>Au</span>
                <input type="date" name="end" value="<?php echo esc($end) ?>">
            </label>
            <label>
                <span>Intervalle (arrivées)</span>
                <select name="granularity">
                    <option value="day" <?php echo $granularity === 'day' ? 'selected' : '' ?>>1 jour</option>
                    <option value="week" <?php echo $granularity === 'week' ? 'selected' : '' ?>>1 semaine</option>
                    <option value="month" <?php echo $granularity === 'month' ? 'selected' : '' ?>>1 mois</option>
                </select>
            </label>
            <label>
                <span>Intervalle (présence)</span>
                <select name="presence_granularity">
                    <option value="week" <?php echo $presence_granularity === 'week' ? 'selected' : '' ?>>1 semaine</option>
                    <option value="month" <?php echo $presence_granularity === 'month' ? 'selected' : '' ?>>1 mois</option>
                </select>
            </label>
            <button type="submit" class="outline-btn-inverse">Filtrer</button>
        </form>
    </section>

    <?php $has_joiners = ($still_here + $left) > 0; ?>

    <?php if (!$has_joiners) { ?>
    <p class="empty-state">Aucune arrivée enregistrée sur cette période.</p>
    <?php } else { ?>

    <section class="statistics-chart-card statistics-chart-wide">
        <h2>Nouveaux arrivants</h2>
        <div class="chart-holder chart-holder-line">
            <canvas id="chart-joiners"></canvas>
        </div>
    </section>

    <section class="statistics-pies">
        <div class="statistics-chart-card">
            <h2>Répartition par troop</h2>
            <div class="chart-holder chart-holder-pie">
                <canvas id="chart-troops"></canvas>
            </div>
        </div>
        <div class="statistics-chart-card">
            <h2>Toujours présents / Partis</h2>
            <div class="chart-holder chart-holder-pie">
                <canvas id="chart-retention"></canvas>
            </div>
        </div>
    </section>

    <?php } ?>

    <?php if (!$has_presence) { ?>
    <p class="empty-state">Aucune présence enregistrée sur cette période.</p>
    <?php } else { ?>

    <section class="statistics-chart-card statistics-chart-wide">
        <h2>Taux de présence</h2>
        <div class="chart-holder chart-holder-line">
            <canvas id="chart-presence"></canvas>
        </div>
    </section>

    <?php } ?>

    <!-- Hors période : il s'agit de l'effectif d'aujourd'hui, pas d'un flux
         d'arrivées. Ce graphique reste donc affiché même quand le filtre ne
         retourne aucune arrivée. -->
    <section class="statistics-pies">
        <div class="statistics-chart-card">
            <h2>Plateformes de jeu<span class="statistics-subtitle">effectif actuel, toutes périodes confondues</span></h2>
            <div class="chart-holder chart-holder-pie">
                <canvas id="chart-platforms"></canvas>
            </div>
        </div>
    </section>

    <script src="/chart.umd.min.js"></script>
    <script>
        const hasJoiners = <?php echo json_encode($has_joiners) ?>;
        const hasPresence = <?php echo json_encode($has_presence) ?>;
        const troops = <?php echo json_encode($troops) ?>;
        const platforms = <?php echo json_encode($platforms) ?>;
        const platformColors = <?php echo json_encode($platform_colors) ?>;

        if (hasJoiners) {
            const days = <?php echo json_encode($buckets) ?>;
            const seriesByTroop = <?php echo json_encode($series_by_troop) ?>;
            const seriesTotal = <?php echo json_encode($series_total) ?>;
            const pieTroops = <?php echo json_encode($pie_troops) ?>;
            const stillHere = <?php echo (int) $still_here ?>;
            const leftCount = <?php echo (int) $left ?>;

            const labels = days.map(function (d) {
                const parts = d.split('-');
                return parts[2] + '/' + parts[1];
            });

            const joinerDatasets = [];
            for (const troopId in troops) {
                joinerDatasets.push({
                    label: troops[troopId].title,
                    data: days.map(function (d) { return seriesByTroop[troopId][d]; }),
                    borderColor: troops[troopId].color,
                    backgroundColor: troops[troopId].color,
                    tension: 0.25,
                    pointRadius: 2,
                });
            }
            joinerDatasets.push({
                label: 'Total',
                data: days.map(function (d) { return seriesTotal[d]; }),
                borderColor: '#0F0E33',
                backgroundColor: '#0F0E33',
                borderDash: [6, 4],
                tension: 0.25,
                pointRadius: 2,
            });

            new Chart(document.getElementById('chart-joiners'), {
                type: 'line',
                data: { labels: labels, datasets: joinerDatasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                },
            });

            const troopPieLabels = [];
            const troopPieData = [];
            const troopPieColors = [];
            for (const troopId in troops) {
                if (pieTroops[troopId] > 0) {
                    troopPieLabels.push(troops[troopId].title);
                    troopPieData.push(pieTroops[troopId]);
                    troopPieColors.push(troops[troopId].color);
                }
            }

            new Chart(document.getElementById('chart-troops'), {
                type: 'pie',
                data: {
                    labels: troopPieLabels,
                    datasets: [{ data: troopPieData, backgroundColor: troopPieColors }],
                },
                options: { responsive: true, maintainAspectRatio: false },
            });

            new Chart(document.getElementById('chart-retention'), {
                type: 'pie',
                data: {
                    labels: ['Toujours présents', 'Partis'],
                    datasets: [{
                        data: [stillHere, leftCount],
                        backgroundColor: ['rgb(97, 189, 109)', 'rgb(209, 72, 65)'],
                    }],
                },
                options: { responsive: true, maintainAspectRatio: false },
            });
        }

        if (hasPresence) {
            const presenceGranularity = <?php echo json_encode($presence_granularity) ?>;
            const presenceBuckets = <?php echo json_encode($presence_buckets) ?>;
            const presenceRateByTroop = <?php echo json_encode($presence_rate_by_troop) ?>;
            const presenceRateTotal = <?php echo json_encode($presence_rate_total) ?>;

            const presenceLabels = presenceBuckets.map(function (d) {
                const parts = d.split('-');
                return presenceGranularity === 'month' ? (parts[1] + '/' + parts[0]) : (parts[2] + '/' + parts[1]);
            });

            const presenceDatasets = [];
            for (const troopId in troops) {
                presenceDatasets.push({
                    label: troops[troopId].title,
                    data: presenceBuckets.map(function (d) { return presenceRateByTroop[troopId][d]; }),
                    borderColor: troops[troopId].color,
                    backgroundColor: troops[troopId].color,
                    tension: 0.25,
                    pointRadius: 2,
                    spanGaps: false,
                });
            }
            presenceDatasets.push({
                label: 'Total',
                data: presenceBuckets.map(function (d) { return presenceRateTotal[d]; }),
                borderColor: '#0F0E33',
                backgroundColor: '#0F0E33',
                borderDash: [6, 4],
                tension: 0.25,
                pointRadius: 2,
                spanGaps: false,
            });

            new Chart(document.getElementById('chart-presence'), {
                type: 'line',
                data: { labels: presenceLabels, datasets: presenceDatasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: { callback: function (value) { return value + '%'; } },
                        },
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    return ctx.dataset.label + ': ' + (ctx.parsed.y === null ? 'aucune donnée' : ctx.parsed.y + '%');
                                },
                            },
                        },
                    },
                },
            });
        }

        // Une plateforme sans aucun membre est retirée du camembert : une part
        // de taille nulle n'apporte rien et encombre la légende.
        const platformLabels = [];
        const platformData = [];
        const platformPieColors = [];
        for (const name in platforms) {
            if (platforms[name] > 0) {
                platformLabels.push(name);
                platformData.push(platforms[name]);
                platformPieColors.push(platformColors[name]);
            }
        }

        new Chart(document.getElementById('chart-platforms'), {
            type: 'pie',
            data: {
                labels: platformLabels,
                datasets: [{ data: platformData, backgroundColor: platformPieColors }],
            },
            options: { responsive: true, maintainAspectRatio: false },
        });
    </script>
</main>
