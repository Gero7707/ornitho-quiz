<?php
/**
 * Classe MailService — Service d'envoi d'emails via PHPMailer
 * Couche d'abstraction entre les controllers et PHPMailer
 * Permet de changer de provider SMTP en modifiant uniquement les variables d'environnement
 *
 * Variables d'environnement requises dans .env et docker-compose.yml :
 *   MAILTRAP_HOST, MAILTRAP_PORT, MAILTRAP_USERNAME, MAILTRAP_PASSWORD
 *
 * En développement : Mailtrap Sandbox intercepte les emails sans les envoyer vraiment
 * En production : remplacer les credentials par ceux du provider choisi (Brevo, Mailgun etc.)
 *
 * Usage dans un controller :
 *   $this->mailService->sendEmail($to, $subject, $body);
 */

use PHPMailer\PHPMailer\PHPMailer;

// Autoloader Composer — charge PHPMailer et toutes les dépendances installées via composer require
require_once __DIR__ . '/../../vendor/autoload.php';

class MailService{

    /**
     * Instance PHPMailer — configurée dans le constructeur
     * true en paramètre active le mode exception (erreurs SMTP lèvent des exceptions)
     */
    private PHPMailer $mail;

    /**
     * Initialise PHPMailer avec le mode exception activé
     * La configuration SMTP est faite dans sendEmail() pour permettre
     * une éventuelle configuration dynamique par email
     */
    public function __construct(){
        $this->mail = new PHPMailer(true);
    }

    /**
     * Envoie un email via SMTP
     * Méthode générique et réutilisable — pas de contenu hardcodé
     * Le sujet et le corps sont définis par le controller appelant
     *
     * @param string $to      Adresse email du destinataire
     * @param string $subject Sujet de l'email
     * @param string $body    Corps de l'email (texte brut)
     */
    public function sendEmail(string $to, string $subject, string $body): void {
        $this->mail->clearAllRecipients();
        // Active le transport SMTP (par opposition au mail() natif PHP)
        $this->mail->isSMTP();

        // Credentials SMTP lus depuis les variables d'environnement
        $this->mail->Host     = getenv('MAIL_HOST');
        $this->mail->SMTPAuth = true;
        $this->mail->Username = getenv('MAIL_USER');
        $this->mail->Password = getenv('MAIL_PASS');
        $this->mail->Port     = getenv('MAIL_PORT');

        // Expéditeur affiché dans le client mail du destinataire
        // À personnaliser selon le projet
        $this->mail->setFrom('vince.63@outlook.fr', 'Vite & Gourmand');

        // Destinataire
        $this->mail->addAddress($to);
        
        $this->mail->isHTML(true);
        $this->mail->CharSet = 'UTF-8';
        $this->mail->Body = $this->wrapBody($body);
        // Contenu — subject et body passés en paramètres pour la réutilisabilité
        $this->mail->Subject = $subject;

        // Envoi — lève une PHPMailer\Exception en cas d'échec SMTP
        $this->mail->send();
    }

    private function wrapBody(string $message): string{
        $body = <<<HTML
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f4f4f4;">
                    <tr>
                        <td align="center" style="padding: 24px 12px;">

                            <!-- conteneur centré, largeur max 600px -->
                            <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 8px;">

                                <!-- en-tête -->
                                <tr>
                                    <td align="center" style="background-color: #1a2238; padding: 24px; border-radius: 8px 8px 0 0;">
                                        <span style="color: #d4af37; font-family: Arial, Helvetica, sans-serif; font-size: 24px; font-weight: bold;">Vite &amp; Gourmand</span>
                                    </td>
                                </tr>

                                <!-- corps -->
                                <tr>
                                    <td  style="padding: 32px 24px; font-family: Arial, Helvetica, sans-serif; font-size: 16px; line-height: 1.5; color: #333333;">
                                    <!-- ton message ici -->
                                        {$message}
                                    </td>
                                </tr>

                                <!-- pied -->
                                <tr>
                                    <td align="center" style="padding: 16px 24px; font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #888888;">
                                        Vite &amp; Gourmand — Bordeaux
                                    </td>
                                </tr>

                            </table>

                        </td>
                    </tr>
                </table>
            HTML;
        return $body;
    }
}