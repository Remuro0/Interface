<?php
session_start();
require_once '../auth.php';
requireAuth();

if ($_SESSION['role'] !== 'user') {
    header("Location: ../index.php");
    exit;
}

require_once '../config.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Загружаем все покупки пользователя
    $stmt = $pdo->prepare("
        SELECT p.id, p.item_type, p.service_id, p.package_id, p.price, p.purchased_at,
               s.name AS service_name,
               t.name AS package_name
        FROM purchases p
        LEFT JOIN services s ON p.service_id = s.id
        LEFT JOIN tariff_plans t ON p.package_id = t.id
        WHERE p.user_id = ?
        ORDER BY p.purchased_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Ошибка БД: " . htmlspecialchars($e->getMessage()));
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>История покупок</title>
    <link rel="stylesheet" href="../css/guest.css">
    <link rel="stylesheet" href="../css/userbar.css">
    <link rel="stylesheet" href="../css/history.css">
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
            <a href="cart.php">Корзина</a>
            <a href="purchased.php">Мои покупки</a>
            <a href="support.php">Поддержка</a>
            <a href="edit_profile.php">Профиль</a>
            <a href="billing.php">Биллинг</a>
            <a href="referral.php">Рефералы</a>
            <a href="../logout.php">Выход</a>
        </div>
    </div>

    <div class="content-wrapper">
        <h1 style="text-align: center; color: #c7b8ff;">История покупок</h1>

        <?php if (empty($purchases)): ?>
            <div style="text-align: center; color: #aaa; font-size: 1.2rem; margin: 60px 0;">
                У вас пока нет покупок.
            </div>
        <?php else: ?>
            <div class="purchases-list">
                <?php foreach ($purchases as $purchase): ?>
                    <div class="purchase-item">
                        <h3>
                            <?= htmlspecialchars($purchase['item_type'] === 'service' ? $purchase['service_name'] : $purchase['package_name']) ?>
                        </h3>
                        <div class="purchase-info">
                            <span class="purchase-price">Цена: <?= number_format($purchase['price'], 0, '', ' ') ?> ₽</span>
                            <span class="purchase-date">Дата: <?= htmlspecialchars(date('d.m.Y H:i', strtotime($purchase['purchased_at']))) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 30px;">
            <a href="services.php" class="btn" style="background: linear-gradient(to right, #00c853, #64dd17); color: white;">Купить ещё</a>
        </div>
    </div>
</body>
</html>