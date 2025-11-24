<?php
session_start();
require_once '../auth.php';
requireAuth();
require_once '../log_action.php';

// Только для инженера
if ($_SESSION['role'] !== 'engineer') {
    $_SESSION['message'] = "❌ Доступ запрещён.";
    header("Location: ../pages/view_network.php");
    exit;
}

require_once '../config.php';

$device_id = (int)($_GET['id'] ?? 0);
if (!$device_id) {
    $_SESSION['message'] = "❌ Не указан ID устройства.";
    header("Location: ../pages/view_network.php");
    exit;
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Получаем устройство по ID
    $stmt = $pdo->prepare("SELECT `id`, `name`, `ip_address`, `device_type`, `status`, `last_checked` FROM `network_devices` WHERE `id` = ?");
    $stmt->execute([$device_id]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$device) {
        $_SESSION['message]'] = "❌ Устройство не найдено.";
        header("Location: ../pages/view_network.php");
        exit;
    }

    // Обработка формы
    if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update') {
        $columns_info = $pdo->query("DESCRIBE network_devices")->fetchAll(PDO::FETCH_ASSOC);
        $set = [];
        $values = [];
        foreach ($columns_info as $col) {
            $field = $col['Field'];
            if ($field === 'id') continue;
            $set[] = "`$field` = ?";
            $value = $_POST[$field] ?? '';
            $values[] = $value;
        }
        $values[] = $device_id;
        $sql = "UPDATE network_devices SET " . implode(', ', $set) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute($values)) {
            $_SESSION['message'] = "✅ Устройство обновлено.";
            logAction($pdo, $_SESSION['user_id'], $_SESSION['username'], 'NETWORK_DEVICE_UPDATED', "ID: $device_id, IP: " . ($_POST['ip_address'] ?? '—'));
        } else {
            $_SESSION['message'] = "❌ Ошибка при обновлении.";
        }
        header("Location: ../pages/view_network.php");
        exit;
    }

} catch (PDOException $e) {
    die("Ошибка БД: " . htmlspecialchars($e->getMessage()));
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Редактировать устройство — <?= htmlspecialchars($device['name']) ?></title>
    <link rel="stylesheet" href="../css/view_db.css">
</head>
<body>
    <div style="max-width: 800px; margin: 30px auto; background: rgba(30,25,45,0.95); padding: 20px; border-radius: 12px; border: 1px solid #5a1a8f;">
        <h2 style="text-align: center; color: #c7b8ff;">Редактировать устройство: <?= htmlspecialchars($device['name']) ?></h2>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= (int)$device['id'] ?>">
            <?php foreach ($device as $col => $value): ?>
                <?php if ($col === 'id') continue; ?>
                <div style="margin: 12px 0;">
                    <label style="display: block; color: #c7b8ff; margin-bottom: 4px;"><?= htmlspecialchars($col) ?>:</label>
                    <input type="text" name="<?= $col ?>" value="<?= htmlspecialchars($value) ?>"
                           style="width: 100%; padding: 8px; background: #1e192d; border: 1px solid #5a1a8f; color: white; border-radius: 6px;">
                </div>
            <?php endforeach; ?>
            <div style="text-align: center; margin-top: 20px;">
                <button type="submit" style="background: linear-gradient(to right, #3a0d6a, #5a1a8f); color: white; padding: 10px 24px; border: none; border-radius: 6px; cursor: pointer;">💾 Сохранить</button>
                <a href="../pages/view_network.php" style="color: #ff6b6b; text-decoration: none; margin-left: 15px;">❌ Отмена</a>
            </div>
        </form>
    </div>
</body>
</html>