<?php
$pageCss = "login";
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/nav.php';

?>
<main>
    <div class="form-container">
        <h1 class="text-center mt-5">Supprimer votre profil</h1>
        <p class="text-center">Êtes-vous sûr de vouloir supprimer votre profil?</p>
        <p class="text-center">Vous ne pourrez plus accéder à vos statistiques.</p>
        <form action="/supprimer-profil" method="POST" x-data="{ password: '', email: '' }">
            <?= Auth::csrfField() ?>
            <label class="form-label" for="email">Email :</label>
            <input class="form-control mb-3" type="text" name="email" id="email" x-model="email">
            <div class="d-flex password flex-column">
                <label for="password" class="form-label">Mot de passe : </label>
                <button type="button" class="btn-password m-auto hover-btn" data-target="password" aria-label="Voir le mot de passe" data-tooltip="Voir le mot de passe"><i class="fa-regular fa-eye"></i></button>
            </div>
            <input type="password" class="form-control mb-3" name="password" id="password" x-model="password">
            <div class="modal-validation">
                <button  class="btn-form" type="button" id="btnValiderSuppression" popovertarget="my-popover" :disabled="password === '' || email === ''">Supprimer votre profil</button>
                <div class="popover-modal supprimer-profil-modal p-3" popover id="my-popover">
                    
                    <p>Vous êtes sur le point de supprimer votre comte.</p>
                    <p> Vous ne pourrez plus accéder à votre compte ni consulter les statistiques de vos sessions de quiz.</p>
                    <p>Nous sommes désolé de vous voir partir. A bientôt peut-être.</p>
                    <p>L'équipe d'OrnithoQuiz vous souhaite une bonne Journée.</p>
                    <button class="btn-form btn-supprimer-profil"  type="submit">Supprimer compte</button>
                </div>
                
            </div>
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
$loadScriptJs = 'form.js';
require_once __DIR__ . '/../layout/importJs.php';
require_once __DIR__ . '/../layout/footer.php';
?>