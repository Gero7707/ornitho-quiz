<?php
$pageTitle = "Identifier un oiseau — OrnithoQuizz";
$pageCss = "birdnet";
require_once __DIR__ . '/../layout/header.php';
?>
<a href="/" class="fiche-back-id"> ← Retour à l'accueil</a><br>
<section class="birdnet-hero">
    <div class="birdnet-hero__content">
        <span class="birdnet-hero__badge">Powered by BirdNET</span>
        <h1 class="birdnet-hero__title">Identifier un oiseau par son chant</h1>
        <p class="birdnet-hero__subtitle">
            Enregistrez ou importez un son d'oiseau — l'intelligence artificielle BirdNET 
            analysera l'audio directement dans votre navigateur pour identifier l'espèce.
        </p>
    </div>
</section>

<section class="birdnet-app" x-data="birdnetApp()">

    <!-- Chargement du modèle -->
    <div class="birdnet-model-status" x-show="modelStatus !== 'ready'">
        <template x-if="modelStatus === 'loading'">
            <div class="birdnet-model-loading">
                <span class="birdnet-spinner birdnet-spinner--large"></span>
                <p>Chargement du modèle BirdNET...</p>
                <p class="birdnet-model-loading__detail" x-text="modelLoadingDetail"></p>
                <div class="birdnet-progress">
                    <div class="birdnet-progress__bar" :style="'width:' + modelLoadingProgress + '%'"></div>
                </div>
            </div>
        </template>
        <template x-if="modelStatus === 'error'">
            <div class="birdnet-model-error">
                <p>Erreur lors du chargement du modèle.</p>
                <button type="button" @click="loadModel()">Réessayer</button>
            </div>
        </template>
    </div>

    <!-- Sélection du mode -->
    <div class="birdnet-modes">
        <button type="button" 
                class="birdnet-mode" 
                :class="{ 'birdnet-mode--active': mode === 'upload' }"
                @click="mode = 'upload'">
            
            <span class="birdnet-mode__label">Importer</span>
        </button>

        <button type="button" 
                class="birdnet-mode" 
                :class="{ 'birdnet-mode--active': mode === 'record' }"
                @click="mode = 'record'">
            
            <span class="birdnet-mode__label">Enregistrer</span>
        </button>
    </div>

    <!-- Zone Upload -->
    <div class="birdnet-panel" x-show="mode === 'upload'" x-transition>
        <div class="birdnet-dropzone" 
            :class="{ 'birdnet-dropzone--drag': isDragging }"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="handleDrop($event)"
            @click="$refs.fileInput.click()">
            
            <input type="file" 
                    x-ref="fileInput" 
                    accept="audio/*" 
                    class="birdnet-dropzone__input"
                    @change="handleFileSelect($event)">

            <div class="birdnet-dropzone__content" x-show="!audioFile">
                <svg class="birdnet-dropzone__icon" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="1.5">
                    <circle cx="32" cy="32" r="28"/>
                    <path d="M32 20v24M20 32h24"/>
                </svg>
                <p class="birdnet-dropzone__text">
                    Glissez un fichier audio ici<br>
                    <span>ou cliquez pour parcourir</span>
                </p>
                <p class="birdnet-dropzone__formats">MP3, WAV, OGG, FLAC — max 10 Mo</p>
            </div>

            <div class="birdnet-dropzone__file" x-show="audioFile" @click.stop>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24">
                    <path d="M9 18V5l12-2v13"/>
                    <circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>
                </svg>
                <div class="birdnet-dropzone__file-info">
                    <span x-text="audioFile?.name"></span>
                    <span x-text="audioFileSize"></span>
                </div>
                <button type="button" class="birdnet-dropzone__remove" @click="removeFile()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Player audio preview -->
        <div class="birdnet-player" x-show="audioUrl">
            <audio :src="audioUrl" controls x-ref="audioPlayer"></audio>
        </div>

        <button type="button" 
                class="birdnet-analyze-btn" 
                :disabled="!audioFile || isAnalyzing"
                @click="analyzeFile()">
            <template x-if="!isAnalyzing">
                <span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    Identifier l'oiseau
                </span>
            </template>
            <template x-if="isAnalyzing">
                <span>
                    <span class="birdnet-spinner"></span>
                    Analyse en cours...
                </span>
            </template>
        </button>
    </div>

    <!-- Zone Enregistrement -->
    <div class="birdnet-panel" x-show="mode === 'record'" x-transition>
        <div class="birdnet-recorder">
            
            <!-- Visualisation audio -->
            <div class="birdnet-recorder__viz">
                <canvas x-ref="vizCanvas" width="600" height="120"></canvas>
                <div class="birdnet-recorder__time" x-show="isRecording || recordedBlob">
                    <span x-text="recordingTime"></span>
                </div>
            </div>

            <!-- Boutons d'enregistrement -->
            <div class="birdnet-recorder__controls">
                <svg class="birdnet-mode__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/>
                    <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
                    <line x1="12" y1="19" x2="12" y2="23"/>
                    <line x1="8" y1="23" x2="16" y2="23"/>
                </svg>
                <template x-if="!isRecording && !recordedBlob">
                    <button type="button" class="birdnet-rec-btn birdnet-rec-btn--start" @click="startRecording()">
                        <span class="birdnet-rec-btn__dot"></span>
                        Enregistrer
                    </button>
                </template>

                <template x-if="isRecording">
                    <button type="button" class="birdnet-rec-btn birdnet-rec-btn--stop" @click="stopRecording()">
                        <span class="birdnet-rec-btn__square"></span>
                        Arrêter
                    </button>
                </template>

                <template x-if="recordedBlob && !isRecording">
                    <div class="birdnet-recorder__actions">
                        <button type="button" class="birdnet-rec-btn birdnet-rec-btn--retry" @click="resetRecording()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18">
                                <polyline points="1 4 1 10 7 10"/>
                                <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>
                            </svg>
                            Recommencer
                        </button>
                        <button type="button" 
                                class="birdnet-analyze-btn" 
                                :disabled="isAnalyzing"
                                @click="analyzeRecording()">
                            <template x-if="!isAnalyzing">
                                <span>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20">
                                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                    </svg>
                                    Identifier
                                </span>
                            </template>
                            <template x-if="isAnalyzing">
                                <span>
                                    <span class="birdnet-spinner"></span>
                                    Analyse...
                                </span>
                            </template>
                        </button>
                    </div>
                </template>
            </div>

            <!-- Player de l'enregistrement -->
            <div class="birdnet-player" 
                <div class="birdnet-player" 
                x-show="recordedUrl" 
                x-data="{ playing: false, progress: 0, currentTime: '0:00' }">
                
                <audio x-ref="playerAudio" :src="recordedUrl"
                    @ended="playing = false; progress = 0; currentTime = '0:00'"
                    @timeupdate="
                        progress = ($refs.playerAudio.currentTime / $refs.playerAudio.duration) * 100;
                        let s = Math.floor($refs.playerAudio.currentTime);
                        currentTime = Math.floor(s/60) + ':' + String(s%60).padStart(2, '0')
                    ">
                </audio>

                <button type="button" class="birdnet-player__btn"
                        @click="playing ? $refs.playerAudio.pause() : $refs.playerAudio.play(); playing = !playing">
                    <svg x-show="!playing" width="10" height="12" viewBox="0 0 12 14" fill="none">
                        <path d="M1 1L11 7L1 13V1Z" fill="currentColor"/>
                    </svg>
                    <svg x-show="playing" width="10" height="12" viewBox="0 0 12 14" fill="none">
                        <rect x="1" y="1" width="3.5" height="12" rx="1" fill="currentColor"/>
                        <rect x="7.5" y="1" width="3.5" height="12" rx="1" fill="currentColor"/>
                    </svg>
                </button>

                <div class="birdnet-player__progress"
                    @click="
                        let rect = $el.getBoundingClientRect();
                        $refs.playerAudio.currentTime = ((event.clientX - rect.left) / rect.width) * $refs.playerAudio.duration
                    ">
                    <div class="birdnet-player__progress-bg"></div>
                    <div class="birdnet-player__progress-fill" :style="'width:' + progress + '%'"></div>
                </div>

                <span class="birdnet-player__time" x-text="currentTime"></span>
            </div>
                    </div>
    </div>

    <!-- Chargement du modèle -->
    <div class="birdnet-model-status" x-show="modelStatus !== 'ready'">
        <template x-if="modelStatus === 'loading'">
            <div class="birdnet-model-loading">
                <span class="birdnet-spinner birdnet-spinner--large"></span>
                <p>Chargement du modèle BirdNET...</p>
                <p class="birdnet-model-loading__detail" x-text="modelLoadingDetail"></p>
                <div class="birdnet-progress">
                    <div class="birdnet-progress__bar" :style="'width:' + modelLoadingProgress + '%'"></div>
                </div>
            </div>
        </template>
        <template x-if="modelStatus === 'error'">
            <div class="birdnet-model-error">
                <p>Erreur lors du chargement du modèle.</p>
                <button type="button" @click="loadModel()">Réessayer</button>
            </div>
        </template>
    </div>

    <!-- Résultats -->
    <div class="birdnet-results" x-show="results.length > 0">
        <h2 class="birdnet-results__title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
            Espèces détectées
        </h2>

        <template x-for="(result, index) in results" :key="index">
            <div>
                <!-- Liens de recherche externe (si NON trouvé en BDD) -->
                
            

                <div class="birdnet-result" :class="{ 'birdnet-result--matched': result.matched }">
                    <div class="birdnet-result__rank" x-text="index + 1"></div>
                    <div class="birdnet-result__info">
                        <span class="birdnet-result__name" x-text="result.commonName"></span>
                        <span class="birdnet-result__latin" x-text="result.scientificName"></span>
                        <span class="birdnet-result__time" x-text="result.timeRange" x-show="result.timeRange"></span>
                    </div>
                    <div class="birdnet-result__external" x-show="!result.matched">
                        <span>Fiche absente. En savoir plus :</span>
                        <div class="external-links">
                            <a :href="'https://www.google.com/search?q=' + encodeURIComponent(result.commonName + ' oiseau')" 
                            target="_blank" rel="noopener" title="Rechercher sur Google">
                                <i class="fa-brands fa-google" title="Google"></i>
                            </a>
                            <a :href="'https://fr.wikipedia.org/w/index.php?search=' + encodeURIComponent(result.commonName)"
                            target="_blank" rel="noopener" title="Wikipedia">
                                <i class="fa-brands fa-wikipedia-w" title="Wikipedia"></i>
                            </a>
                            <a :href="'https://www.youtube.com/results?search_query=' + encodeURIComponent(result.commonName + ' chant oiseau')" 
                            target="_blank" rel="noopener" title="YouTube">
                                <i class="fa-brands fa-youtube" title="YouTube"></i>
                            </a>
                            <a :href="'https://www.google.com/search?q=site:oiseaux.net+' + encodeURIComponent(result.commonName)"
                            target="_blank" rel="noopener" title="Rechercher sur oiseaux.net">
                                <i class="fa-solid fa-feather" title="Oiseaux.net"></i>
                            </a>
                            <a :href="'https://www.gbif.org/fr/species/search?q=' + encodeURIComponent(result.scientificName)" 
                            target="_blank" rel="noopener" title="Rechercher sur GBIF">
                                <i class="fa-solid fa-dove" title="gbif.org"></i>
                            </a>
                        </div>
                    </div>
                    <a class="birdnet-result__link" 
                        :href="result.url" 
                        x-show="result.matched"
                        target="_blank"
                        title="Voir la fiche espèce">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20">
                            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                            <polyline points="15 3 21 3 21 9"/>
                            <line x1="10" y1="14" x2="21" y2="3"/>
                        </svg>
                        Fiche
                    </a>
                    <div class="birdnet-result__confidence">
                        <div class="birdnet-result__bar">
                            <div class="birdnet-result__bar-fill" :style="'width:' + result.confidence + '%'"></div>
                        </div>
                        <span class="birdnet-result__pct" x-text="result.confidence + '%'"></span>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- Message si aucun résultat -->
    <div class="birdnet-no-results" x-show="analysisComplete && results.length === 0">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="48">
            <circle cx="12" cy="12" r="10"/>
            <line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
        </svg>
        <p>Aucune espèce détectée avec suffisamment de confiance.</p>
        <p class="birdnet-no-results__hint">Essayez avec un enregistrement plus clair ou plus long.</p>
    </div>

</section>

<!-- Info BirdNET -->
<section class="birdnet-info">
    <div class="birdnet-info__content">
        <h2>Comment ça marche ?</h2>
        <div class="birdnet-info__steps">
            <div class="birdnet-info__step">
                <div class="birdnet-info__step-num">1</div>
                <h3>Capturez un son</h3>
                <p>Enregistrez avec votre micro ou importez un fichier audio contenant un chant d'oiseau.</p>
            </div>
            <div class="birdnet-info__step">
                <div class="birdnet-info__step-num">2</div>
                <h3>Analyse locale</h3>
                <p>L'IA BirdNET analyse le son directement dans votre navigateur. Rien n'est envoyé sur internet.</p>
            </div>
            <div class="birdnet-info__step">
                <div class="birdnet-info__step-num">3</div>
                <h3>Découvrez l'espèce</h3>
                <p>Consultez les résultats et accédez aux fiches détaillées des espèces identifiées.</p>
            </div>
        </div>
        <p class="birdnet-info__credit">
            Identification propulsée par <a href="https://birdnet.cornell.edu" target="_blank" rel="noopener">BirdNET</a> — 
            Cornell Lab of Ornithology & Chemnitz University of Technology.
        </p>
    </div>
</section>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>