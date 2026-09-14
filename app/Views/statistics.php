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
                <span>Intervalle</span>
                <select name="granularity">
                    <option value="day" <?php echo $granularity === 'day' ? 'selected' : '' ?>>1 jour</option>
                    <option value="week" <?php echo $granularity === 'week' ? 'selected' : '' ?>>1 semaine</option>
                </select>
            </label>
            <button type="submit" class="outline-btn-inverse">Filtrer</button>
        </form>
    </section>

    <?php if ($still_here + $left === 0) { ?>
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

    <script src="/chart.umd.min.js"></script>
    <script>
        const days = <?php echo json_encode($buckets) ?>;
        const troops = <?php echo json_encode($troops) ?>;
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
    </script>
    <?php } ?>
</main>
