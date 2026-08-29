<?php
    $errors = session()->getFlashdata('errors') ?? [];
    $old = session()->getFlashdata('old') ?? [];
?>
<main class="training">
    <form action="/upload/galerie" method="post" enctype="multipart/form-data" class="training-form">
        <div class="tf-head">
            <h1>Upload galerie</h1>
            <?php if (!empty($errors)) { ?>
            <ul class="form-errors">
                <?php foreach ($errors as $error) { ?><li><?php echo esc($error) ?></li><?php } ?>
            </ul>
            <?php } ?>
            <div class="tf-sub">
                <input type="file" name="picture" accept="image/png,image/jpeg" required>
                <select name="category" required>
                    <option value="">Sélectionner une catégorie</option>
                    <?php foreach ($categories as $category) { ?>
                    <option value="<?php echo esc($category->slug) ?>" <?php echo (($old['category'] ?? '') === $category->slug) ? 'selected' : '' ?>><?php echo esc($category->name) ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="tf-content">
            <textarea name="description" placeholder="Description de la photo..." rows="6"><?php echo esc($old['description'] ?? '') ?></textarea>
        </div>
        <div class="tf-foot">
            <button type="submit" class="outline-btn-inverse">ENVOYER</button>
        </div>
    </form>
</main>
