<?php
require_once __DIR__ . '/layout/header.php'; 
?>
<!-- ================================================
        NAVIGATION
================================================ -->
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
            <div popover id="my-popover"><?php var_dump($_SESSION); ?></div>
        <?php endif; ?>

        <a href="/login"><i class="fa-solid fa-user"></i> Compte</a>
        <a href="/a-propos">À propos</a>
        <a href="#contact">Contact</a>
        <a href="/identifier">Identifier un oiseau</a>
    </div>
</nav>

<!-- ================================================
        HERO
================================================ -->
<section class="hero">

    <div class="hero-img" role="img" aria-label="Sous-bois au lever du soleil"></div>
    <div class="hero-overlay-top"></div>
    <div class="hero-overlay-tint"></div>

    <!-- Silhouettes d'oiseaux animées -->
    <div class="hero-birds" aria-hidden="true">
        <svg class="bird" width="18" height="10" viewBox="0 0 18 10" fill="none">
            <path d="M0 5 Q4 0 9 3 Q14 0 18 5" stroke="rgba(232,201,122,0.7)" stroke-width="1.2" fill="none"/>
        </svg>
        <svg class="bird" width="14" height="8" viewBox="0 0 14 8" fill="none">
            <path d="M0 4 Q3.5 0 7 2.5 Q10.5 0 14 4" stroke="rgba(168,197,176,0.6)" stroke-width="1" fill="none"/>
        </svg>
        <svg class="bird" width="22" height="12" viewBox="0 0 22 12" fill="none">
            <path d="M0 6 Q5 0 11 4 Q17 0 22 6" stroke="rgba(232,201,122,0.5)" stroke-width="1.4" fill="none"/>
        </svg>
        <svg class="bird" width="12" height="7" viewBox="0 0 12 7" fill="none">
            <path d="M0 3.5 Q3 0 6 2 Q9 0 12 3.5" stroke="rgba(168,197,176,0.5)" stroke-width="1" fill="none"/>
        </svg>
        <svg class="bird" width="16" height="9" viewBox="0 0 16 9" fill="none">
            <path d="M0 4.5 Q4 0 8 3 Q12 0 16 4.5" stroke="rgba(232,201,122,0.4)" stroke-width="1.1" fill="none"/>
        </svg>
    </div>

    <!-- Waveform décorative -->
    <div class="waveform" id="waveform" aria-hidden="true"></div>

    <!-- Contenu hero -->
    <p class="hero-eyebrow fade-up">Apprendre en écoutant</p>

    <h1 class="hero-title fade-up delay-1">
        Reconnais<br>
        <em>les oiseaux</em>
    </h1>

    <p class="hero-sub fade-up delay-2">
        Entraîne ton oreille aux chants et cris de 360 espèces.
        Quiz progressif, de débutant à expert.
    </p>

    <div class="hero-actions fade-up delay-3">
        <a href="/quiz" class="btn-primary">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                <path d="M4 2L12 7L4 12V2Z" fill="currentColor"/>
            </svg>
            Commencer le quiz
        </a>
        <a href="/oiseaux" class="btn-secondary">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                <circle cx="7" cy="7" r="5.5" stroke="currentColor" stroke-width="1.2"/>
                <path d="M5.5 5L9 7L5.5 9V5Z" fill="currentColor"/>
            </svg>
            Explorer les espèces
        </a>
    </div>

    

</section>
 
<!-- ================================================
        STATS
================================================ -->
<div class="stats-strip">
    <div class="stat-item">
        <div class="stat-num"><?= number_format($stats['nb_especes'] ?? 360, 0, ',', ' ') ?></div>
        <div class="stat-label">espèces</div>
    </div>
    <div class="stat-item">
        <div class="stat-num"><?= number_format($stats['nb_sons'] ?? 1934, 0, ',', ' ') ?></div>
        <div class="stat-label">sons</div>
    </div>
    <div class="stat-item">
        <div class="stat-num">4</div>
        <div class="stat-label">types de jeux</div>
    </div>
</div>

<!-- ================================================
        IMAGE INTERMÉDIAIRE + CITATION
================================================ -->
<div class="nature-section">
    <div class="nature-img-wrap">
        <img
            class="nature-img"
            src="/assets/img/ssBois/sous-bois.webp"
            alt="Sous-bois au lever du soleil, fougères et rayons dorés"
            loading="lazy"
            width="800"
            height="450"
        >
        <div class="nature-img-overlay"></div>
        <p class="nature-quote">
            "<span>Pour pouvoir voir les oiseaux, il faut faire partie du silence.</span><br>
            Robert Wilson Lynd , écrivain irlandais"<br><br>

            "<span>Celui qui n'a jamais entendu les premiers chants d'oiseaux tôt le matin dans le silence d'une forêt rate une sensation à nulle autre pareille.</span><br>
            Jean-Claude Génot, naturaliste français "
        </p>
    </div>
</div>

<!-- ================================================
        FONCTIONNALITÉS
================================================ -->
<section class="section-fonctionnalites" id="fonctionnalites">

    <p class="section-label">Fonctionnalités</p>
    <h2 class="section-title">Une appli pour <em>tous les niveaux</em></h2>
    <p class="section-desc">Du grand débutant au naturaliste confirmé. Progressez à votre rythme.</p>

    <div class="feature-grid">

        <div class="feature-card featured">
            <span class="feature-badge">Disponible</span>
            <div class="feature-icon gold" aria-hidden="true">🎵</div>
            <h3 class="feature-card-title">Quiz progressif</h3>
            <p class="feature-card-desc">
                QCM, vrai/faux, association son/nom. Plusieurs niveaux pour progresser
                naturellement de l'oreille novice à l'expert.
            </p>
        </div>

        <div class="feature-card">
            <span class="feature-badge">Disponible</span>
            <div class="feature-icon green" aria-hidden="true">🦉</div>
            <h3 class="feature-card-title">Explorer par espèce</h3>
            <p class="feature-card-desc">
                Navigue parmi 360 espèces, écoute tous leurs sons,
                lis leurs fiches issues de Wikipedia.
            </p>
        </div>

        <div class="feature-card">
            <span class="feature-badge soon">Bientôt</span>
            <div class="feature-icon green" aria-hidden="true">🎼</div>
            <h3 class="feature-card-title">Thématiques sonores</h3>
            <p class="feature-card-desc">
                40 thématiques organisées : chants d'aube, cris d'alarme,
                parades nuptiales et bien d'autres.
            </p>
        </div>

        <div class="feature-card">
            <span class="feature-badge">Disponible</span>
            <div class="feature-icon muted" aria-hidden="true">🎙</div>
            <h3 class="feature-card-title">Reconnaissance en direct</h3>
            <p class="feature-card-desc">
                Identifie l'oiseau que tu entends en temps réel grâce à l'API BirdNET.
                Pointe ton téléphone vers le ciel.
            </p>
        </div>

        <div class="feature-card">
            <span class="feature-badge soon">Bientôt</span>
            <div class="feature-icon muted" aria-hidden="true">📈</div>
            <h3 class="feature-card-title">Statistiques personnelles</h3>
            <p class="feature-card-desc">
                Suis ta progression, tes espèces maîtrisées et
                les sons qui te résistent encore.
            </p>
        </div>

    </div>
</section>
<div class="section-divider"></div>
<section class="martin-section">
    <div class="martin"></div>
</section>

<div class="section-divider"></div>

<!-- ================================================
    BIRDNET TEASER
================================================ -->
<section class="birdnet-section" id="birdnet">
    <div class="birdnet-card">
        <div class="pulse-ring" aria-hidden="true">
            <div class="pulse-center">🎙</div>
        </div>
        <span class="birdnet-tag">Disponible</span>
        <h3 class="birdnet-title">
            Reconnais un chant<br>
            <em>en temps réel</em>
        </h3>
        <p class="birdnet-desc">
            Grâce à l'intégration de BirdNET, un réseau de neurones entraîné sur des
            milliers d'enregistrements, l'app identifiera les oiseaux autour de toi
            en quelques secondes.
        </p>
        <button class="birdnet-btn" type="button">
            <a href="/identifier">
                Identifier un oiseau
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                    <path d="M2 6H10M7 3L10 6L7 9" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </a>
            
        </button>
    </div>
</section>
<script src="/assets/js/landing.js"></script>
<?php
require_once __DIR__ . '/layout/footer.php';
?>
