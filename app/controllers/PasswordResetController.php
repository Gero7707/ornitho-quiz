<?php
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../services/MailService.php';


class PasswordResetController{
    private UserModel $users;
    private MailService $mailService;

    public function __construct(){
        $this->users = new UserModel();
        $this->mailService = new MailService();
    }

    public function forgotPasswordForm(){
        require_once __DIR__ . '/../views/auth/forgotPassword.php';
    }

    public function forgotPassword(){
        Auth::verifyCsrfToken();
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        if (!$email ){
            $error = "L'adresse email n'est pas valide !";
            header('Location: /forgot-password?error=' . urlencode($error));
            exit();
        }

        $findEmail = $this->users->findByEmail($email);
        
        if($findEmail){
            $subject = "Réinitialisation de mot de passe .";
            $token = bin2hex(random_bytes(32));
            $lien = getenv('APP_URL') .'/reset-password?token='. $token;
            $bouton = '
            <div style="text-align: center; padding: 24px 0;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td align="center" style="background-color: #d4af37; border-radius: 6px;">
                        <a href="' . $lien . '" target="_blank"
                            style="display: inline-block; padding: 14px 28px; font-family: Arial, Helvetica, sans-serif; font-size: 16px; font-weight: bold; color: #1a2238;text-decoration: none;">
                            Changer Mot de passe
                        </a>
                        </td>
                    </tr>
                </table>
            </div>';

            $imageHaut = '<img src="https://ornitho-quiz.fr/assets/img/email-haut.jpg" 
                    alt="Vite &amp; Gourmand" 
                    width="600" 
                    style="display: block; width: 100%; max-width: 600px; height: auto; border: 0;">';

            $imageBas = '<img src="https://ornitho-quiz.fr/assets/img/email-bas.jpg" 
                    alt="Vite &amp; Gourmand" 
                    width="600" 
                    style="display: block; width: 100%; max-width: 600px; height: auto; border: 0;">';
            $conclusion = '<p>Cliquez sur ce lien : </p> ';
            $body =$imageHaut . $conclusion . $bouton   . $imageBas;
            $expires = date('Y-m-d H:i:s', time() + 3600);
            $this->users->saveResetToken($email, $token, $expires);
            $this->mailService->sendEmail($email,$subject,$body);

            $successMessage = "Lien lien de réinitialisation a été envoyé ! Verifiez votre boîte de reception .";
            header('location: /login?success='  . urlencode($successMessage));
            exit();
        }else{
            $error = "Email n'existe pas en base de données !";
            header('Location: /forgot-password?error=' . urlencode($error));
            exit();
        }
    }


    public function resetPasswordForm(){
        $token = $_GET['token'] ?? '';
        require_once __DIR__ . '/../views/auth/resetPassword.php';
    }
    public function resetPassword(){
        Auth::verifyCsrfToken();
        $token = $_POST['token'];
        $user = $this->users->findByResetToken($token);
        if(!$user){
            $error = "Cet utilisateur n'existe pas ! Merci de créer un compte valide ! ";
            header('location: /reset-password?error=' . urlencode($error));
            exit();
        }
        if($user['reset_token_expires_at'] < date('Y-m-d H:i:s', time())){
            $error = "Votre token a expiré !";
            header('location: /reset-password?error=' . urlencode($error));
            exit();
        }else{
            if($_POST['password'] !== $_POST['password_confirm']){
                $error = "Les deux mots de passe ne correspondent pas !";
                header('Location: /reset-password?error=' . urlencode($error));
                exit();
            }
            if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d]).{10,}$/', $_POST['password'])) {
                //(?=.*[a-z]) → contient au moins une minuscule
                // (?=.*[A-Z]) → contient au moins une majuscule
                // (?=.*\d) → contient au moins un chiffre
                // (?=.*[^a-zA-Z\d]) → contient au moins un caractère qui n'est ni lettre ni chiffre (= spécial)
                // .{10,} → 12 caractères minimum
                // ^ et $ → du début à la fin de la chaîne
                $error = "Le mot de passe doit contenir au moins 12 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.";
                header('Location: /reset-password?error=' . urlencode($error));
                exit();
            }

            $hashedPassword= password_hash($_POST['password'], PASSWORD_BCRYPT);
            $id = $user['id'] ;
            $this->users->updatePassword($id , $hashedPassword);
            $this->users->clearResetToken($id);
            $email = $user['email'];
            $subject = "Mot de passe réinitialsé !";
            $imageHaut = '<img src="https://ornitho-quiz.fr/assets/img/email-haut.jpg" 
                    alt="Vite &amp; Gourmand" 
                    width="600" 
                    style="display: block; width: 100%; max-width: 600px; height: auto; border: 0;">';

            $imageBas = '<img src="https://ornitho-quiz.fr/assets/img/email-bas.jpg" 
                    alt="Vite &amp; Gourmand" 
                    width="600" 
                    style="display: block; width: 100%; max-width: 600px; height: auto; border: 0;">';
            $conclusion = "Vous avez réinitialsé votre mot de passe avec succès ! ";
            $body =$imageHaut . $conclusion  . $imageBas;
            $this->mailService->sendEmail($email,$subject,$body);
            $succesMessage = "Vous avez réinitialsé votre mot de passe avec succès !";
            header('location: /login?success=' . urlencode($succesMessage));
            exit();
        }
    }
}