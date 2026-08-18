<?php
$pageCss = "login";
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/nav.php';

?>

<main>
    <div class="form-container">
        <h1 class="text-center mt-5">Connexion</h1>
        <form action="/reset-password" method="POST">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
            
            <div class="d-flex password flex-column">
                <label class="form-label mt-3" for="password">Nouveau mot de passe</label>
                <button type="button" class="btn-password m-auto hover-btn" data-target="password" aria-label="Voir le mot de passe" data-tooltip="Voir le mot de passe"><i class="fa-regular fa-eye"></i></button>
            </div>
            <input class="form-control"  type="password" name="password" id="password" required><br>
                
            <div class="d-flex password flex-column">
                <label class="form-label" class="mt-3" for="password_confirm">Confirmer le nouveau mot de passe</label>
                <button type="button" class="btn-password m-auto hover-btn" data-target="password_confirm" aria-label="Voir le mot de passe" data-tooltip="Voir le mot de passe"><i class="fa-regular fa-eye" ></i></button>
            </div>
            <input class="form-control"  type="password" name="password_confirm" id="password_confirm" required><br>

            <a class="annuler mt-3 text-center" href="/">Annuler</a><br>
            <button class="mt-3 mb-3 btn-form" type="submit">Changer mot de passe</button>
        </form>
        
        
        <p class="error-message mt-1 text-center"></p><br>
    </div>
</main>



<?php 
$loadScriptJs = 'form.js';
require_once __DIR__ . '/../layout/importJs.php';
require_once __DIR__ . '/../layout/footer.php';
?>