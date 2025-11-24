<?php
session_start();
require_once '../auth.php';
requireAuth();
require_once '../log_action.php';

if ($_SESSION['role'] !== 'user') {
    $_SESSION['message'] = "❌ Доступ запрещён.";
    header("Location: ../index.php");
    exit;
}

require_once '../config.php';

$error = '';
$success = '';

// Проверяем, был ли уже подтверждён email
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("SELECT last_verification_code_sent_at FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $last_sent = $stmt->fetchColumn();

    // Если дата есть — значит, email уже подтверждён
    if ($last_sent) {
        $_SESSION['verification_code'] = 'ALREADY_VERIFIED';
        header("Location: payment_method.php");
        exit;
    }

} catch (PDOException $e) {
    // Продолжаем обычную логику при ошибке
}

// Если дошли сюда — email НЕ подтверждён, нужно ввести код
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['code'])) {
    $entered_code = trim($_POST['code'] ?? '');

    if (empty($entered_code)) {
        $error = "❌ Код подтверждения обязателен.";
    } elseif ($entered_code === ($_SESSION['verification_code'] ?? '')) {
        try {
            $email = $_SESSION['pending_email'] ?? '';
            if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
                $stmt->execute([$email, $_SESSION['user_id']]);
                $_SESSION['email'] = $email;

                logAction($pdo, $_SESSION['user_id'], $_SESSION['username'], 'EMAIL_VERIFIED', "Email: $email");
            }

            // Очищаем временные данные
            unset($_SESSION['verification_code']);
            unset($_SESSION['pending_email']);

            header("Location: payment_method.php");
            exit;

        } catch (PDOException $e) {
            $error = "❌ Ошибка при сохранении email.";
        }
    } else {
        $error = "❌ Неверный код подтверждения.";
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Подтвердите Email</title>
    <link rel="stylesheet" href="../css/guest.css">
    <link rel="stylesheet" href="../css/userbar.css">
    <link rel="stylesheet" href="../css/verify_email.css">
</head>
<body>
    <div class="user-panel">
        <img src="<?= htmlspecialchars($_SESSION['avatar'] ?? '../imang/default.png') ?>" alt="Аватар">
        <div class="user-info">
            <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>
        </div>
        <div style="margin-left: auto;">
            <a href="../index.php" style="color: #e0e0e0; text-decoration: none; margin-left: 15px;">Главная</a>
            <a href="edit_profile.php" style="color: #e0e0e0; text-decoration: none; margin-left: 15px;">Профиль</a>
            <a href="../logout.php" style="color: #ff6b6b; text-decoration: none; margin-left: 15px;">Выход</a>
        </div>
    </div>

    <div class="content-wrapper">
        <div class="verification-box">
            <h2>Подтвердите Email</h2>
            <p>На ваш email был отправлен код подтверждения. Введите его ниже.</p>
            <?php if ($error): ?>
                <div class="error-message">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            <form method="POST">
                <input type="text" name="code" placeholder="Введите код подтверждения" required
                       style="width: 100%; padding: 10px; background: #1e192d; border: 1px solid #5a1a8f; color: white; border-radius: 6px; margin: 10px 0;">
                <button type="submit"
                        style="background: linear-gradient(to right, #00c853, #64dd17); color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer;">
                    Подтвердить
                </button>
            </form>
        </div>
    </div>
</body>
</html>