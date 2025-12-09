<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

class Database {
    private $host = 'mysql';
    private $dbname = 'app';
    private $username = 'root';
    private $password = 'root';
    public $pdo;

    public function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host={$this->host};dbname={$this->dbname}", 
                $this->username, 
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
            $this->pdo->exec("SET NAMES utf8mb4");
        } catch(PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            die("Ошибка подключения к базе данных");
        }
    }

    private function configureSMTP($mail) {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; 
        $mail->SMTPAuth = true;
        
        $mail->Username = 'artem2005225@gmail.com'; 
        $mail->Password = 'msgj xuhf ohka afjt'; 
        
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        
        $mail->setFrom('artem2005225@gmail.com', 'Магазин'); 
        
        $mail->CharSet = 'UTF-8';
    }

    public function addTransaction($user_id, $amount, $type, $description = '') {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO BalanceTransactions (user_id, amount, type, description) VALUES (?, ?, ?, ?)");
            return $stmt->execute([$user_id, $amount, $type, $description]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function sendVerificationEmail($email, $verification_code) {
        try {
            $mail = new PHPMailer(true);
            $this->configureSMTP($mail);

            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'Код подтверждения регистрации';

            $mail->Body = "
            <!DOCTYPE html>
            <html lang='ru'>
            <head>
                <style>
                    body { margin: 0; padding: 0; background-color: #f4f6f9; font-family: sans-serif; }
                    .email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; padding: 40px; text-align: center; border: 1px solid #eee; }
                    .code-box { display: inline-block; padding: 15px 40px; background-color: #f0f7ff; border: 2px dashed #0156FF; border-radius: 8px; font-size: 36px; font-weight: 700; color: #0156FF; letter-spacing: 5px; margin: 20px 0; }
                    .footer { margin-top: 20px; font-size: 12px; color: #999; }
                </style>
            </head>
            <body>
                <div style='padding: 40px 0;'>
                    <div class='email-container'>
                        <h2 style='color: #0156FF; margin-top: 0;'>Код подтверждения</h2>
                        <p>Используйте этот код для завершения регистрации в магазине Computer Shop:</p>
                        
                        <div class='code-box'>{$verification_code}</div>
                        
                        <p style='color: #555;'>Код действителен в течение 1 часа.</p>
                        <div class='footer'>Если вы не запрашивали код, просто проигнорируйте это письмо.</div>
                    </div>
                </div>
            </body>
            </html>";

            $mail->AltBody = "Ваш код: {$verification_code}";
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Mailer Error: " . $mail->ErrorInfo);
            return false;
        }
    }

    public function sendSubscriptionEmail($email) {
        try {
            $mail = new PHPMailer(true);
            $this->configureSMTP($mail);

            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'Вы успешно подписались на рассылку!';

            $mail->Body = "
            <!DOCTYPE html>
            <html lang='ru'>
            <head>
                <style>
                    body { font-family: sans-serif; background-color: #f4f6f9; padding: 20px; }
                    .container { max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; border: 1px solid #eee; }
                    .header { color: #0156FF; font-size: 24px; font-weight: bold; text-align: center; margin-bottom: 20px; }
                    .text { color: #333; line-height: 1.6; text-align: center; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>Спасибо за подписку!</div>
                    <div class='text'>
                        <p>Вы успешно подписались на новостную рассылку <strong>Computer Shop</strong>.</p>
                        <p>Теперь вы первыми узнаете о скидках на видеокарты, новых процессорах и специальных предложениях!</p>
                        <p style='margin-top: 30px; color: #888; font-size: 12px;'>Вы получили это письмо, потому что указали свой Email на нашем сайте.</p>
                    </div>
                </div>
            </body>
            </html>
            ";

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Mailer Error: " . $mail->ErrorInfo);
            return false;
        }
    }
}

try {
    $database = new Database();
    $pdo = $database->pdo;
} catch (Exception $e) {
    die("Ошибка инициализации БД");
}