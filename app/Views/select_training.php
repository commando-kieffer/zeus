<main class="training">
    <form action="/points/modify_training" method="post" class="training-form">
        <div class="tf-head">
            <h1>Sélection d'un rapport de training</h1>
            <div class="tf-sub">
                <select name="training_id">
                    <?php foreach($trainings as $training) { ?>
                    <option value="<?php echo $training[0]->id ?>"><?php echo $training[0]->title ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="tf-foot">
            <button type="submit" class="outline-btn-inverse">RÉCUPÉRER LE RAPPORT</button>
        </div>
    </form>
</main>