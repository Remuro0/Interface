<?php
session_start();
require_once 'auth.php';
requireAuth();

// Только для админа
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['message'] = "❌ Только администратор может синхронизировать данные.";
    header("Location: view_db.php");
    exit;
}

$message = '';
$error = false;

try {
    // === Подключаемся к локальной БД ===
    require_once 'config.php';
    $pdo_local = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo_local->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // === Подключаемся к глобальной БД ===
    $global_host = '134.90.167.42';
    $global_port = '10306';
    $global_dbname = 'project_Tkachenko';
    $global_user = 'Tkachenko';
    $global_pass = 'F6DRi_';

    $pdo_global = new PDO("mysql:host=$global_host;port=$global_port;dbname=$global_dbname;charset=utf8", $global_user, $global_pass);
    $pdo_global->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // === Получаем список таблиц для копирования ===
    $tables = ['users', 'servers', 'network_devices', 'incidents', 'backups', 'changes', 'logs']; // + любые другие, нужные вам

    foreach ($tables as $table) {
        // Очищаем таблицу в глобальной БД
        $pdo_global->exec("TRUNCATE TABLE `$table`");

        // Читаем все данные из локальной
        $stmt_local = $pdo_local->query("SELECT * FROM `$table`");
        $columns = $stmt_local->columnCount() ? array_keys($stmt_local->fetch(PDO::FETCH_ASSOC)) : [];
        $stmt_local->execute(); // сбрасываем после fetch

        if (empty($columns)) continue;

        // Подготавливаем INSERT
        $placeholders = str_repeat('?,', count($columns) - 1) . '?';
        $stmt_global = $pdo_global->prepare("INSERT INTO `$table` (`" . implode('`,`', $columns) . "`) VALUES ($placeholders)");

        // Вставляем построчно
        while ($row = $stmt_local->fetch(PDO::FETCH_NUM)) {
            $stmt_global->execute($row);
        }
    }

    $message = "✅ Успешная синхронизация: данные из локальной БД скопированы в глобальную.";
} catch (PDOException $e) {
    $error = true;
    $message = "❌ Ошибка синхронизации: " . htmlspecialchars($e->getMessage());
} catch (Exception $e) {
    $error = true;
    $message = "❌ Неизвестная ошибка: " . htmlspecialchars($e->getMessage());
}

$_SESSION['message'] = $message;
header("Location: view_db.php");
exit;
?>