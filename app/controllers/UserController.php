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
        Auth::verifyCsrfToken();          

        Auth::destroySession();

        header('Location: /');
        exit();
    }

    public function showProfil(){
        Auth::checkAuth();
        $id = $_SESSION['utilisateur_id'];
        $user = $this->users->findById($id);
        if (!$user) {
            Auth::destroySession();
            header('Location: /login?error=' . urlencode('Session expirée, veuillez vous reconnecter.'));
            exit;
        }
        require_once __DIR__ . '/../views/auth/profil.php';
    }

    public  function showRegister(){
        require_once __DIR__ . '/../views/auth/register.php';
    }

    public function register(){
        Auth::verifyCsrfToken();

        if (!isset($_POST['rgpd'])) {
            $error = "Vous devez accepter la politique de confidentialité.";
            header('Location: /register?error=' . urlencode($error));
            exit();
        }

        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        if (!$email) {
            $error = "L'adresse email n'est pas valide !";
            header('Location: /register?error=' . urlencode($error));
            exit();
        }

        $pseudo = trim($_POST['pseudo'] ?? '');
        if (empty($pseudo)) {
            $error = "Le pseudo est obligatoire !";
            header('Location: /register?error=' . urlencode($error));
            exit();
        }

        $password = $_POST['password'] ?? '';
        if ($password !== ($_POST['password_confirm'] ?? '')) {
            $error = "Les deux mots de passe ne correspondent pas !";
            header('Location: /register?error=' . urlencode($error));
            exit();
        }

        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d]).{12,}$/', $password)) {
            $error = "Le mot de passe doit contenir au moins 12 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.";
            header('Location: /register?error=' . urlencode($error));
            exit();
        }

        if ($this->users->findByEmail($email)) {
            $error = "Cet email est déjà utilisé !";
            header('Location: /register?error=' . urlencode($error));
            exit();
        }

        if ($this->users->findByPseudo($pseudo)) {
            $error = "Ce pseudo existe déjà, veuillez en choisir un autre.";
            header('Location: /register?error=' . urlencode($error));
            exit();
        }

        $data = [
            'email'    => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'pseudo'   => $pseudo,
        ];

        $this->users->createUser($data);

        $successMessage = "Votre compte a été créé avec succès.";
        header('Location: /login?success=' . urlencode($successMessage));
        exit();
    }
}