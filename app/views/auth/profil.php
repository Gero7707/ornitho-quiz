<?php
$pageCss = "login";
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/nav.php';

?>

<main>
    <div class="form-container-profil ">
        <h1 class="text-center mt-5">Profil</h1>
        <div class="donnees-container">
            <h2 class="text-center mt-4">Données utilisatuer</h2>
            <p class="text-center"><strong>Pseudo</strong> : <?= htmlspecialchars($user['pseudo']) ?></p>
            <p class="text-center"><strong>Email</strong> : <?= htmlspecialchars($user['email']) ?></p>
            <a href="/modif-profil" class="btn-form-profil text-center">Modifier</a>
        </div>
        <div class="stats-container mt-5">
            <h2 class="text-center">Stats quizs</h2>
        </div>

        
        
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