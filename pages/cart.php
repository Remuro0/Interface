<?php
session_start();
require_once '../auth.php';
requireAuth();

if ($_SESSION['role'] !== 'user') {
    header("Location: ../index.php");
    exit;
}

// Подключаем PHPMailer — ДО любой логики
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
require_once __DIR__ . '/../vendor/autoload.php';

require_once '../config.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Загружаем элементы корзины с данными
    $stmt = $pdo->prepare("
        SELECT c.id AS cart_id, c.type, c.service_id, c.package_id,
               s.name AS service_name, s.price AS service_price,
               p.name AS package_name, p.price AS package_price
        FROM cart c
        LEFT JOIN services s ON c.service_id = s.id AND c.type = 'service'
        LEFT JOIN tariff_plans p ON c.package_id = p.id AND c.type = 'package'
        WHERE c.user_id = ?
        ORDER BY c.added_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Получаем данные пользователя (email)
    $stmt_user = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt_user->execute([$_SESSION['user_id']]);
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Ошибка БД.");
}

// Логика оформления заказа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkout') {
    $email = trim($_POST['email'] ?? '');
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['message'] = "❌ Укажите корректный email.";
        header("Location: cart.php");
        exit;
    }

    $_SESSION['pending_email'] = $email;

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->prepare("SELECT last_verification_code_sent_at FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $last_sent = $stmt->fetchColumn();

        if ($last_sent) {
            // Пользователь уже подтверждал email — пропускаем этап
            $_SESSION['verification_code'] = 'ALREADY_VERIFIED';
            header("Location: verify_email.php");
            exit;
        }

        // Генерируем и отправляем код
        $verification_code = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 10);
        $_SESSION['verification_code'] = $verification_code;

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.mail.ru';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'it-servis-05@mail.ru';
            $mail->Password   = 'w03ZqhoC4P0F83CCpO94';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom('it-servis-05@mail.ru', 'ИТ-Сервис');
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = 'Подтверждение email — ИТ-Сервис';
            $mail->Body    = "
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <style>
                        body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
                        .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); overflow: hidden; }
                        .header { background: #f8f8f8; padding: 20px; text-align: center; }
                        .content { padding: 30px; }
                        .code-box { background: #f0f0f0; border-radius: 8px; padding: 20px; text-align: center; font-size: 24px; font-weight: bold; color: #333; }
                        .footer { background: #f8f8f8; padding: 15px; text-align: center; font-size: 12px; color: #666; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>ИТ-Сервис</h2>
                        </div>
                        <div class='content'>
                            <h2>Код подтверждения</h2>
                            <div class='code-box'>{$verification_code}</div>
                            <p>Для подтверждения входа используйте код ниже.</p>
                            <p><strong>Никому не сообщайте его! Код действует 10 минут.</strong></p>
                        </div>
                        <div class='footer'>
                            &copy; " . date('Y') . " ИТ-Сервис. Все права защищены.
                        </div>
                    </div>
                </body>
                </html>
            ";

            $mail->send();
            $stmt_update = $pdo->prepare("UPDATE users SET last_verification_code_sent_at = NOW() WHERE id = ?");
            $stmt_update->execute([$_SESSION['user_id']]);

            header("Location: verify_email.php");
            exit;

        } catch (Exception $e) {
            error_log("Ошибка отправки: " . $mail->ErrorInfo);
            $_SESSION['message'] = "❌ Не удалось отправить письмо.";
            header("Location: cart.php");
            exit;
        }

    } catch (PDOException $e) {
        $_SESSION['message'] = "❌ Ошибка при проверке даты отправки.";
        header("Location: cart.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Моя корзина</title>
    <link rel="stylesheet" href="../css/guest.css">
    <link rel="stylesheet" href="../css/userbar.css">
    <link rel="stylesheet" href="../css/cart.css">
</head>
<body>
    <!-- Панель пользователя -->
    <div class="user-panel">
        <img src="<?= htmlspecialchars($_SESSION['avatar'] ?? '../imang/default.png') ?>" alt="Аватарка">
        <div class="user-info">
            <strong><?= htmlspecialchars($_SESSION['username'] ?? 'Пользователь') ?></strong>
        </div>
        <div class="user-menu">
            <a href="user_dashboard.php">Главная</a>
            <a href="purchased.php">Мои покупки</a>
            <a href="history.php">История</a>
            <a href="support.php">Поддержка</a>
            <a href="edit_profile.php">Профиль</a>
            <a href="billing.php">Биллинг</a>
            <a href="notifications.php">Уведомления</a>
            <a href="referral.php">Рефералы</a>
            <a href="../logout.php">Выход</a>
        </div>
    </div>

    <div class="content-wrapper">
        <h1 style="text-align: center; color: #c7b8ff;">Моя корзина</h1>

        <?php if (empty($items)): ?>
            <div class="empty-cart">Корзина пуста</div>
        <?php else: ?>
            <?php $total = 0; ?>
            <?php foreach ($items as $item): ?>
                <div class="cart-item">
                    <div>
                        <?php if ($item['type'] === 'service'): ?>
                            <h3><?= htmlspecialchars($item['service_name']) ?></h3>
                            <div class="price"><?= number_format($item['service_price'], 0, '', ' ') ?> ₽</div>
                        <?php else: ?>
                            <h3>📦 Пакет: <?= htmlspecialchars($item['package_name']) ?></h3>
                            <div class="price"><?= number_format($item['package_price'], 0, '', ' ') ?> ₽</div>
                        <?php endif; ?>
                    </div>
                    <a href="../actions/remove_from_cart.php?action=single&id=<?= (int)$item['cart_id'] ?>" 
                       class="remove-btn" 
                       title="Удалить"
                       onclick="return confirm('Удалить этот товар из корзины?')">×</a>
                </div>
                <?php $total += ($item['type'] === 'service') ? $item['service_price'] : $item['package_price']; ?>
            <?php endforeach; ?>

            <div class="total">Итого: <?= number_format($total, 0, '', ' ') ?> ₽</div>

            <a href="../actions/remove_from_cart.php?action=all" 
               class="clear-cart"
               onclick="return confirm('Очистить всю корзину? Это действие нельзя отменить.')">
                🗑️ Очистить корзину
            </a>

            <form method="POST" class="checkout-form">
                <input type="hidden" name="action" value="checkout">
                <label for="email">Email для получения кода подтверждения:</label>
                <input type="email" id="email" name="email" 
                       value="<?= !empty($user['email']) && $user['email'] !== 'no-email@example.com' ? htmlspecialchars($user['email']) : '' ?>"
                       placeholder="your@email.com"
                       required>
                <button type="submit">Приобрести</button>
            </form>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 30px;">
            <a href="services.php" class="btn" style="background: linear-gradient(to right, #3a0d6a, #5a1a8f); color: white;">Продолжить покупки</a>
        </div>
    </div>
</body>
</html>