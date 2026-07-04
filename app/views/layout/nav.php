<nav class="nav" id="nav">
    <a href="/" class="nav-logo">Ornitho<span>Quiz</span></a>
    
    <!-- Menu burger CSS-only -->
    <input type="checkbox" id="burger-toggle" class="burger-toggle">
    <label for="burger-toggle" class="burger-btn" aria-label="Menu">
        <span></span>
        <span></span>
        <span></span>
    </label>
    
    <div class="nav-links">
        <?php if (getenv('APP_ENV') === 'dev'): ?>
            <button popovertarget="my-popover">Open Var dump</button>
            <div popover id="my-popover"><?php var_dump($_COOKIE); ?></div>
        <?php endif; ?>
        <a href="/">Accueil</a>
        <a href="/a-propos">À propos</a>
        <a href="#contact">Contact</a>
        
        <a href="/identifier">Identifier un oiseau</a>
        <?php if(!isset($_SESSION['utilisateur_id'])) : ?>
            <a href="/login"><i class="fa-solid fa-user"></i> Connexion</a>
        <?php else : ?>
            <a href="/profil"><i class="fa-solid fa-user"></i> Profil/Stats</a>
            <a href="/logout">Déconnexion</a>
        <?php endif ?>
    </div>
</nav>
<?php if ($_GET['error'] ?? null): ?>
    <div class=" error-landing text-center"><?= htmlspecialchars($_GET['error']) ?></div>
<?php elseif($_GET['success'] ?? null) :?>
    <div class=" success-landing text-center"><?= htmlspecialchars($_GET['success']) ?></div>
<?php endif ?>