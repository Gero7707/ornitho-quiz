<?php
require_once __DIR__ . '/../layout/header.php';
?>
<main class="fiche-page">
    
    

    <section>
        
        <div class="en-tete">
            <!-- EN-TÊTE -->
            <div class="fiche-header">
                <div>
                    <h2 class="fiche-nom"><?= htmlspecialchars($oiseau['nom_commun']) ?></h2>
                    <p class="fiche-latin"><?= htmlspecialchars($oiseau['nom_latin'] ?? '') ?></p>
                    <!-- RETOUR -->
                    <?php if(isset($_GET['from']) && $_GET['from'] === 'quiz'): ?>
                        <a href="javascript:history.back()" class="fiche-back">← Retour au Quiz</a>
                    <?php elseif(isset($_GET['from']) && $_GET['from'] === 'birdnet'): ?>
                        <a href="javascript:history.back()" class="fiche-back">← Retour à l'identification</a>
                    <?php else: ?>
                        <a href="/oiseaux" class="fiche-back"> ← Retour à la liste</a>
                    <?php endif; ?>
                </div>
                
            </div>

            <!-- CAROUSEL + LIGHTBOX -->
            <?php if(!empty($images)): ?>
                <?php $imagesJson = json_encode(array_map(fn($img) => $img['url'], $images)); ?>
                <div class="carousel-wrap"
                        x-data="{
                            actif: 0,
                            total: <?= count($images) ?>,
                            lightbox: false,
                            images: <?= htmlspecialchars($imagesJson, ENT_QUOTES) ?>,
                            prev() { this.actif = (this.actif - 1 + this.total) % this.total },
                            next() { this.actif = (this.actif + 1) % this.total }
                        }"
                        @keydown.escape.window="lightbox = false">

                    <!-- Track -->
                    <div class="carousel-track"
                        :style="'transform: translateX(-' + (actif * 100) + '%)'">
                        <?php foreach($images as $img): ?>
                            <div class="carousel-slide">
                                <img src="<?= htmlspecialchars($img['url']) ?>"
                                    alt="<?= htmlspecialchars($oiseau['nom_commun']) ?>"
                                    loading="lazy"
                                    @click="lightbox = true"
                                    style="cursor: zoom-in;">
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if(count($images) > 0): ?>
                        <button class="carousel-btn prev" @click="prev()" aria-label="Précédent">&#8592;</button>
                        <button class="carousel-btn next" @click="next()" aria-label="Suivant">&#8594;</button>
                        <div class="carousel-dots">
                            <?php foreach($images as $i => $img): ?>
                                <div class="carousel-dot"
                                    :class="{ active: actif === <?= $i ?> }"
                                    @click="actif = <?= $i ?>"></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Icône zoom — toujours visible -->
                    <div class="carousel-zoom-hint" aria-hidden="true">
                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                            <circle cx="6" cy="6" r="4.5" stroke="currentColor" stroke-width="1.2"/>
                            <path d="M9.5 9.5L13 13" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                            <path d="M6 4v4M4 6h4" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                        </svg>
                    </div>

                    <!-- LIGHTBOX — toujours présente -->
                    <div class="lightbox"
                        x-show="lightbox"
                        @click.self="lightbox = false"
                        >
                        <button class="lightbox-close" @click="lightbox = false" aria-label="Fermer">&#10005;</button>
                        <?php if(count($images) > 1): ?>
                            <button class="lightbox-nav prev" @click="prev()" aria-label="Précédent">&#8592;</button>
                        <?php endif; ?>
                        <img class="lightbox-img" :src="images[actif]" alt="<?= htmlspecialchars($oiseau['nom_commun']) ?>">
                        <?php if(count($images) > 1): ?>
                            <button class="lightbox-nav next" @click="next()" aria-label="Suivant">&#8594;</button>
                            <div class="lightbox-counter" x-text="(actif + 1) + ' / ' + total"></div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif($photoUrl): ?>
                <div x-data="{ lightbox: false }" @keydown.escape.window="lightbox = false">
                    <img class="fiche-photo"
                        src="<?= htmlspecialchars($photoUrl) ?>"
                        alt="<?= htmlspecialchars($oiseau['nom_commun']) ?>"
                        @click="lightbox = true"
                        style="cursor: zoom-in;">
                    <div class="lightbox" x-show="lightbox" @click.self="lightbox = false">
                        <button class="lightbox-close" @click="lightbox = false" aria-label="Fermer">&#10005;</button>
                        <img class="lightbox-img" src="<?= htmlspecialchars($photoUrl) ?>" alt="<?= htmlspecialchars($oiseau['nom_commun']) ?>">
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="onglets">
            <div class="lien-externes">
                <a href="https://www.google.com/search?q=<?= urlencode($oiseau['nom_commun']) ?>" target="_blank"><i class="fa-brands fa-google" title="Google"></i></a>
                
                <a href="https://fr.wikipedia.org/wiki/<?= str_replace(' ', '_', $oiseau['nom_commun']) ?>" target="_blank"><i class="fa-brands fa-wikipedia-w" title="Wikipedia"></i></a>

                <a href="https://www.youtube.com/results?search_query=<?= urlencode($oiseau['nom_commun']) ?>" target="_blank"><i class="fa-brands fa-youtube" title="YouTube"></i></a>

                <a href="https://www.oiseaux.net/search?q=<?= urlencode($oiseau['nom_commun']) ?>" target="_blank"><i class="fa-solid fa-feather" title="Oiseaux.net"></i></a>
                
                <a href="https://www.gbif.org/fr/search?q=<?= urlencode($oiseau['nom_commun']) ?>" target="_blank"><i class="fa-solid fa-dove" title="gbif.org"></i></a>
            </div>
            <input type="radio" name="onglet" class="radio-display-none" id="tab-sons" checked>
            <input type="radio" name="onglet" class="radio-display-none" id="tab-infos">
            <div class="onglets-nav">
                <label for="tab-sons">Sons</label>
                <label for="tab-infos">Description</label>
            </div>
            <div class="onglet-contenu" id="contenu-sons">
                <!-- SONS -->
                <h2 class="fiche-section-title">Sons</h2>
                <ul class="sons-list" x-data="audioManager()">
                    <?php foreach ($sons as $index => $son): ?>
                    <li class="son-item" x-data="{ idx: <?= $index ?> }">
                        <p class="son-titre"><?= nettoyerTitre($son['titre']) ?></p>
                        <div class="player" :class="{ playing: currentIdx === idx }">
                            <audio x-ref="audio_<?= $index ?>"
                                    src="<?= htmlspecialchars(getAudioUrl($son['chemin_fichier'])) ?>"
                                    preload="metadata"
                                    @timeupdate="currentIdx === idx && updateProgress($refs['audio_<?= $index ?>'])"
                                    x-init="$nextTick(() => initDuration($refs['audio_<?= $index ?>'], idx))"
                                    @ended="onEnded(idx)">
                            </audio>

                            <button class="player-btn"
                                    @click="toggle($refs['audio_<?= $index ?>'], idx)"
                                    :aria-label="currentIdx === idx && !paused ? 'Pause' : 'Lecture'">
                                <svg x-show="currentIdx !== idx || paused" width="12" height="14" viewBox="0 0 12 14" fill="none">
                                    <path d="M1 1L11 7L1 13V1Z" fill="currentColor"/>
                                </svg>
                                <svg x-show="currentIdx === idx && !paused" width="12" height="14" viewBox="0 0 12 14" fill="none">
                                    <rect x="1" y="1" width="3.5" height="12" rx="1" fill="currentColor"/>
                                    <rect x="7.5" y="1" width="3.5" height="12" rx="1" fill="currentColor"/>
                                </svg>
                            </button>

                            <div class="player-progress-wrap"
                                    @click="seek($event, $refs['audio_<?= $index ?>'], idx)">
                                <div class="player-progress-bg"></div>
                                <div class="player-progress-fill"
                                    :style="'width: ' + (currentIdx === idx ? progress : 0) + '%'"></div>
                                <div class="player-progress-thumb"
                                    :style="'left: calc(' + (currentIdx === idx ? progress : 0) + '% - 6px)'"
                                    x-show="currentIdx === idx"></div>
                            </div>

                            <span class="player-time" x-text="currentIdx === idx ? currentTime + ' / ' + (durations[idx] ?? '0:00') : (durations[idx] ?? '0:00')">0:00</span>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="onglet-contenu" id="contenu-infos">
                <!-- DESCRIPTION -->
                <?php if($description || !empty($oiseau['description_courte'])): ?>
                    <h2 class="fiche-section-title">Description</h2>
                    <div class="fiche-description">
                        <?php if($description): ?>
                            <p><?= htmlspecialchars($description) ?></p>
                        <?php endif; ?>
                        <?= $oiseau['description_courte'] ?><br>
                        <a href="https://en.wikipedia.org/wiki/<?= str_replace(' ', '_', $oiseau['nom_latin']) ?>" 
                            target="_blank" 
                            class="lien-wiki-en">
                            En savoir plus (Wikipedia EN)
                        </a>
                    </div>
                <?php endif; ?>
                <div>
                    <!-- IUCN -->
                    <?php if($oiseau['iucn_statut']): ?>
                        <h2 class="fiche-section-title">Statut IUCN</h2>
                        <div class="iucn-block">
                            <div class="iucn-row">
                                <span class="iucn-label">Statut</span>
                                <span><?= htmlspecialchars($oiseau['iucn_statut']) ?></span>
                            </div>
                            <div class="iucn-row">
                                <span class="iucn-label">Tendance</span>
                                <span><?= htmlspecialchars($oiseau['iucn_tendance'] ?? '') ?></span>
                            </div>
                            <?php if(!empty($oiseau['iucn_criteres'])): ?>
                                <div class="iucn-row">
                                    <span class="iucn-label">Critères</span>
                                    <span>
                                        <?= htmlspecialchars($oiseau['iucn_criteres']) ?>
                                        — <a href="https://www.iucnredlist.org/resources/categories-and-criteria" target="_blank">explication</a>
                                    </span>
                                </div>
                            <?php endif; ?>
                            <div class="iucn-row">
                                <span class="iucn-label">Année</span>
                                <span><?= htmlspecialchars($oiseau['iucn_annee'] ?? '') ?></span>
                            </div>
                            <p>Tous les status :</p>
                            <p>(EX) Éteinte,(EW) Éteinte à l'état sauvage ,(CR) En danger critique ,<br>(EN) En danger , (VU) Vulnérable , (NT) Quasi menacée ,<br>(LC) Préoccupation mineure , (DD) Données insuffisantes , <br>(NE) Non évaluée</p>
                        </div>
                        
                    <?php endif; ?>
                </div>
            </div>
            
        </div>
    </section>
</main>

<script>

</script>
<script src="/assets/js/audio.js"></script>
<?php
require_once __DIR__ . '/../layout/footer.php';
?>