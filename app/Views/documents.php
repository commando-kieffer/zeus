<?php
/**
 * Bibliothèque de documents.
 *
 * @var object[]    $documents  documents, déjà triés par titre
 * @var bool        $can_manage vrai pour l'état-major (ajout et modification)
 * @var string|null $success    message de confirmation après une action
 */
?>
<main class="documents">
    <section class="documents-head">
        <h1>Documents</h1>
        <p class="documents-intro">Livrets, guides et autres documents du Commando. Un clic ouvre le document dans un nouvel onglet.</p>
        <?php if ($can_manage) { ?>
        <a class="solid-btn" href="/documents/ajouter">Ajouter un document</a>
        <?php } ?>
    </section>

    <?php if (!empty($success)) { ?>
    <p class="documents-flash" role="status"><?php echo esc($success) ?></p>
    <?php } ?>

    <?php if (empty($documents)) { ?>
    <p class="documents-empty">Aucun document n'a encore été ajouté.</p>
    <?php } else { ?>
    <section class="documents-toolbar">
        <div class="toolbar-field toolbar-search">
            <label class="sr-only" for="documents-search">Rechercher un document</label>
            <input type="search" id="documents-search" placeholder="Rechercher un document…" autocomplete="off">
        </div>
        <div class="toolbar-field toolbar-sort">
            <label class="sr-only" for="documents-sort">Trier les documents</label>
            <select id="documents-sort">
                <option value="title">Titre (A → Z)</option>
                <option value="created">Ajoutés récemment</option>
                <option value="updated">Mis à jour récemment</option>
            </select>
        </div>
    </section>

    <section class="documents-grid" id="documents-grid">
        <?php foreach ($documents as $document) {
            $version = strtotime($document->updated_at . ' UTC');
        ?>
        <article class="document-card"
                 data-title="<?php echo esc($document->title, 'attr') ?>"
                 data-created="<?php echo esc($document->created_at, 'attr') ?>"
                 data-updated="<?php echo esc($document->updated_at, 'attr') ?>">
            <a class="document-link" href="/documents/<?php echo (int) $document->id ?>/fichier" target="_blank" rel="noopener">
                <div class="document-preview">
                    <img src="/documents/<?php echo (int) $document->id ?>/apercu?v=<?php echo (int) $version ?>"
                         alt="" loading="lazy" decoding="async">
                    <span class="document-type"><?php echo esc(document_file_label($document->file_name)) ?></span>
                </div>
                <div class="document-info">
                    <h2><?php echo esc($document->title) ?></h2>
                    <p class="document-size"><?php echo esc(document_format_size((int) $document->file_size)) ?></p>
                    <p class="document-date">Ajouté le <?php echo format_utc_date_fr($document->created_at) ?></p>
                    <p class="document-date">Mis à jour le <?php echo format_utc_date_fr($document->updated_at) ?></p>
                </div>
                <span class="sr-only">(s'ouvre dans un nouvel onglet)</span>
            </a>
            <?php if ($can_manage) { ?>
            <div class="document-actions">
                <a class="outline-btn-inverse" href="/documents/<?php echo (int) $document->id ?>/modifier">Modifier</a>
            </div>
            <?php } ?>
        </article>
        <?php } ?>
    </section>

    <p class="documents-empty" id="documents-no-result" role="status" hidden>Aucun document ne correspond à votre recherche.</p>

    <script>
        (function () {
            var grid = document.getElementById('documents-grid');
            var search = document.getElementById('documents-search');
            var sort = document.getElementById('documents-sort');
            var noResult = document.getElementById('documents-no-result');
            var cards = Array.prototype.slice.call(grid.children);

            // Recherche insensible à la casse et aux accents : « eclaireur »
            // doit retrouver « Livret éclaireur ».
            function normalize(text) {
                return text.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
            }

            cards.forEach(function (card) {
                card.dataset.search = normalize(card.dataset.title);
            });

            // Les dates sont des « AAAA-MM-JJ hh:mm:ss » : l'ordre alphabétique
            // est l'ordre chronologique. Les plus récentes d'abord.
            var comparators = {
                title: function (a, b) {
                    return a.dataset.title.localeCompare(b.dataset.title, 'fr', { sensitivity: 'base' });
                },
                created: function (a, b) {
                    return b.dataset.created.localeCompare(a.dataset.created);
                },
                updated: function (a, b) {
                    return b.dataset.updated.localeCompare(a.dataset.updated);
                }
            };

            function refresh() {
                var query = normalize(search.value.trim());
                var visible = 0;

                cards.sort(comparators[sort.value]).forEach(function (card) {
                    var matches = card.dataset.search.indexOf(query) !== -1;
                    card.hidden = !matches;
                    if (matches) visible++;
                    grid.appendChild(card);
                });

                noResult.hidden = visible !== 0;
            }

            search.addEventListener('input', refresh);
            sort.addEventListener('change', refresh);
        })();
    </script>
    <?php } ?>
</main>
