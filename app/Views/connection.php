<main class="connection">
    <div class="login-form">
        <div class="head-form">
            <div class="left-form">
                <img src="/pictures/logo_ck_website.png" alt="Logotype du Commando Kieffer 2004">
            </div>
            <form action="/login" method="post" class="right-form">
                <img src="/pictures/zeus.png" alt="Logotype du Commando Kieffer 2004" class="zeus">
                <div class="input nickname-input">
                    <label for="nickname">Nom de commando</label>
                    <input type="text" name="nickname" placeholder="Votre nom de commando (ex. : Le Floch)">
                </div>
                <div class="input password-input">
                    <label for="password">Mot de passe</label>
                    <input type="password" name="password" placeholder="Votre mot de passe">
                </div>
                <button type="submit" class="outline-btn">SE CONNECTER</button>
            </form>
        </div>
        <div class="foot-form">
            Site protégé par les articles L.111-1 et L.123-1 du code de la propriété intellectuelle. © 2004 - <?php echo date("Y"); ?> Commando Kieffer.
        </div>
    </div>
</main>