<?php
session_start();
require_once 'auth.php';
requireAuth();

// 🔒 Только админ
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['message'] = "❌ Только администратор может синхронизировать данные.";
    header("Location: view_db.php");
    exit;
}

$message = '';
$error = false;

try {
    // === Подключаемся к локальной БД (из config.php) ===
    require_once 'config.php';
    $pdo_local = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo_local->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // === Подключаемся к глобальной БД (жёстко прописаны данные — как в ТЗ) ===
    $global_config = [
        'host' => '134.90.167.42',
        'port' => '10306',
        'dbname' => 'project_Tkachenko',
        'username' => 'Tkachenko',
        'password' => 'F6DRi_',
    ];

    $pdo_global = new PDO(
        "mysql:host={$global_config['host']};port={$global_config['port']};dbname={$global_config['dbname']};charset=utf8",
        $global_config['username'],
        $global_config['password']
    );
    $pdo_global->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // === Список таблиц для синхронизации ===
    $tables = [
        'users',
        'servers',
        'network_devices',
        'changes',
        'incidents',
        'backups',
        'logs',
        'services',
        'tariff_plans',
        'support_tickets',
        'purchases',
        'cart',
        'notifications'
    ];

    // === Начинаем транзакцию глобальной БД ===
    $pdo_global->beginTransaction();

    foreach ($tables as $table) {
        // Проверяем, существует ли таблица в локальной БД
        $stmt_check = $pdo_local->prepare("SHOW TABLES LIKE ?");
        $stmt_check->execute([$table]);
        if (!$stmt_check->fetch()) {
            continue; // пропускаем, если таблицы нет локально
        }

        // Очищаем таблицу в глобальной БД (TRUNCATE безопаснее DELETE, если нет внешних ключей)
        try {
            $pdo_global->exec("TRUNCATE TABLE `$table`");
        } catch (PDOException $e) {
            // Если TRUNCATE запрещён (например, из-за FK) — используем DELETE
            $pdo_global->exec("DELETE FROM `$table`");
            $pdo_global->exec("ALTER TABLE `$table` AUTO_INCREMENT = 1");
        }

        // Читаем все данные из локальной
        $stmt_select = $pdo_local->query("SELECT * FROM `$table`");
        $columns = $stmt_select->columnCount() 
            ? array_keys($stmt_select->fetch(PDO::FETCH_ASSOC) ?: []) 
            : [];
        if (empty($columns)) continue;

        // Подготавливаем INSERT
        $placeholders = str_repeat('?,', count($columns) - 1) . '?';
        $stmt_insert = $pdo_global->prepare(
            "INSERT INTO `$table` (`" . implode('`,`', $columns) . "`) VALUES ($placeholders)"
        );

        // Вставляем построчно
        $stmt_select->execute(); // сбрасываем курсор
        while ($row = $stmt_select->fetch(PDO::FETCH_NUM)) {
            $stmt_insert->execute($row);
        }
    }

    // === Фиксируем изменения ===
    $pdo_global->commit();
    $message = "✅ Успешная синхронизация: данные из локальной БД скопированы в глобальную.";

} catch (PDOException $e) {
    $error = true;
    $message = "❌ Ошибка синхронизации: " . htmlspecialchars($e->getMessage());
    if (isset($pdo_global)) {
        @$pdo_global->rollback(); // @ — подавить ошибку, если транзакции не было
    }
} catch (Exception $e) {
    $error = true;
    $message = "❌ Критическая ошибка: " . htmlspecialchars($e->getMessage());
}

$_SESSION['message'] = $message;
header("Location: view_db.php");
exit;