<?php
// Стартуем сессию только если она еще не начата
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Подключаем автозагрузчик Composer
require_once __DIR__ . '/../../vendor/autoload.php';

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
            die("Ошибка подключения к базе данных: " . $e->getMessage());
        }
    }

    public function getUserBalance($user_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT balance FROM Users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['balance'] : 0;
        } catch (PDOException $e) {
            error_log("Error getting user balance: " . $e->getMessage());
            return 0;
        }
    }

    public function addTransaction($user_id, $amount, $type, $description = '') {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO BalanceTransactions (user_id, amount, type, description) VALUES (?, ?, ?, ?)");
            return $stmt->execute([$user_id, $amount, $type, $description]);
        } catch (PDOException $e) {
            error_log("Error adding transaction: " . $e->getMessage());
            return false;
        }
    }

    public function sendVerificationEmail($email, $verification_code) {
        try {
            $mail = new PHPMailer(true);

            // Настройки сервера
            $mail->isSMTP();
            $mail->Host = 'mailhog';  // Хост Mailhog в Docker сети
            $mail->Port = 1025;       // Порт Mailhog
            $mail->SMTPAuth = false;  // Mailhog не требует аутентификации
            $mail->CharSet = 'UTF-8';

            // Отправитель
            $mail->setFrom('noreply@computershop.com', 'Магазин компьютерных комплектующих');
            $mail->addAddress($email); // Получатель

            // Содержимое письма
            $mail->isHTML(true);
            $mail->Subject = 'Код подтверждения email';
            $mail->Body = "
                <!DOCTYPE html>
                <html lang='ru'>
                <head>
                    <meta charset='UTF-8'>
                    <title>Подтверждение email</title>
                    <style>
                        body { font-family: Arial, sans-serif; background-color: #f5f5f5; margin: 0; padding: 20px; }
                        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                        .header { text-align: center; margin-bottom: 30px; }
                        .code { font-size: 32px; font-weight: bold; color: #0156FF; text-align: center; margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 5px; }
                        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #666; font-size: 12px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>Подтверждение email адреса</h1>
                        </div>
                        <p>Здравствуйте!</p>
                        <p>Для завершения регистрации в магазине компьютерных комплектующих введите следующий код подтверждения:</p>
                        <div class='code'>{$verification_code}</div>
                        <p>Код действителен в течение <strong>1 часа</strong>.</p>
                        <p>Если вы не регистрировались в нашем магазине, просто проигнорируйте это письмо.</p>
                        <div class='footer'>
                            <p>С уважением,<br>Команда магазина компьютерных комплектующих</p>
                        </div>
                    </div>
                </body>
                </html>
            ";

            // Альтернативный текст для почтовых клиентов, которые не поддерживают HTML
            $mail->AltBody = "Код подтверждения: {$verification_code}\n\nДля завершения регистрации введите этот код на сайте.\nКод действителен в течение 1 часа.";

            $mail->send();
            error_log("Email sent successfully to: {$email}");
            return true;
            
        } catch (Exception $e) {
            error_log("PHPMailer Error: {$mail->ErrorInfo}");
            error_log("Failed to send email to: {$email}");
            return false;
        }
    }
}

// Создаем глобальное подключение
try {
    $database = new Database();
    $pdo = $database->pdo;
} catch (Exception $e) {
    error_log("Failed to initialize database: " . $e->getMessage());
    die("Не удалось инициализировать подключение к базе данных");
}
?>