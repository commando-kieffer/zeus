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
    <nav>
        <ul>
            <li><a href="/profil">Mon profil</a></li>
            <li><a href="/">Salle des cartes</a></li>
            <?php if (session("user")['is_staff']) { ?>
                <li>
                    <p id="points">Points</p>
                </li>
                <li><a href="#">Décorations</a></li>
                <li><a href="#">Upload galerie</a></li>
                <li><a href="#">Upload carte</a></li>
            <?php } ?>
        </ul>
    </nav>
    <?php if (session("user")['is_staff']) { ?>
    <nav class="sub">
        <ul>
            <li><a href="/points/training">Training</a></li>
            <li><a href="/points/work">Points pour métier</a></li>
            <li><a href="/points/blame">Blâme</a></li>
            <li><a href="/points/warning">Avertissement</a></li>
            <li><a href="/points/correct_point">Correction de points</a></li>
        </ul>
    </nav>
    <?php } ?>
</header>

<script>
    document.getElementById("points").addEventListener("click", function() {
        const subMenu = document.querySelector(".sub");

        if (subMenu.style.display === "none" || subMenu.style.display === "") {
            subMenu.style.display = "block";
        } else {
            subMenu.style.display = "none";
        }
    });
</script>