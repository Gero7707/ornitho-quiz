<?php
// ============================================================
// CONFIGURATION DE LA SESSION
// Doit être appelé avant tout output et avant d'utiliser $_SESSION
// ============================================================

session_start([
    'cookie_httponly' => true,    // JavaScript ne peut pas lire le cookie de session — protection XSS
    'cookie_secure' => getenv('APP_ENV') === 'prod',    // Cookie envoyé uniquement en HTTPS — passe à true en production
    'cookie_samesite' => 'Strict', // Cookie non envoyé depuis un site externe — protection CSRF
    'use_strict_mode' => true,    // Refuse les id de session non générés par le serveur — protection session fixation
    'use_only_cookies' => true,   // Interdit les id de session dans l'URL — protection session hijacking
    'gc_maxlifetime' => 1800      // Session expire après 30 min d'inactivité
]);

require_once  __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Router.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../app/helpers/helpers.php';
require_once __DIR__ . '/../app/controllers/HomeController.php';
require_once __DIR__ . '/../app/controllers/OiseauController.php';
require_once __DIR__ . '/../app/controllers/SonController.php';
require_once __DIR__ . '/../app/controllers/QuizController.php';
require_once __DIR__ . '/../app/controllers/BirdnetController.php';
require_once __DIR__ . '/../app/controllers/UserController.php';

// require_once __DIR__ . '/../app/controllers/ThematiqueController.php';

$router = new Router();

$router->add('GET', '/','HomeController',  'index');

$router->add('GET', '/oiseaux', 'OiseauController','index');

$router->add('GET', '/sons', 'SonController','getBySon');

$router->add('GET', '/oiseau/:id', 'OiseauController', 'show');

$router->add('GET' , '/quiz/jeu1', 'QuizController', 'jeu1');

$router->add('GET', '/quiz', 'QuizController', 'accueilQuiz');

$router->add('POST', '/quiz/valider' , 'QuizController', 'valider');

$router->add('POST', '/quiz/start','QuizController',  'start');

$router->add('GET', '/quiz/fin', 'QuizController', 'fin');

$router->add('GET',  '/quiz/jeu2',        'QuizController', 'jeu2');

$router->add('POST', '/quiz/validerJeu2', 'QuizController', 'validerJeu2');

$router->add('GET',  '/quiz/jeu3',        'QuizController', 'jeu3');

$router->add('POST', '/quiz/validerJeu3', 'QuizController', 'validerJeu3');

$router->add('GET',  '/quiz/jeu4',        'QuizController', 'jeu4');

$router->add('POST', '/quiz/validerJeu4', 'QuizController', 'validerJeu4');

$router->add('GET', '/quiz/resultat', 'QuizController', 'resultat');

$router->add('GET', '/identifier', 'BirdnetController', 'index');

$router->add('GET', '/birdnet/match', 'BirdnetController', 'matchSpecies');

// $router->add('POST','/quiz/:niveau','QuizController',  'repondre');
// $router->add('GET', '/thematiques', 'ThematiqueController','index');

$router->add('GET' , '/login' , 'UserController' , 'showLogin');
$router->add('POST' , '/login' , 'UserController' , 'login');

$url = strtok($_SERVER['REQUEST_URI'], '?');

$router->dispatch($url);

?>