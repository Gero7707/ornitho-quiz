/**
 * OrnithoQuiz — Landing page JS
 */

document.addEventListener('DOMContentLoaded', () => {

    // ----------------------------------------
    // Waveform décorative
    // ----------------------------------------
    const waveform = document.getElementById('waveform');
    if (waveform) {
        const heights = Array.from({length: 300}, () => 60).map(h => Math.min(h * 2, 100));
        heights.forEach((h, i) => {
            const bar = document.createElement('div');
            bar.className = 'waveform-bar';
            bar.style.height        = h + '%';
            bar.style.animationDelay = (i * 0.03) + 's';
            waveform.appendChild(bar);
        });
    }

    // ----------------------------------------
    // Nav : fond opaque au scroll
    // ----------------------------------------
    const nav = document.getElementById('nav');
    if (nav) {
        const onScroll = () => {
            if (window.scrollY > 60) {
                nav.style.background = 'rgba(13,31,26,0.5)';
                nav.style.backdropFilter = 'blur(8px)';
            } else {
                nav.style.background = '';
                nav.style.backdropFilter = '';
            }
        };
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    // ----------------------------------------
    // Intersection Observer : fade-up au scroll
    // (pour les éléments hors hero)
    // ----------------------------------------
    const scrollFadeEls = document.querySelectorAll('.feature-card, .stat-item, .birdnet-card');
    if ('IntersectionObserver' in window && scrollFadeEls.length) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity   = '1';
                    entry.target.style.transform = 'translateY(0)';
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });

        scrollFadeEls.forEach((el, i) => {
            el.style.opacity    = '0';
            el.style.transform  = 'translateY(24px)';
            el.style.transition = `opacity 0.5s ease ${i * 0.07}s, transform 0.5s ease ${i * 0.07}s`;
            observer.observe(el);
        });
    }

});