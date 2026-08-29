<main class="training">
    <?php if (empty($operations)) { ?>
    <div class="tf-head">
        <h1>Rapport de présence</h1>
        <p>Aucune opération n'est en attente d'un rapport pour votre troop.</p>
    </div>
    <?php } else { ?>
    <form action="#" class="training-form" id="select-operation-form">
        <div class="tf-head">
            <h1>Rapport de présence</h1>
            <div class="tf-sub">
                <select name="operation_id" id="operation_id">
                    <?php foreach ($operations as $op) { ?>
                    <option value="<?php echo $op->id ?>"><?php echo esc($op->name) . ' - ' . (new DateTime($op->date))->format('d/m/Y') ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="tf-foot">
            <button type="submit" class="outline-btn-inverse">CONTINUER</button>
        </div>
    </form>
    <?php } ?>
</main>

<?php if (!empty($operations)) { ?>
<script>
    document.getElementById('select-operation-form').addEventListener('submit', function(e) {
        e.preventDefault();
        var id = document.getElementById('operation_id').value;
        window.location.href = '/operations/report/' + id;
    });
</script>
<?php } ?>
