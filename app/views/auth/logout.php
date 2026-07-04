<?php
$pageCss = "login";
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/nav.php';

?>

<main>
    <div class="form-container">
        <h1 class="text-center mt-5">Déconnexion</h1>
        <p class="text-center">Vous nous quittez déjà?</p>
        <p class="text-center">Voulez vous vraiment vous déconnecter ?</p>
        <form action="/logout" method="POST">
            <?= Auth::csrfField() ?>
            <button type="submit" class="mt-5 btn-form">Se déconnecter</button>
            <a href="/" class="mt-5 btn-form text-center">Annuler</a>
        </form>
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