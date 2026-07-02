/**
 * BirdNET Identifier — Alpine.js Component
 * Communique avec birdnet-worker.js pour l'analyse audio réelle
 */

const BIRDNET_SAMPLE_RATE = 48000;
const BIRDNET_CHUNK_SIZE = 144000; // 3 secondes à 48 kHz
// URL de base des modèles sur R2
const BIRDNET_MODEL_URL = window.AUDIO_BASE_URL
    ? window.AUDIO_BASE_URL.replace(/\/$/, '') + '/birdnet-model'
    : '/birdnet-model';

document.addEventListener('alpine:init', () => {
    Alpine.data('birdnetApp', () => ({

        // ─── État général ──────────────────────────────────
        mode: 'upload',
        modelStatus: 'loading',
        modelLoadingDetail: '',
        modelLoadingProgress: 0,
        isAnalyzing: false,
        analysisComplete: false,
        analysisProgress: 0,
        results: [],
        worker: null,

        // ─── Upload ────────────────────────────────────────
        audioFile: null,
        audioUrl: null,
        audioFileSize: '',
        isDragging: false,

        // ─── Enregistrement ────────────────────────────────
        isRecording: false,
        recordedBlob: null,
        recordedUrl: null,
        recordingTime: '0:00',
        mediaRecorder: null,
        audioChunks: [],
        recordingInterval: null,
        recordingStartTime: null,
        audioContext: null,
        analyserNode: null,
        animationFrame: null,

        // ─── Initialisation ────────────────────────────────
        init() {
            this.loadModel();
        },

        // ─── Chargement du modèle via Worker ───────────────
        async loadModel() {
            this.modelStatus = 'loading';
            this.modelLoadingProgress = 0;
            this.modelLoadingDetail = 'Initialisation...';

            try {
                const workerUrl = `/assets/js/birdnet-worker.js?modelUrl=${encodeURIComponent(BIRDNET_MODEL_URL)}`;
                this.worker = new Worker(workerUrl);

                await new Promise((resolve, reject) => {
                    const timeout = setTimeout(() => reject(new Error('Timeout')), 120000);

                    this.worker.addEventListener('message', ({ data }) => {
                        if (data.progress !== undefined) {
                            this.modelLoadingProgress = data.progress;
                        }
                        if (data.message === 'load_model') {
                            this.modelLoadingDetail = 'Chargement du modèle BirdNET...';
                        }
                        if (data.message === 'warmup') {
                            this.modelLoadingDetail = 'Préchauffage du modèle...';
                        }
                        if (data.message === 'load_geomodel') {
                            this.modelLoadingDetail = 'Chargement du modèle géographique...';
                        }
                        if (data.message === 'load_labels') {
                            this.modelLoadingDetail = 'Chargement des noms d\'espèces...';
                        }
                        if (data.message === 'loaded') {
                            clearTimeout(timeout);
                            resolve();
                        }
                    });

                    this.worker.addEventListener('error', (e) => {
                        clearTimeout(timeout);
                        reject(e);
                    });
                });

                // Envoyer la position géographique pour filtrer les espèces
                this.modelLoadingDetail = 'Filtrage géographique...';
                this.modelLoadingProgress = 98;
                await this.sendGeoLocation();

                this.modelStatus = 'ready';
                this.modelLoadingProgress = 100;

            } catch (err) {
                console.error('Erreur chargement modèle:', err);
                this.modelStatus = 'error';
            }
        },

        async sendGeoLocation() {
            try {
                const position = await new Promise((resolve, reject) => {
                    navigator.geolocation.getCurrentPosition(resolve, reject, {
                        enableHighAccuracy: false,
                        timeout: 5000
                    });
                });
                await this.sendWorkerMessage({
                    message: 'area-scores',
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude
                });
            } catch (e) {
                console.log('Géolocalisation non disponible, analyse sans filtre géographique.');
            }
        },

        sendWorkerMessage(data) {
            return new Promise(resolve => {
                const handler = ({ data: response }) => {
                    if (response.message === data.message) {
                        this.worker.removeEventListener('message', handler);
                        resolve(response);
                    }
                };
                this.worker.addEventListener('message', handler);
                this.worker.postMessage(data);
            });
        },

        // ─── Méthodes Upload ───────────────────────────────

        handleFileSelect(event) {
            const file = event.target.files[0];
            if (file) this.setFile(file);
        },

        handleDrop(event) {
            this.isDragging = false;
            const file = event.dataTransfer.files[0];
            if (file && file.type.startsWith('audio/')) {
                this.setFile(file);
            }
        },

        setFile(file) {
            if (file.size > 10 * 1024 * 1024) {
                alert('Le fichier est trop volumineux (max 10 Mo).');
                return;
            }
            this.audioFile = file;
            this.audioFileSize = this.formatSize(file.size);
            this.audioUrl = URL.createObjectURL(file);
            this.results = [];
            this.analysisComplete = false;
        },

        removeFile() {
            if (this.audioUrl) URL.revokeObjectURL(this.audioUrl);
            this.audioFile = null;
            this.audioUrl = null;
            this.audioFileSize = '';
            this.results = [];
            this.analysisComplete = false;
        },

        formatSize(bytes) {
            if (bytes < 1024) return bytes + ' o';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(0) + ' Ko';
            return (bytes / (1024 * 1024)).toFixed(1) + ' Mo';
        },

        // ─── Méthodes Enregistrement ───────────────────────

        async startRecording() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    audio: {
                        sampleRate: BIRDNET_SAMPLE_RATE,
                        channelCount: 1,
                        echoCancellation: false,
                        noiseSuppression: false
                    }
                });

                this.audioChunks = [];
                this.mediaRecorder = new MediaRecorder(stream, {
                    mimeType: this.getSupportedMimeType()
                });

                this.mediaRecorder.ondataavailable = (e) => {
                    if (e.data.size > 0) this.audioChunks.push(e.data);
                };

                this.mediaRecorder.onstop = () => {
                    this.recordedBlob = new Blob(this.audioChunks, { type: 'audio/webm' });
                    this.recordedUrl = URL.createObjectURL(this.recordedBlob);
                    stream.getTracks().forEach(track => track.stop());
                    this.stopVisualization();
                };

                this.mediaRecorder.start(100);
                this.isRecording = true;
                this.recordingStartTime = Date.now();
                this.results = [];
                this.analysisComplete = false;

                this.recordingInterval = setInterval(() => {
                    const elapsed = Math.floor((Date.now() - this.recordingStartTime) / 1000);
                    const min = Math.floor(elapsed / 60);
                    const sec = (elapsed % 60).toString().padStart(2, '0');
                    this.recordingTime = `${min}:${sec}`;
                }, 200);

                this.startVisualization(stream);

            } catch (err) {
                console.error('Erreur micro:', err);
                alert('Impossible d\'accéder au microphone. Vérifiez les permissions.');
            }
        },

        stopRecording() {
            if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
                this.mediaRecorder.stop();
            }
            this.isRecording = false;
            clearInterval(this.recordingInterval);
        },

        resetRecording() {
            if (this.recordedUrl) URL.revokeObjectURL(this.recordedUrl);
            this.recordedBlob = null;
            this.recordedUrl = null;
            this.recordingTime = '0:00';
            this.results = [];
            this.analysisComplete = false;
        },

        getSupportedMimeType() {
            const types = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/mp4'];
            for (const type of types) {
                if (MediaRecorder.isTypeSupported(type)) return type;
            }
            return '';
        },

        // ─── Visualisation audio ───────────────────────────

        startVisualization(stream) {
            this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const source = this.audioContext.createMediaStreamSource(stream);
            this.analyserNode = this.audioContext.createAnalyser();
            this.analyserNode.fftSize = 256;
            source.connect(this.analyserNode);

            const canvas = this.$refs.vizCanvas;
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            const bufferLength = this.analyserNode.frequencyBinCount;
            const dataArray = new Uint8Array(bufferLength);

            const draw = () => {
                this.animationFrame = requestAnimationFrame(draw);
                this.analyserNode.getByteFrequencyData(dataArray);
                ctx.fillStyle = 'rgba(10, 26, 15, 0.85)';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                const barWidth = (canvas.width / bufferLength) * 1.5;
                let x = 0;
                for (let i = 0; i < bufferLength; i++) {
                    const barHeight = (dataArray[i] / 255) * canvas.height * 0.85;
                    const hue = 45 + (dataArray[i] / 255) * 30;
                    const lightness = 30 + (dataArray[i] / 255) * 25;
                    ctx.fillStyle = `hsl(${hue}, 60%, ${lightness}%)`;
                    ctx.fillRect(x, canvas.height - barHeight, barWidth - 1, barHeight);
                    x += barWidth;
                }
            };
            draw();
        },

        stopVisualization() {
            if (this.animationFrame) {
                cancelAnimationFrame(this.animationFrame);
                this.animationFrame = null;
            }
            if (this.audioContext) {
                this.audioContext.close();
                this.audioContext = null;
            }
        },

        // ─── Analyse ───────────────────────────────────────

        async analyzeFile() {
            if (!this.audioFile) return;
            await this.analyzeAudio(this.audioFile);
        },

        async analyzeRecording() {
            if (!this.recordedBlob) return;
            await this.analyzeAudio(this.recordedBlob);
        },

        async analyzeAudio(audioBlob) {
            if (this.modelStatus !== 'ready') {
                alert('Le modèle BirdNET est encore en cours de chargement.');
                return;
            }

            this.isAnalyzing = true;
            this.results = [];
            this.analysisComplete = false;
            this.analysisProgress = 0;

            try {
                // 1. Décoder l'audio à 48 kHz
                const arrayBuffer = await audioBlob.arrayBuffer();
                const tempCtx = new AudioContext({ sampleRate: BIRDNET_SAMPLE_RATE });
                const audioBuffer = await tempCtx.decodeAudioData(arrayBuffer);
                const pcmAudio = new Float32Array(audioBuffer.getChannelData(0));
                tempCtx.close();

                const totalChunks = Math.ceil(pcmAudio.length / BIRDNET_CHUNK_SIZE);
                const allDetections = [];

                // 2. Analyser par chunks de 3 secondes
                for (let k = 0; k < pcmAudio.length; k += BIRDNET_CHUNK_SIZE) {
                    let chunk = pcmAudio.slice(k, k + BIRDNET_CHUNK_SIZE);

                    if (chunk.length < BIRDNET_CHUNK_SIZE) {
                        const padded = new Float32Array(BIRDNET_CHUNK_SIZE);
                        padded.set(chunk, 0);
                        chunk = padded;
                    }

                    const chunkIndex = Math.floor(k / BIRDNET_CHUNK_SIZE);
                    const startTime = k / BIRDNET_SAMPLE_RATE;
                    const endTime = Math.min((k + BIRDNET_CHUNK_SIZE) / BIRDNET_SAMPLE_RATE, pcmAudio.length / BIRDNET_SAMPLE_RATE);

                    const response = await this.sendWorkerMessage({
                        message: 'predict',
                        pcmAudio: chunk,
                        chunkIndex: chunkIndex,
                        minAreaConfidence: 0
                    });

                    for (const det of response.prediction) {
                        allDetections.push({ ...det, startTime, endTime });
                    }

                    this.analysisProgress = Math.round(((chunkIndex + 1) / totalChunks) * 100);
                }

                // 3. Garder le meilleur score par espèce
                const bestBySpecies = {};
                for (const det of allDetections) {
                    const key = det.scientificName;
                    if (!bestBySpecies[key] || det.confidence > bestBySpecies[key].confidence) {
                        bestBySpecies[key] = det;
                    }
                }

                // 4. Top 10, filtrés à > 10% de confiance
                const topResults = Object.values(bestBySpecies)
                    .sort((a, b) => b.confidence - a.confidence)
                    .slice(0, 10)
                    .filter(r => r.confidence > 0.1);

                // 5. Correspondances BDD
                const results = [];
                for (const det of topResults) {
                    const result = {
                        scientificName: det.scientificName,
                        commonName: det.commonNameFr || det.commonName,
                        confidence: Math.round(det.confidence * 100),
                        timeRange: `${Math.floor(det.startTime)}s — ${Math.floor(det.endTime)}s`,
                        matched: false,
                        url: null
                    };

                    try {
                        const resp = await fetch(`/birdnet/match?nom_latin=${encodeURIComponent(det.scientificName)}`);
                        const data = await resp.json();
                        if (data.found) {
                            result.matched = true;
                            result.url = data.url;
                            result.commonName = data.nom_commun;
                        }
                    } catch (e) { /* pas grave */ }

                    results.push(result);
                }

                this.results = results;

            } catch (err) {
                console.error('Erreur analyse BirdNET:', err);
                alert('Erreur lors de l\'analyse : ' + err.message);
            } finally {
                this.isAnalyzing = false;
                this.analysisComplete = true;
            }
        }
    }));
});