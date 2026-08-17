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

                <div class="d-flex password justify-content-center">
                    <label class="form-label" class="mt-3" for="password">Mot de passe</label><br>
                    <button type="button" class="btn-password" data-target="password" aria-label="Voir le mot de passe"><i class="fa-regular fa-eye"></i></button><br>
                </div>
                <input class="form-control"  type="password" name="password" id="password" required><br>
                
                <div class="d-flex password justify-content-center">
                    <label class="form-label" class="mt-3" for="password_confirm">Confirmer le MDP</label><br>
                    <button type="button" class="btn-password" data-target="password_confirm" aria-label="Voir le mot de passe"><i class="fa-regular fa-eye"></i></button><br>
                </div>
                <input class="form-control"  type="password" name="password_confirm" id="password_confirm" required><br>

                <label class="form-label" class="mt-3 texte-check" for="rgpd"><input type="checkbox" name="rgpd" id="rgpd" required>J'accepte que mes données personnelles <br> soient collectées et traitées conformément à notre <br>
                    <a href="/mentions-legales">politique de confidentialité</a>
                </label><br>
                <a class="annuler" href="/">Annuler</a><br>
                <button class="mt-3 mb-3 btn-form" type="submit">Créer votre compte</button>
        </form>
        <p class="text-center mt-5">Pour vous connecter vous devez avoir un compte chez nous .</p>
        <a href="/creer-compte" class="btn-form text-center">Créer un compte</a>
        <?php if ($_GET['error'] ?? null): ?>
            <p class="error-message-php text-center mt-1"><?= htmlspecialchars($_GET['error']) ?></p>
        <?php endif ?>
        <?php if ($_GET['success'] ?? null): ?>
            <p class="success-message-php text-center mt-1"><?= htmlspecialchars($_GET['success']) ?></p>
        <?php endif ?>
        <p class="error-message mt-1 text-center"></p><br>
    </div>
</main>



<?php 
require_once __DIR__ . '/../layout/footer.php';
?>