<?php
session_start();
if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['role']) {
        case 'user':
            header("Location: pages/user_dashboard.php");
            break;
        case 'engineer':
            header("Location: pages/engineer_dashboard.php");
            break;
        case 'manager':
            header("Location: pages/manager_dashboard.php"); // ← исправлено
            break;
        case 'db_admin':
            header("Location: pages/db_admin_dashboard.php");
            break;
        case 'admin':
            header("Location: pages/view_db.php");
            break;
        default:
            header("Location: index.php");
    }
    exit;
}

// Подключение к БД
require_once 'config.php'; // ← config.php в корне
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ошибка подключения к БД.");
}

// Загрузка услуг с id 9, 10, 11, 12
$stmt = $pdo->query("SELECT * FROM services WHERE id IN (9, 10, 11, 12)");
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>ИТ-Сервис — Добро пожаловать</title>
    <link rel="stylesheet" href="css/index.css">
</head>
<body>
    <div class="header">
        <h1>Добро пожаловать в ИТ-Сервис</h1>
        <p>Профессиональное управление инфраструктурой, безопасность и поддержка 24/7</p>
    </div>

    <div class="services-grid">
        <?php foreach ($services as $service): ?>
            <div class="service-card">
                <h3><?= htmlspecialchars($service['name']) ?></h3>
                <p><?= htmlspecialchars($service['description']) ?></p>
            </div>
        <?php endforeach; ?>
        <!-- 5-я карточка — переход на страницу всех услуг -->
        <div class="service-card">
            <h3>Все услуги</h3>
            <p>Посмотрите полный список наших услуг.</p>
            <a href="/login.php" style="background: linear-gradient(to right, #00c853, #64dd17); color: white; text-decoration: none; margin-top: 10px; display: inline-block; padding: 10px 20px; border-radius: 6px;">Перейти</a>
        </div>
    </div>

    <div class="support-section">
        <h2>🛠️ Техническая поддержка</h2>
        <p>Нужна помощь? Мы всегда на связи!</p>
        <div class="support-info">
            <div class="contact-item">
                <span class="icon">📧</span>
                <span class="text">support@it-service.com</span>
            </div>
            <div class="contact-item">
                <span class="icon">📞</span>
                <span class="text">+7 (999) 123-45-67</span>
            </div>
            <div class="contact-item">
                <span class="icon">🕖</span>
                <span class="text">Работаем 24/7</span>
            </div>
        </div>
    </div>

    <div class="actions">
        <a href="login.php" class="btn btn-login">Войти в систему</a>
        <a href="register.php" class="btn btn-register">Зарегистрироваться</a>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>