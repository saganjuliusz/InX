<?php
// mail_handler.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

class MailHandler {
    private $smtpHost;
    private $smtpUser;
    private $smtpPass;
    private $smtpPort;
    
    public function __construct() {
        $this->smtpHost = SMTP_HOST;
        $this->smtpUser = SMTP_USER;
        $this->smtpPass = SMTP_PASS;
        $this->smtpPort = SMTP_PORT;
    }
    
    public function wyslijMailResetowaniaHasla($email, $token) {
        $to = $email;
        $subject = 'Reset hasła - InX Music';
        
        $link = "https://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=" . $token;
        
        $message = "
        <html>
        <head>
            <title>Reset hasła</title>
        </head>
        <body>
            <h2>Reset hasła</h2>
            <p>Otrzymaliśmy prośbę o zresetowanie hasła do Twojego konta.</p>
            <p>Aby zresetować hasło, kliknij w poniższy link:</p>
            <p><a href='$link'>$link</a></p>
            <p>Link jest ważny przez 1 godzinę.</p>
            <p>Jeśli nie prosiłeś o reset hasła, zignoruj tę wiadomość.</p>
        </body>
        </html>
        ";

        return $this->wyslijEmail($to, $subject, $message);
    }
    
    public function wyslijPowiadomienieZmianyHasla($email) {
        $to = $email;
        $subject = 'Zmiana hasła - InX Music';
        
        $message = "
        <html>
        <head>
            <title>Zmiana hasła</title>
        </head>
        <body>
            <h2>Zmiana hasła</h2>
            <p>Twoje hasło zostało pomyślnie zmienione.</p>
            <p>Jeśli nie dokonywałeś tej zmiany, natychmiast skontaktuj się z administracją.</p>
        </body>
        </html>
        ";

        return $this->wyslijEmail($to, $subject, $message);
    }
    
    private function wyslijEmail($to, $subject, $message) {
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=utf-8',
            'From: InX Music <' . $this->smtpUser . '>',
            'Reply-To: ' . $this->smtpUser,
            'X-Mailer: PHP/' . phpversion()
        ];

        try {
            if (mail($to, $subject, $message, implode("\r\n", $headers))) {
                error_log("Email wysłany pomyślnie do: " . $to);
                return true;
            } else {
                error_log("Błąd wysyłania emaila do: " . $to);
                return false;
            }
        } catch (Exception $e) {
            error_log("Wyjątek podczas wysyłania emaila: " . $e->getMessage());
            return false;
        }
    }
}
?>