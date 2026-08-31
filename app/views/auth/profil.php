<?php
$pageCss = "login";
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/nav.php';

?>

<main>
    
    <div class="form-container-profil ">
        <?php if ($_GET['error'] ?? null): ?>
            <p class="error-message-php text-center mt-1"><?= htmlspecialchars($_GET['error']) ?></p>
        <?php endif ?>
        <?php if ($_GET['success'] ?? null): ?>
            <p class="success-message-php text-center mt-1"><?= htmlspecialchars($_GET['success']) ?></p>
        <?php endif ?>
        <p class="error-message mt-1 text-center"></p><br>
        <h1 class="text-center mt-5">Profil</h1>
        <div class="donnees-container">
            <h2 class="text-center mt-4">Données utilisatuer</h2>
            <p class="text-center"><strong>Pseudo</strong> : <?= htmlspecialchars($user['pseudo']) ?></p>
            <p class="text-center"><strong>Email</strong> : <?= htmlspecialchars($user['email']) ?></p>
            <a href="/modif-profil" class="btn-form-profil text-center">Modifier</a>
            <a href="/supprimer-profil" class="btn-form-profil text-center mt-3">Supprimer profil</a>
        </div>
        <?php if ($aDesStats): ?>
            <div class="stats-container mt-5">
                <h2 class="text-center">Stats quizs</h2>
                <p class="text-center">Nombre total : <?= $totalQuestions ?></p>
                <p class="text-center">Bonnes réponses : <?= $totalBonnesReponses ?></p>
                <p class="text-center">Pourcentage total : <?= $pourcentageGlobal ?> % de réussite</p>
            </div>
        <?php else: ?>
            <div class="stats-container mt-5">
                <h2 class="text-center">Stats quizs</h2>
                <p class="text-center">Aucune statistique pour l'instant. Jouez un quiz pour voir vos résultats !</p>
            </div>
        <?php endif; ?>
        <div class="conteneur-graphique mt-3">
            <canvas id="graphiqueQuiz" data-stats='<?= htmlspecialchars(json_encode($statsParJeu), ENT_QUOTES, "UTF-8") ?>'></canvas>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php 
$loadScriptJs = 'profil-chart.js';
require_once __DIR__ . '/../layout/importJs.php';
require_once __DIR__ . '/../layout/footer.php';
?>