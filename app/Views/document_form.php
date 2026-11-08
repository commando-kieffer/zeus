<?php
/**
 * Formulaire d'ajout et de modification d'un document.
 *
 * @var object|null         $document           document modifié, null à l'ajout
 * @var array<string,string> $errors            erreurs par champ (flashdata)
 * @var array<string,string> $old               valeurs saisies avant une erreur (flashdata)
 * @var int                 $max_document_bytes taille maximale du document
 * @var int                 $max_preview_bytes  taille maximale de l'aperçu
 * @var int                 $title_max_length
 */
$editing = $document !== null;
$action  = $editing ? '/documents/' . (int) $document->id . '/modifier' : '/documents/ajouter';
$title   = $old['title'] ?? ($editing ? $document->title : '');
?>
<main class="documents">
    <form action="<?php echo $action ?>" method="post" enctype="multipart/form-data" class="document-form" id="document-form">
        <?php echo csrf_field() ?>
        <h1><?php echo $editing ? 'Modifier le document' : 'Ajouter un document' ?></h1>

        <?php if (!empty($errors)) { ?>
        <ul class="form-errors" role="alert">
            <?php foreach ($errors as $error) { ?><li><?php echo esc($error) ?></li><?php } ?>
        </ul>
        <?php } ?>

        <div class="form-field">
            <label for="document-title">Titre</label>
            <input type="text" id="document-title" name="title" required maxlength="<?php echo (int) $title_max_length ?>"
                   value="<?php echo esc($title, 'attr') ?>">
        </div>

        <div class="form-field">
            <label for="document-preview">Image d'aperçu</label>
            <?php if ($editing) { ?>
            <img class="form-current-preview" id="preview-current"
                 src="/documents/<?php echo (int) $document->id ?>/apercu?v=<?php echo (int) strtotime($document->updated_at . ' UTC') ?>"
                 alt="Aperçu actuel">
            <?php } else { ?>
            <img class="form-current-preview" id="preview-current" alt="Aperçu choisi" hidden>
            <?php } ?>
            <input type="file" id="document-preview" name="preview" accept="image/png,image/jpeg,image/webp"
                   data-max-bytes="<?php echo (int) $max_preview_bytes ?>" <?php echo $editing ? '' : 'required' ?>>
            <p class="form-hint">
                PNG, JPEG ou WebP, <?php echo esc(document_format_size($max_preview_bytes)) ?> maximum. Un format portrait (3:4) est conseillé.
                <?php if ($editing) { ?>Laissez vide pour conserver l'image actuelle.<?php } ?>
            </p>
        </div>

        <div class="form-field">
            <label for="document-file">Document</label>
            <?php if ($editing) { ?>
            <p class="form-current-file">
                Fichier actuel :
                <a href="/documents/<?php echo (int) $document->id ?>/fichier" target="_blank" rel="noopener"><?php echo esc($document->file_name) ?></a>
                (<?php echo esc(document_format_size((int) $document->file_size)) ?>)
            </p>
            <?php } ?>
            <input type="file" id="document-file" name="document"
                   data-max-bytes="<?php echo (int) $max_document_bytes ?>" <?php echo $editing ? '' : 'required' ?>>
            <p class="form-hint">
                Tout type de fichier (PDF, image, archive…), <?php echo esc(document_format_size($max_document_bytes)) ?> maximum.
                <?php if ($editing) { ?>Laissez vide pour conserver le fichier actuel.<?php } ?>
            </p>
        </div>

        <div class="form-actions">
            <button type="submit" class="solid-btn">Enregistrer</button>
            <a class="outline-btn-inverse" href="/documents">Annuler</a>
        </div>
    </form>

    <?php if ($editing) { ?>
    <form action="/documents/<?php echo (int) $document->id ?>/supprimer" method="post" class="document-delete" id="document-delete"
          data-confirm="<?php echo esc(sprintf("Supprimer définitivement « %s » ? Cette action est irréversible.", $document->title), 'attr') ?>">
        <?php echo csrf_field() ?>
        <h2>Supprimer ce document</h2>
        <p>Le document et son aperçu seront définitivement supprimés.</p>
        <button type="submit" class="danger-btn">Supprimer</button>
    </form>
    <?php } ?>

    <script>
        (function () {
            // Fichier trop gros : on le signale tout de suite plutôt qu'après
            // un envoi qui peut être long, et que le serveur rejetterait.
            function formatSize(bytes) {
                if (bytes >= 1048576) return (Math.round(bytes / 104857.6) / 10).toString().replace('.', ',') + ' Mo';
                return Math.max(1, Math.round(bytes / 1024)) + ' Ko';
            }

            document.querySelectorAll('input[type="file"][data-max-bytes]').forEach(function (input) {
                input.addEventListener('change', function () {
                    var file = input.files[0];
                    var max = parseInt(input.dataset.maxBytes, 10);

                    input.setCustomValidity(file && max > 0 && file.size > max
                        ? 'Ce fichier pèse ' + formatSize(file.size) + ' : la limite est de ' + formatSize(max) + '.'
                        : '');

                    if (!input.validity.valid) input.reportValidity();
                });
            });

            // Aperçu de l'image choisie.
            var previewInput = document.getElementById('document-preview');
            var previewImage = document.getElementById('preview-current');
            var originalSrc = previewImage.getAttribute('src');
            var objectUrl = null;

            previewInput.addEventListener('change', function () {
                if (objectUrl) URL.revokeObjectURL(objectUrl);
                objectUrl = null;

                var file = previewInput.files[0];
                if (file && previewInput.validity.valid) {
                    objectUrl = URL.createObjectURL(file);
                    previewImage.src = objectUrl;
                    previewImage.hidden = false;
                } else if (originalSrc) {
                    previewImage.src = originalSrc;
                } else {
                    previewImage.removeAttribute('src');
                    previewImage.hidden = true;
                }
            });

            var deleteForm = document.getElementById('document-delete');
            if (deleteForm) {
                deleteForm.addEventListener('submit', function (event) {
                    if (!window.confirm(deleteForm.dataset.confirm)) event.preventDefault();
                });
            }
        })();
    </script>
</main>
