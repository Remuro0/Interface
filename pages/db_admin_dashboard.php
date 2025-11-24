<?php
session_start();
require_once '../auth.php';
requireAuth();

// Только для db_admin
if ($_SESSION['role'] !== 'db_admin') {
    $_SESSION['message'] = "❌ Доступ запрещён.";
    header("Location: ../index.php");
    exit;
}

require_once '../config.php';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Получаем все таблицы
    $stmt = $pdo->query("SHOW TABLES");
    $tables = [];
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
} catch (PDOException $e) {
    die("Ошибка подключения к БД: " . htmlspecialchars($e->getMessage()));
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Панель DB-администратора</title>
    <link rel="stylesheet" href="../css/guest.css">
    <link rel="stylesheet" href="../css/userbar.css">
    <link rel="stylesheet" href="../css/db_admin_dashboard.css">
</head>
<body>

<!-- Панель пользователя -->
<div class="user-panel">
<img src="../<?= htmlspecialchars($_SESSION['avatar'] ?? '../imang/default.png') ?>" alt="Аватарка">
    <div class="user-info">
        <strong><?= htmlspecialchars($_SESSION['username']) ?></strong><br>
        <span class="role">Роль: <?= htmlspecialchars($_SESSION['role']) ?></span>
    </div>
    <div class="user-menu">
        <!-- Исправленные ссылки -->
        <a href="db_admin_dashboard.php">Главная</a>
        <a href="view_db.php">Просмотр БД</a>
        <a href="../actions/create_table_form.php">Создать таблицу</a>
        <a href="../actions/backup.php">Бэкап</a>
        <a href="sql_console.php">SQL-консоль</a>
        <a href="edit_profile.php">Профиль</a>
        <a href="../logout.php">Выход</a>
    </div>
</div>

<div class="content-wrapper">
    <h1 class="dashboard-header">Панель DB-администратора</h1>

    <div class="quick-actions">
        <a href="view_db.php" class="quick-btn btn-db">👁️‍ Просмотр БД</a>
        <a href="../actions/create_table_form.php" class="quick-btn btn-create">➕ Создать таблицу</a>
        <a href="../actions/backup.php" class="quick-btn btn-backup">💾 Бэкап</a>
        <a href="sql_console.php" class="quick-btn btn-sql">💻 SQL-консоль</a>
    </div>

    <div class="tables-section">
        <h3>Все таблицы</h3>
        <?php if (empty($tables)): ?>
            <p style="text-align: center; color: #aaa;">Нет таблиц в базе данных.</p>
        <?php else: ?>
            <div class="tables-grid">
                <?php foreach ($tables as $table): ?>
                    <a href="view_db.php?table=<?= urlencode($table) ?>" class="table-link">
                        <?= htmlspecialchars($table) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../footer.php'; ?>

</body>
</html>