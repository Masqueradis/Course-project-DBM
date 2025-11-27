-- Создаем пользователя с правами доступа с любого хоста
CREATE USER IF NOT EXISTS 'user'@'%' IDENTIFIED BY 'password';
GRANT ALL PRIVILEGES ON app.* TO 'user'@'%';
GRANT ALL PRIVILEGES ON *.* TO 'user'@'%' WITH GRANT OPTION;
FLUSH PRIVILEGES;

-- Создаем таблицу для транзакций баланса
CREATE TABLE IF NOT EXISTS `BalanceTransactions` (
  `transaction_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `type` enum('deposit','purchase','refund') NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`transaction_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Добавляем поле баланса в таблицу Users
ALTER TABLE `Users` ADD COLUMN IF NOT EXISTS `balance` DECIMAL(10,2) DEFAULT 0.00 AFTER `role`;