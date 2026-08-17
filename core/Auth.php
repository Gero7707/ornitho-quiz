<?php
/**
 * Classe Auth — Sécurité et protection des routes
 * Toutes les méthodes sont statiques pour être appelées sans instanciation
 * Utilisation : Auth::csrfField() etc.
 */

// Database.php chargé en dépendance core — disponible dans tout le projet via index.php
require_once __DIR__ . '/Database.php';

class Auth{

    // ============================================================
    // PROTECTION DES ROUTES
    // À appeler en première ligne de chaque méthode controller protégée
    // ============================================================

    private const INACTIVITY_LIMIT = 900;   // 15 min — poste non maîtrisé

/**
     * Expiration de session par inactivité (défense applicative garantie,
     * là où gc_maxlifetime n'offre qu'un nettoyage probabiliste).
     * Appelée par checkAuth/checkAdmin/checkEmploye une fois la connexion confirmée.
     */
    private static function enforceInactivityTimeout(): void {
        // Première requête authentifiée : on pose le marqueur, rien à expirer encore
        if (!isset($_SESSION['last_activity'])) {
            $_SESSION['last_activity'] = time();
            return;
        }
        $limite =self::INACTIVITY_LIMIT ;
        
        // Inactif depuis plus que la limite → session invalidée, retour au login
        if (time() - $_SESSION['last_activity'] > $limite) {
            session_unset();
            session_destroy();
            header('location: /login');
            exit();
        }

        // Requête valide → on repousse l'échéance
        $_SESSION['last_activity'] = time();
    }
    /**
     * Vérifie qu'un utilisateur est connecté
     * Redirige vers /auth/login si la session est absente
     * Usage : Auth::checkAuth(); en haut de chaque méthode protégée
     */
    public static function checkAuth(): void {
        if(!isset($_SESSION['utilisateur_id'])){
            header('location: /login');
            exit();
        }
        self::enforceInactivityTimeout();
    }


    // ============================================================
    // PROTECTION CSRF (Cross-Site Request Forgery)
    // Protège les formulaires contre les soumissions depuis des sites externes
    // Flux CSRF : csrfField() crée le token (paresseux) + l'injecte dans le form → verifyCsrfToken() le valide au POST
    // ============================================================


    /**
     * Vérifie la validité du token CSRF soumis avec le formulaire
     * hash_equals() utilisé à la place de === pour éviter les timing attacks
     * En cas d'échec : redirige vers l'URL $retour fournie par le controller 
     * À appeler en première ligne de chaque bloc POST
     */
    public static function verifyCsrfToken(): void {
        if (
            !isset($_POST['csrf_token']) ||
            !isset($_SESSION['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
        ) {
            $error = "Votre session a expiré ou le formulaire a été soumis plusieurs fois. Veuillez réessayer.";
            header('Location: ' . $_SERVER['HTTP_REFERER'] . '?error=' . urlencode($error));
            exit();
        }
    }

    /**
     * Génère le champ hidden à insérer dans chaque formulaire POST
     * Retourne une string HTML avec le token CSRF en valeur
     * Usage dans une vue : <?= Auth::csrfField() ?>
     */
    public static function csrfField(): string {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
    }

    public static function destroySession(): void {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }

        session_destroy();
    }
}