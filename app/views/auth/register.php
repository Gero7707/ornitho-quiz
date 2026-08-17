<?php
$pageCss = "login";
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/nav.php';

?>

<main>
    <div class="form-container">
        <h1 class="text-center mt-5">Connexion</h1>
        <form action="/register" method="POST">
            <?= Auth::csrfField() ?>
            <label class="form-label" for="email">Email</label><br>
                <input class="form-control" type="email" name="email" id="email" required><br>

                <label class="form-label" class="mt-3" for="pseudo">Pseudo</label><br>
                <input class="form-control"  type="text" name="pseudo" id="pseudo" required><br>

                <div class="d-flex password flex-column">
                    <label class="form-label mt-3" for="password">Mot de passe</label>
                    <button type="button" class="btn-password m-auto hover-btn" data-target="password" aria-label="Voir le mot de passe" data-tooltip="Voir le mot de passe"><i class="fa-regular fa-eye"></i></button>
                </div>
                <input class="form-control"  type="password" name="password" id="password" required><br>
                
                <div class="d-flex password flex-column">
                    <label class="form-label" class="mt-3" for="password_confirm">Confirmer le MDP</label>
                    <button type="button" class="btn-password m-auto hover-btn" data-target="password_confirm" aria-label="Voir le mot de passe" data-tooltip="Voir le mot de passe"><i class="fa-regular fa-eye" ></i></button>
                </div>
                <input class="form-control"  type="password" name="password_confirm" id="password_confirm" required><br>

                <label class="form-label" class="mt-3 texte-check form-check-label" for="rgpd"><input class="form-check-input check" type="checkbox" name="rgpd" id="rgpd" required>J'accepte que mes données personnelles <br> soient collectées et traitées conformément à notre <br>
                    <a href="/mentions-legales">politique de confidentialité</a>
                </label><br>
                <a class="annuler mt-3 text-center" href="/">Annuler</a><br>
                <button class="mt-3 mb-3 btn-form" type="submit">Créer votre compte</button>
        </form>
        
        
        <p class="error-message mt-1 text-center"></p><br>
    </div>
</main>



<?php 
$loadScriptJs = 'form.js';
require_once __DIR__ . '/../layout/importJs.php';
require_once __DIR__ . '/../layout/footer.php';
?>