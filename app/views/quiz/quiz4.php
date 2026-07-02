<?php
require_once __DIR__ . '/../layout/header.php';
?>
<main class="img-fond">
    <section class="img-gauche"></section>
    <section class="display-cards display-quiz">
        <h1 class="titre-quiz">Trouver le nom de l'oiseau</h1><br><br>

        <div class="quiz-container" x-data="audioManager()">
            
            <?php if(!isset($resultat)): ?>
                <div class="container">
                    <h2 class="sous-titre-quiz">Quel est le nom de cet oiseau ?</h2><br>
                    <form action="/quiz/validerJeu4" method="POST">
                        
                        <div class="quiz-item" x-data="{ idx: 0 }"> 
                            <div class="player" :class="{ playing: currentIdx === idx }">
                                <audio x-ref="audio_0"
                                    src="<?= htmlspecialchars(getAudioUrl($son['chemin_fichier'])) ?>"
                                    preload="none"
                                    @timeupdate="currentIdx === idx && updateProgress($refs.audio_0)"
                                    @ended="onEnded(idx)"
                                    @loadedmetadata="setDuration($refs['audio_0'], idx)">
                                </audio>
                                <button class="player-btn"
                                        type="button"
                                        @click="toggle($refs.audio_0, idx)"
                                        :aria-label="currentIdx === idx && !paused ? 'Pause' : 'Lecture'">
                                    <svg x-show="currentIdx !== idx || paused" width="12" height="14" viewBox="0 0 12 14" fill="none">
                                        <path d="M1 1L11 7L1 13V1Z" fill="currentColor"/>
                                    </svg>
                                    <svg x-show="currentIdx === idx && !paused" width="12" height="14" viewBox="0 0 12 14" fill="none">
                                        <rect x="1" y="1" width="3.5" height="12" rx="1" fill="currentColor"/>
                                        <rect x="7.5" y="1" width="3.5" height="12" rx="1" fill="currentColor"/>
                                    </svg>
                                </button>
                                <div class="player-progress-wrap" @click="seek($event, $refs.audio_0, idx)">
                                    <div class="player-progress-bg"></div>
                                    <div class="player-progress-fill"
                                        :style="'width: ' + (currentIdx === idx ? progress : 0) + '%'"></div>
                                    <div class="player-progress-thumb"
                                        :style="'left: calc(' + (currentIdx === idx ? progress : 0) + '% - 6px)'"
                                        x-show="currentIdx === idx"></div>
                                </div>
                                <span class="player-time" x-text="currentIdx === idx ? currentTime + ' / ' + totalTime : '0:00'">0:00</span>
                            </div>
                        </div>

                        <input type="hidden" name="bonne_reponse" value="<?= htmlspecialchars($oiseau['nom_commun']) ?>">
                        <input type="hidden" name="oiseau_id" value="<?= htmlspecialchars($oiseau['id']) ?>">
                        <input type="hidden" name="son_id" value="<?= htmlspecialchars($son['id']) ?>">
                        
                        <?php foreach ($quatreNoms as $option): ?>
                            <div class="label-nom-oiseau">
                                <label >
                                    <input type="radio" name="reponse" value="<?= htmlspecialchars($option['nom_commun']) ?>">
                                    &nbsp;<?= htmlspecialchars($option['nom_commun']) ?>
                                </label><br>
                            </div>
                        <?php endforeach; ?>
                        
                        <p class="error-mess"></p>
                        <div class="validation">
                            <button type="submit" class="valider">Valider</button>
                        </div>
                    </form>
                </div>

            <?php else: ?>
                <div class="container">
                    <div class="result-navigation">
                        <a href="/oiseau/<?= $son['oiseau_id'] ?>?from=quiz" class="action-quiz">Voir Fiche oiseau</a>
                        <?php if($resultat === 'correct'): ?>
                            <p class="bonne-reponse">Bonne réponse !</p>
                            <p class="bonne-reponse-texte">Bravo ! C'est bien <?= htmlspecialchars($oiseau['nom_commun']) ?>.</p>
                        <?php else: ?>
                            <p class="mauvaise-reponse">Mauvaise réponse...</p>
                            <p class="mauvaise-reponse-texte">Dommage ! C'était  <?= htmlspecialchars($oiseau['nom_commun']) ?> !</p>
                        <?php endif; ?>
                        <a href="/quiz/<?= $derniere ? 'fin' : $jeu ?>" class="action-quiz"><?= $derniere ? 'Voir mon score' : 'Question suivante' ?></a>
                    </div>
                    <div class="quiz-item" x-data="{ idx: 0 }">
                        <p><?= nettoyerTitre($son['titre']) ?></p>
                        <div class="player" :class="{ playing: currentIdx === idx }">
                            <audio x-ref="audio_res"
                                src="<?= htmlspecialchars(getAudioUrl($son['chemin_fichier'])) ?>"
                                preload="none"
                                @timeupdate="currentIdx === idx && updateProgress($refs.audio_res)"
                                @ended="onEnded(idx)"
                                @loadedmetadata="setDuration($refs['audio_res'], idx)">
                            </audio>
                            <button class="player-btn" type="button" @click="toggle($refs.audio_res, idx)">
                                <svg x-show="currentIdx !== idx || paused" width="12" height="14" viewBox="0 0 12 14" fill="none"><path d="M1 1L11 7L1 13V1Z" fill="currentColor"/></svg>
                                <svg x-show="currentIdx === idx && !paused" width="12" height="14" viewBox="0 0 12 14" fill="none"><rect x="1" y="1" width="3.5" height="12" rx="1" fill="currentColor"/><rect x="7.5" y="1" width="3.5" height="12" rx="1" fill="currentColor"/></svg>
                            </button>
                            <div class="player-progress-wrap" @click="seek($event, $refs.audio_res, idx)">
                                <div class="player-progress-bg"></div>
                                <div class="player-progress-fill" :style="'width: ' + (currentIdx === idx ? progress : 0) + '%'"></div>
                                <div class="player-progress-thumb"
                                        :style="'left: calc(' + (currentIdx === idx ? progress : 0) + '% - 6px)'"
                                        x-show="currentIdx === idx"></div>
                            </div>
                            <span class="player-time" x-text="currentIdx === idx ? currentTime + ' / ' + totalTime : '0:00'">0:00</span>
                        </div>
                    </div>

                    
                </div>
            <?php endif; ?>
        </div> </section>
    <section class="img-droite"></section>
</main>

<script src="/assets/js/callToAction.js"></script>
<?php
require_once __DIR__ . '/../layout/footer.php';
?>