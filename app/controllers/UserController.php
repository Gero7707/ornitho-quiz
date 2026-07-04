<?php 
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../models/LoginAttemptsModel.php';


class UserController{
    private UserModel $users;

    private LoginAttemptModel $loginAttempts;

    public function __construct(){
        $this->users = new UserModel();
        $this->loginAttempts = new LoginAttemptModel();
    }

    public function showLogin(){
        require_once __DIR__ . '/../views/auth/login.php';
    }

    public function login(){
        Auth::verifyCsrfToken();

        $input = trim($_POST['login'] ?? '');

        // Valider que le champ n'est pas vide
        if (empty($input) || empty($_POST['password'])) {
            $error = "Veuillez remplir tous les champs !";
            header('location: /login?error=' . urlencode($error));
            exit();
        }
        $ip = $_SERVER['REMOTE_ADDR'];
        $attempts = $this->loginAttempts->getAttempts($ip);
        if(count($attempts) >= 5 ){
            $error = "Vous avez tenté de vous connecter plus de 5 fois sans succés , par sécurité vous devez réessayer ultérieurement !";
            header('location: /?error=' . urlencode($error));
            exit();
        }

        $user =$this->users->findByInput($input);

        if($user && password_verify($_POST['password'], $user['mot_de_passe_hash'])){
            // Régénérer l'id de session contre le session fixation
            session_regenerate_id(true);

            $_SESSION['utilisateur_id'] = $user['id'];
            $_SESSION['pseudo'] = $user['pseudo'];
            $_SESSION['email'] = $user['email'];
            $this->loginAttempts->resetAttempts($ip);
            
            $_SESSION['flash_bienvenue'] = true;

            $successMessage = "Bienvenu " . $_SESSION['pseudo'] ;
            header('location: /?success=' . urlencode($successMessage));
            exit();
        }else{
            $this->loginAttempts->addAttempt($ip);
            
            $error = "Identifiants et mot de passe incorrects !";
            header('location: /login?error=' . urlencode($error));
            exit();
        }
    }

    public function showLogout(){
        Auth::checkAuth();
        require_once __DIR__ . '/../views/auth/logout.php';
    }
    public function logOut(){
        Auth::verifyCsrfToken();          // 1. protection CSRF

        $_SESSION = [];                    // 2. vider les données en mémoire

        // 3. supprimer le cookie de session côté navigateur
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }

        session_destroy();                 // 4. détruire côté serveur

        header('Location: /');
        exit();
    }

    public function showProfil(){
        Auth::checkAuth();
        $id = $_SESSION['utilisateur_id'];
        $user = $this->users->findById($id);
        require_once __DIR__ . '/../views/auth/profil.php';
    }
}