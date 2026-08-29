<?php
    $errors = session()->getFlashdata('errors') ?? [];
    $old = session()->getFlashdata('old') ?? [];
?>
<main class="training">
    <form action="/operations/create" method="post" class="training-form">
        <div class="tf-head">
            <h1>Créer une opération</h1>
            <?php if (!empty($errors)) { ?>
            <ul class="form-errors">
                <?php foreach ($errors as $error) { ?><li><?php echo esc($error) ?></li><?php } ?>
            </ul>
            <?php } ?>
            <div class="tf-sub">
                <input type="text" name="name" value="<?php echo esc($old['name'] ?? '') ?>" placeholder="Nom de l'opération (ex. : Opération Overlord)" required>
                <input type="date" name="date" value="<?php echo esc($old['date'] ?? '') ?>" required>
                <input type="text" name="location" value="<?php echo esc($old['location'] ?? '') ?>" placeholder="Nom de la carte / du lieu" required>
                <select name="scenarist_id" required>
                    <option value="" disabled <?php echo empty($old['scenarist_id']) ? 'selected' : '' ?>>Scénariste</option>
                    <?php foreach ($members as $member) { ?>
                    <option value="<?php echo $member->user_id ?>" <?php echo (($old['scenarist_id'] ?? '') == $member->user_id) ? 'selected' : '' ?>><?php echo esc($member->username) ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="tf-content">
            <textarea name="description" placeholder="Description du scénario..." rows="8"><?php echo esc($old['description'] ?? '') ?></textarea>
        </div>
        <div class="tf-foot">
            <button type="submit" class="outline-btn-inverse">CRÉER L'OPÉRATION</button>
        </div>
    </form>
</main>
