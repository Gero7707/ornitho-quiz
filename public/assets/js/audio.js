document.addEventListener('DOMContentLoaded', () => {
    // Arrêter les autres audios quand un démarre
    document.querySelectorAll('audio').forEach(audio => {
        audio.addEventListener('play', () => {
            document.querySelectorAll('audio').forEach(other => {
                if (other !== audio) other.pause();
            });
        });
    });

});
document.addEventListener('alpine:init', () => {
    Alpine.data('audioManager', () => ({
        currentIdx: null,
        paused: true,
        progress: 0,
        currentTime: '0:00',
        totalTime: '0:00',
        durations: {},
        toggle(audio, idx) {
            if (this.currentIdx !== null && this.currentIdx !== idx) {
                const audios = document.querySelectorAll('audio');
                if (audios[this.currentIdx]) {
                    audios[this.currentIdx].pause();
                    audios[this.currentIdx].currentTime = 0;
                }
                this.progress = 0;
                this.currentTime = '0:00';
            }
            this.currentIdx = idx;
            if (audio.paused) {
                audio.play().then(() => {
                this.paused = false;
            }).catch(() => {
                this.paused = true;
            });
            } else {
                audio.pause();
                this.paused = true;
            }
        },
        updateProgress(audio) {
            if (!audio.duration) return;
            this.totalTime = this.durations[this.currentIdx] || '0:00';
            const update = () => {
                if (audio.paused || audio.ended) return;
                this.progress = (audio.currentTime / audio.duration) * 100;
                const m = Math.floor(audio.currentTime / 60);
                const s = Math.floor(audio.currentTime % 60).toString().padStart(2, '0');
                this.currentTime = m + ':' + s;
                requestAnimationFrame(update);
            };
            
            requestAnimationFrame(update);
        },
        setDuration(audio, idx) {
            if (!audio.duration) return;
            const m = Math.floor(audio.duration / 60);
            const s = Math.floor(audio.duration % 60).toString().padStart(2, '0');
            this.durations[idx] = m + ':' + s;
        },
        initDuration(audio, idx) {
            if (audio.readyState >= 1) {
                this.setDuration(audio, idx);
            } else {
                audio.addEventListener('loadedmetadata', () => this.setDuration(audio, idx), { once: true });
            }
        },
        seek(event, audio, idx) {
            if (this.currentIdx !== idx) return;
            const rect = event.currentTarget.getBoundingClientRect();
            const ratio = Math.max(0, Math.min(1, (event.clientX - rect.left) / rect.width));
            if (audio.duration) audio.currentTime = ratio * audio.duration;
        },
        onEnded(idx) {
            if (this.currentIdx === idx) {
                this.paused = true;
                this.progress = 0;
                this.currentTime = '0:00';
            }
        }
    }));
});


