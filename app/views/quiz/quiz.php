<?php
require_once __DIR__ . '/../layout/header.php';
?>
<main  class="img-fond">
    <section class="img-gauche"></section>
    <section class="display-cards display-quiz">
        <h1 class="titre-quiz">Trouvez l'intrus</h1>
        <p class="info">Cocher pour valider votre réponse.</p>
            <?php if(!isset($resultat)): ?>
                <div class="quiz-container" x-data="audioManager()">
                    <form action="/quiz/valider" method="POST">
                        <input type="hidden" name="bonne_reponse_id" value="<?= htmlspecialchars($bonneReponseId) ?>">
                        <input type="hidden" name="oiseau_id" value="<?= htmlspecialchars($oiseau['id']) ?>">
                        <input type="hidden" name="sons_ids" value="<?= htmlspecialchars(implode(',', array_column($quatreSons, 'id'))) ?>">
                        <input type="hidden" name="oiseau_cible_id" value="<?= htmlspecialchars($intruOiseauId) ?>">
                        <input type="hidden" name="oiseau_intrus_id" value="<?= htmlspecialchars($intru['oiseau_id']) ?>">
                        <?php foreach ($quatreSons as $index => $son): ?>
                            <div class="quiz-item" x-data="{ idx: <?= $index ?> }">
                                <div class="player" :class="{ playing: currentIdx === idx }">
                                    <audio x-ref="audio_<?= $index ?>"
                                            src="<?= htmlspecialchars(getAudioUrl($son['chemin_fichier'])) ?>"
                                            preload="metadata"
                                            @timeupdate="currentIdx === idx && updateProgress($refs['audio_<?= $index ?>'])"
                                            x-init="$nextTick(() => initDuration($refs['audio_<?= $index ?>'], idx))"
                                            @ended="onEnded(idx)">
                                    </audio>
                                    <button class="player-btn"
                                            type="button"
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
                                <input type="radio" name="son_id" class="radio-quiz" value="<?= htmlspecialchars($son['id']) ?>"><i class="fa-solid fa-hand-point-left"></i>
                            </div>
                        <?php endforeach; ?>
                        <p class="error-mess"></p>
                        <div class="validation">
                            <button type="submit" class="valider">Valider</button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <?php if($resultat === 'correct'): ?>
                    <p class="bonne-reponse">Bonne réponse !</p>
                    <p class="bonne-reponse-texte">Bravo ! C'est bien <?= htmlspecialchars($vraiIntrus['nom_commun']) ?>.</p>
                    <p class="info">Cliquer sur le nom de l'oiseau pour voir la fiche.</p>
                <?php else: ?>
                    <p class="mauvaise-reponse">Mauvaise réponse...</p>
                    <p class="mauvaise-reponse-texte">Dommage ! C'était  <?= htmlspecialchars($vraiIntrus['nom_commun']) ?> !</p>
                    <p class="info">Cliquer sur le nom de l'oiseau pour voir la fiche.</p>
                <?php endif; ?>
                <a href="/quiz/<?= $derniere ? 'fin' : $jeu ?>" class="action-quiz"><?= $derniere ? 'Voir mon score' : 'Question suivante' ?></a>
                <div class="quiz-container" x-data="audioManager()">
                    <?php foreach ($quatreSons as $index => $son): ?>
                        <div class="quiz-item" x-data="{ idx: <?= $index ?> }">
                            <p><a href="/oiseau/<?= $son['oiseau_id'] ?>?from=quiz"><?= htmlspecialchars($son['nom_commun'] ?? '') ?></a></p>
                            <div class="player" :class="{ playing: currentIdx === idx }">
                                <audio x-ref="audio_<?= $index ?>"
                                    src="<?= htmlspecialchars(getAudioUrl($son['chemin_fichier'])) ?>"
                                    preload="metadata"
                                    @timeupdate="currentIdx === idx && updateProgress($refs['audio_<?= $index ?>'])"
                                    x-init="$nextTick(() => initDuration($refs['audio_<?= $index ?>'], idx))"
                                    @ended="onEnded(idx)">
                                </audio>
                                <button type="button" class="player-btn"
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
                        </div>
                    <?php endforeach; ?>
                </div>
                
            <?php endif; ?>
    </section>
    <section class="img-droite"></section>
</main>
<script src="/assets/js/callToAction.js"></script>
<?php
require_once __DIR__ . '/../layout/footer.php';
?>