const canvas = document.getElementById('graphiqueQuiz');
const statsParJeu = JSON.parse(canvas.dataset.stats);
const nomsJeux = {
    jeu1: 'Trouver l\'intrus',
    jeu2: 'Chant ou cris?',
    jeu3: 'Son de l\'oiseau',
    jeu4: 'Nom de l\'oiseau'
};

const labels = Object.keys(statsParJeu).map(jeu => nomsJeux[jeu]);
const data   =  Object.values(statsParJeu).map(item => item.pourcentage);

const estMobile = window.innerWidth < 768;
const axeValeurs = estMobile ? 'x' : 'y';
const axeCategories = estMobile ? 'y' : 'x';

const scalesConfig = {
    [axeValeurs]: {
        min: 0,
        max: 100,
        ticks: {
            font: { size: 14 }
        }
    },
    [axeCategories]: {
        ticks: {
            autoSkip: false,
            font: { size: 14 }
        }
    }
};

new Chart(canvas, {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [{
            label: 'Pourcentage de bonnes réponses',
            data: data,
            backgroundColor: '#3d6b52',
            hoverBackgroundColor: '#2a4a3a'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: estMobile ? 'y' : 'x',
        scales: scalesConfig
    }
});