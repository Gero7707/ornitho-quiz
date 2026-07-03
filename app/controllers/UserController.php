<?php 
require_once __DIR__ . '/../models/UserModel.php';


class UserController{
    private UserModel $users;

    public function __construct(){
        $this->users = new UserModel();
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

        $user =$this->users->findByInput($input);

        if($user && password_verify($_POST['password'], $user['mot_de_passe_hash'])){
            // Régénérer l'id de session contre le session fixation
            session_regenerate_id(true);

            $_SESSION['utilisateur_id'] = $user['id'];
            $_SESSION['pseudo'] = $user['pseudo'];
            $_SESSION['email'] = $user['email'];

            $_SESSION['flash_bienvenue'] = true;

            $successMessage = "Bienvenu " . $_SESSION['pseudo'] ;
            header('location: /?success=' . urlencode($successMessage));
            exit();
        }else{
            $error = "Identifiants et mot de passe incorrects !";
            header('location: /login?error=' . urlencode($error));
            exit();
        }
    }
}