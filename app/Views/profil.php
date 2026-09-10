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
            <h3>Taux de présence</h3>
            <p><?php echo $profil['stats']->panel_prs + $profil['stats']->panel_abs == 0 ? "0%" : ceil(100 * $profil['stats']->panel_prs / ($profil['stats']->panel_abs + $profil['stats']->panel_prs)) . "%" ?></p>
        </div>
        <div class="ps-data">
            <h3>Date d'entrée</h3>
            <p><?php echo $profil['joined_at'] !== null ? (new DateTime($profil['joined_at']))->format('d/m/Y') : 'Inconnue' ?></p>
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
            <a class="outline-btn-inverse" href="<?php echo $profil_base_url ?>?page=<?php echo $history_page - 1 ?>">&larr; Plus récent</a>
            <?php } ?>
            <span>Page <?php echo $history_page + 1 ?> / <?php echo $history_page_count ?></span>
            <?php if ($history_page < $history_page_count - 1) { ?>
            <a class="outline-btn-inverse" href="<?php echo $profil_base_url ?>?page=<?php echo $history_page + 1 ?>">Plus ancien &rarr;</a>
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

