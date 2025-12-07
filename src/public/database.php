<?php
// Проверяем, запущена ли сессия, перед тем как запускать
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Подключаем зависимости Composer (для PHPMailer и Excel)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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

            // Настройки сервера (Mailhog)
            $mail->isSMTP();
            $mail->Host = 'mailhog';
            $mail->Port = 1025;
            $mail->SMTPAuth = false;
            $mail->CharSet = 'UTF-8';

            // От кого
            $mail->setFrom('noreply@computershop.com', 'Computer Shop'); // Лучше использовать название магазина
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = 'Код подтверждения регистрации';

            // === КРАСИВЫЙ HTML ШАБЛОН ===
            $mail->Body = "
            <!DOCTYPE html>
            <html lang='ru'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Подтверждение Email</title>
                <style>
                    body { margin: 0; padding: 0; background-color: #f4f6f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
                    .email-wrapper { width: 100%; background-color: #f4f6f9; padding: 40px 0; }
                    .email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
                    .email-header { background-color: #0156FF; padding: 30px; text-align: center; }
                    .email-header h1 { color: #ffffff; margin: 0; font-size: 24px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
                    .email-body { padding: 40px 30px; color: #333333; line-height: 1.6; font-size: 16px; }
                    .code-box { display: block; width: fit-content; margin: 30px auto; padding: 15px 40px; background-color: #f0f7ff; border: 2px dashed #0156FF; border-radius: 8px; font-size: 36px; font-weight: 700; color: #0156FF; letter-spacing: 8px; text-align: center; }
                    .email-footer { background-color: #f8f9fa; padding: 20px; text-align: center; color: #888888; font-size: 13px; border-top: 1px solid #eeeeee; }
                    .email-footer a { color: #0156FF; text-decoration: none; }
                    
                    /* Адаптив для телефонов */
                    @media only screen and (max-width: 600px) {
                        .email-body { padding: 20px; }
                        .code-box { font-size: 28px; padding: 10px 20px; letter-spacing: 4px; width: 100%; box-sizing: border-box; }
                    }
                </style>
            </head>
            <body>
                <div class='email-wrapper'>
                    <div class='email-container'>
                        <div class='email-header'>
                            <h1>Computer Shop</h1>
                        </div>
                        
                        <div class='email-body'>
                            <p style='margin-top: 0;'>Здравствуйте!</p>
                            <p>Спасибо за регистрацию в нашем магазине. Для завершения создания аккаунта и подтверждения вашей почты, пожалуйста, используйте код ниже:</p>
                            
                            <div class='code-box'>{$verification_code}</div>
                            
                            <p>Код действителен в течение <strong>1 часа</strong>.</p>
                            <p style='margin-bottom: 0;'>Если вы не запрашивали этот код, просто проигнорируйте это письмо.</p>
                        </div>
                        
                        <div class='email-footer'>
                            <p>&copy; " . date('Y') . " Computer Shop. Все права защищены.</p>
                            <p>Это автоматическое письмо, пожалуйста, не отвечайте на него.</p>
                        </div>
                    </div>
                </div>
            </body>
            </html>
            ";

            // Текстовая версия для старых клиентов
            $mail->AltBody = "Ваш код подтверждения: {$verification_code}\n\nВведите его на сайте для завершения регистрации.";

            $mail->send();
            return true;
            
        } catch (Exception $e) {
            error_log("Email error: " . $mail->ErrorInfo);
            return false;
        }
    }
    // Метод для отправки письма о подписке
    public function sendSubscriptionEmail($email) {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'mailhog';
            $mail->Port = 1025;
            $mail->SMTPAuth = false;
            $mail->CharSet = 'UTF-8';

            $mail->setFrom('newsletter@computershop.com', 'Computer Shop News');
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = 'Вы успешно подписались на рассылку!';

            // HTML Шаблон письма
            $mail->Body = "
            <!DOCTYPE html>
            <html lang='ru'>
            <head>
                <style>
                    body { font-family: 'Segoe UI', sans-serif; background-color: #f4f6f9; padding: 20px; }
                    .container { max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
                    .header { color: #0156FF; font-size: 24px; font-weight: bold; text-align: center; margin-bottom: 20px; }
                    .text { font-size: 16px; color: #333; line-height: 1.5; text-align: center; }
                    .footer { margin-top: 30px; font-size: 12px; color: #999; text-align: center; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>Спасибо за подписку!</div>
                    <div class='text'>
                        <p>Вы успешно подписались на новостную рассылку <strong>Computer Shop</strong>.</p>
                        <p>Теперь вы первыми узнаете о скидках на видеокарты, новых процессорах и специальных предложениях!</p>
                    </div>
                    <div class='footer'>
                        &copy; " . date('Y') . " Computer Shop. Вы получили это письмо, потому что подписались на нашем сайте.
                    </div>
                </div>
            </body>
            </html>
            ";

            $mail->send();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}



// Создаем глобальное подключение
try {
    $database = new Database();
    $pdo = $database->pdo;
} catch (Exception $e) {
    die("Ошибка инициализации БД");
}
