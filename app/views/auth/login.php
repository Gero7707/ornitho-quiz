<?php
$pageCss = "login";
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/nav.php';

?>

<main>
    <div class="form-container">
        <h1 class="text-center mt-5">Connexion</h1>
        <form action="/login" method="POST">
            <?= Auth::csrfField() ?>
            <label class="form-label" for="login">Email :</label>
            <input class="form-control mb-3" type="text" name="login" id="login">
            <label for="password" class="form-label">Mot de passe : </label>
            <input type="password" class="form-control mb-3" name="password" id="password">
            <button type="submit" class="mt-5 btn-form">Se connecter</button>
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