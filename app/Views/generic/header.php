<header>
    <div class="header-top">
        <div class="ht-left">

        </div>
        <div class="ht-center">
            <img src="/pictures/logo_ck_website.png" alt="Logotype du Commando Kieffer 2004">
        </div>
        <div class="ht-right">
            <div class="profil-container">
                <div class="pc-left">
                    <p>Bonjour <?php echo session("user")['username']; ?></p>
                    <a href="/logout">Se déconnecter</a>
                </div>
                <div class="pc-right">
                    <img src="<?php echo session("user")['avatar_urls']['s']; ?>" alt="Image de profil forum">
                </div>
                <a href="#" class="mobile"><img src="/pictures/disconnect.png" alt=""></a>
            </div>
        </div>
    </div>
    <?php $current_user = session("user"); ?>
    <nav>
        <ul>
            <li><a href="/">Accueil</a></li>
            <li><a href="/profil">Mon profil</a></li>
            <li>
                <p id="operations-menu">Opérations</p>
            </li>
            <li><a href="/classements">Classements</a></li>
            <?php if (session("user")['is_staff'] || can_award_job_points($current_user)) { ?>
                <li>
                    <p id="points">Points</p>
                </li>
            <?php } ?>
            <?php if (is_team_leader($current_user)) { ?>
                <li>
                    <p id="medals-menu">Décorations</p>
                </li>
                <li><a href="/statistiques">Statistiques</a></li>
                <li><a href="/coffre">Coffre</a></li>
                <li>
                    <p id="upload-menu">Upload</p>
                </li>
            <?php } ?>
        </ul>
    </nav>
    <nav class="sub sub-operations">
        <ul>
            <li><a href="/operations">Liste</a></li>
            <?php if (can_create_operations($current_user)) { ?>
                <li><a href="/operations/create">Créer une opération</a></li>
            <?php } ?>
            <?php if (is_squad_or_team_leader($current_user)) { ?>
                <li><a href="/operations/report">Rapport de présence</a></li>
            <?php } ?>
        </ul>
    </nav>
    <?php if (session("user")['is_staff'] || can_award_job_points($current_user)) { ?>
    <nav class="sub sub-points">
        <ul>
            <?php if (can_award_job_points($current_user)) { ?>
            <li><a href="/points/work">Points pour métier</a></li>
            <?php } ?>
            <?php if (session("user")['is_staff']) { ?>
            <li><a href="/points/blame">Blâme</a></li>
            <li><a href="/points/warning">Avertissement</a></li>
            <li><a href="/points/correct_point">Correction de points</a></li>
            <?php } ?>
        </ul>
    </nav>
    <?php } ?>
    <?php if (is_team_leader($current_user)) { ?>
    <nav class="sub sub-medals">
        <ul>
            <li><a href="/medals/liste">Liste</a></li>
            <li><a href="/medals">Attribution</a></li>
        </ul>
    </nav>
    <nav class="sub sub-upload">
        <ul>
            <li><a href="/upload/galerie">Upload galerie</a></li>
            <li><a href="#">Upload carte</a></li>
        </ul>
    </nav>
    <?php } ?>
</header>

<script>
    function toggleSubMenu(triggerId, subMenuSelector) {
        const trigger = document.getElementById(triggerId);
        if (!trigger) return;

        trigger.addEventListener("click", function() {
            const subMenu = document.querySelector(subMenuSelector);
            const wasOpen = subMenu.classList.contains("open");

            document.querySelectorAll(".sub").forEach(function(el) {
                el.classList.remove("open");
            });

            if (!wasOpen) subMenu.classList.add("open");
        });
    }

    toggleSubMenu("operations-menu", ".sub-operations");
    toggleSubMenu("points", ".sub-points");
    toggleSubMenu("medals-menu", ".sub-medals");
    toggleSubMenu("upload-menu", ".sub-upload");
</script>
