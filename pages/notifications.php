<?php
session_start();
require_once '../auth.php';
requireAuth();

if ($_SESSION['role'] !== 'user') {
    $_SESSION['message'] = "❌ Доступ запрещён.";
    header("Location: ../index.php");
    exit;
}

require_once '../config.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Загружаем уведомления пользователя
    $stmt = $pdo->prepare("
        SELECT id, type, title, message, created_at, is_read
        FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Ошибка БД: " . htmlspecialchars($e->getMessage()));
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Уведомления</title>
    <link rel="stylesheet" href="../css/guest.css">
    <link rel="stylesheet" href="../css/userbar.css">
    <link rel="stylesheet" href="../css/notifications.css">
</head>
<body>
    <!-- Панель пользователя -->
    <div class="user-panel">
        <img src="<?= htmlspecialchars($_SESSION['avatar'] ?? '../imang/default.png') ?>" alt="Аватарка">
        <div class="user-info">
            <strong><?= htmlspecialchars($_SESSION['username'] ?? 'Пользователь') ?></strong>
        </div>
        <div class="user-menu">
            <a href="services.php">Услуги</a>
            <a href="cart.php">Корзина</a>
            <a href="purchased.php">Мои покупки</a>
            <a href="history.php">История</a>
            <a href="support.php">Поддержка</a>
            <a href="edit_profile.php">Профиль</a>
            <a href="billing.php">Биллинг</a>
            <a href="referral.php">Рефералы</a>
            <a href="../logout.php">Выход</a>
        </div>
    </div>

    <div class="content-wrapper" style="padding: 0 20px;">
        <h1 style="text-align: center; color: #c7b8ff;">Уведомления</h1>

        <?php if (empty($notifications)): ?>
            <div style="text-align: center; color: #aaa; font-size: 1.2rem; margin: 60px 0;">
                У вас пока нет уведомлений.
            </div>
        <?php else: ?>
            <div class="notifications-list">
                <?php foreach ($notifications as $notif): ?>
                    <div class="notification-item <?= $notif['is_read'] ? '' : 'unread' ?>">
                        <div class="notification-type"><?= htmlspecialchars($notif['type']) ?></div>
                        <div class="notification-title"><?= htmlspecialchars($notif['title']) ?></div>
                        <div class="notification-content"><?= nl2br(htmlspecialchars($notif['message'])) ?></div>
                        <div class="notification-date"><?= htmlspecialchars(date('d.m.Y H:i', strtotime($notif['created_at']))) ?></div>
                        <?php if (!$notif['is_read']): ?>
                            <div class="mark-read" onclick="markAsRead(<?= (int)$notif['id'] ?>)">Отметить как прочитанное</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 30px;">
            <a href="user_dashboard.php" class="btn" style="background: linear-gradient(to right, #00c853, #64dd17); color: white;">Назад</a>
        </div>
    </div>

    <script>
        function markAsRead(id) {
            fetch('../api/mark_notification_read.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Ошибка при отметке уведомления.');
                    }
                })
                .catch(() => {
                    alert('Не удалось подключиться к серверу.');
                });
        }
    </script>
</body>
</html>ы