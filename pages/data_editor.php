<?php
session_start(); // ← Должно быть ДО requireAuth()
require_once '../auth.php';
requireAuth();
require_once '../log_action.php';

// === Настройки подключения ===
$host = 'localhost';
$dbname = 'local';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ошибка подключения: " . htmlspecialchars($e->getMessage()));
}

// === Получаем параметры ===
$id = (int)($_GET['id'] ?? 0);
$table = $_GET['table'] ?? '';

// === Проверка таблицы ===
if (!in_array($table, ['users', 'servers', 'network_devices', 'changes', 'incidents', 'backups', 'logs'])) {
    die("Недопустимая таблица.");
}

// === Получаем запись ===
$stmt = $pdo->prepare("SELECT * FROM `$table` WHERE id = ?");
$stmt->execute([$id]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$record) {
    die("Запись не найдена.");
}

// === Получаем список полей ===
$columns = array_keys($record);

// === Обработка формы ===
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update') {
    $set = [];
    $values = [];
    foreach ($columns as $col) {
        if ($col !== 'id') {
            $set[] = "`$col` = ?";
            $values[] = $_POST[$col] ?? '';
        }
    }
    $values[] = $id;

    $sql = "UPDATE `$table` SET " . implode(', ', $set) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute($values)) {
        $_SESSION['message'] = "✅ Запись обновлена.";
        logAction($pdo, $_SESSION['user_id'], $_SESSION['username'], 'EDIT_RECORD', "Таблица: $table, ID: $id");
    } else {
        $_SESSION['message'] = "❌ Ошибка при обновлении.";
    }
    header("Location: ../pages/view_db.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Редактировать — <?= htmlspecialchars($table) ?></title>
    <link rel="stylesheet" href="../css/view_db.css">
</head>
<body>

<?php if (!empty($_SESSION['message'])): ?>
    <div style="text-align: center; padding: 10px; background: rgba(40, 100, 40, 0.3); color: #aaffaa; margin: 10px auto; max-width: 600px; border-radius: 6px;">
        <?= htmlspecialchars($_SESSION['message']) ?>
    </div>
    <?php unset($_SESSION['message']); ?>
<?php endif; ?>

<div style="max-width: 800px; margin: 30px auto; background: rgba(30,25,45,0.95); padding: 20px; border-radius: 12px; border: 1px solid #5a1a8f;">
    <h2 style="text-align: center; color: #c7b8ff;">Редактировать запись в таблице: <?= htmlspecialchars($table) ?></h2>
    <form method="POST">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="<?= $id ?>">

        <?php foreach ($columns as $col): ?>
            <?php if ($col === 'id') continue; ?>
            <div style="margin: 12px 0;">
                <label style="display: block; color: #c7b8ff; margin-bottom: 4px;"><?= htmlspecialchars($col) ?>:</label>
                <input type="text" name="<?= $col ?>" value="<?= htmlspecialchars($record[$col]) ?>" 
                       style="width: 100%; padding: 8px; background: #1e192d; border: 1px solid #5a1a8f; color: white; border-radius: 6px;">
            </div>
        <?php endforeach; ?>

        <div style="text-align: center; margin-top: 20px;">
            <button type="submit" style="background: linear-gradient(to right, #3a0d6a, #5a1a8f); color: white; padding: 10px 24px; border: none; border-radius: 6px; cursor: pointer;">💾 Сохранить</button>
            <a href="../pages/view_db.php" style="color: #ff6b6b; text-decoration: none; margin-left: 15px;">❌ Отмена</a>
        </div>
    </form>
</div>

</body>
</html>