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
    public static function verifyCsrfToken(string $retour = '/'): void {
        if (
            !isset($_POST['csrf_token']) ||
            !isset($_SESSION['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
        ) {
            $error = "Votre session a expiré ou le formulaire a été soumis plusieurs fois. Veuillez réessayer.";
            header('Location: ' . $retour . '?error=' . urlencode($error));
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
}