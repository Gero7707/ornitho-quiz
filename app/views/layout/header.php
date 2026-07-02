<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OrnithoQuiz</title>

    <meta name="description" content="Quiz ludiques sur les oiseaux pour tester vos connaissances en ornithologie. Découvrez des sons d'oiseaux, des images et des faits fascinants sur nos amis à plumes !">
    <meta name="robots" content="index, follow">
    <meta name="theme-color" content="#1c612d">

    <meta property="og:title" content="OrnithoQuiz">
    <meta property="og:description" content="Quiz ludiques sur les oiseaux pour tester vos connaissances en ornithologie. Découvrez des sons d'oiseaux, des images et des faits fascinants sur nos amis à plumes !">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://ornitho-quiz.fr">
    <meta property="og:image" content="https://ornitho-quiz.fr/assets/img/og-image.png">
    <meta property="og:image:width" content="1024">
    <meta property="og:image:height" content="1024">
    <meta property="og:image:type" content="image/png">

    <link rel="icon" type="image/png" href="/assets/img/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg" />
    <link rel="shortcut icon" href="/assets/img/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/img/apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="Ornithoquiz" />
    <link rel="manifest" href="/site.webmanifest" />

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,400&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="/assets/css/landing.css">
    <link rel="stylesheet" href="/assets/css/fiche.css">
    <link rel="stylesheet" href="/assets/css/quiz.css">
    <?php if (isset($pageCss) && $pageCss === 'birdnet'): ?>
        <link rel="stylesheet" href="/assets/css/birdnet.css">
    <?php endif; ?>
    <script>window.AUDIO_BASE_URL = "<?= $_ENV['AUDIO_BASE_URL'] ?? '' ?>";</script>
    <script defer src="/assets/js/audio.js"></script>
    <?php if (isset($pageCss) && $pageCss === 'birdnet'): ?>
        <script src="/assets/js/birdnet.js" defer></script>
    <?php endif; ?>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://kit.fontawesome.com/a5f2a52ad7.js" crossorigin="anonymous"></script>
</head>
<body>
