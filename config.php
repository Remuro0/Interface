<?php
// config.php — универсальный конфиг с fallback на глобальную БД

// === Локальная БД (по умолчанию) ===
$config = [
    'host' => 'localhost',
    'port' => '3306',
    'dbname' => 'local',
    'username' => 'root',
    'password' => '',
    'type' => 'local'
];

// === Глобальная БД (резервная и для синхронизации) ===
$global_config = [
    'host' => '134.90.167.42',
    'port' => '10306',
    'dbname' => 'project_Tkachenko',
    'username' => 'Tkachenko',
    'password' => 'F6DRi_',
    'type' => 'global'
];

// === Создаём подключение — с fallback на глобальную, если локальная недоступна ===
function getDBConnection($for_sync = false) {
    global $config, $global_config;

    if ($for_sync) {
        // Явно запрашиваем глобальную — для sync_to_global.php
        $target = $global_config;
    } else {
        // Пробуем локальную; если не получится — глобальную
        try {
            $pdo = new PDO(
                "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset=utf8",
                $config['username'],
                $config['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $pdo->query("SELECT 1"); // проверка живости
            return $pdo;
        } catch (PDOException $e) {
            // Локальная недоступна → fallback на глобальную
            $target = $global_config;
            $_SESSION['db_fallback'] = true;
        }
    }

    // Подключаемся к целевой БД
    try {
        $pdo = new PDO(
            "mysql:host={$target['host']};port={$target['port']};dbname={$target['dbname']};charset=utf8",
            $target['username'],
            $target['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        if (!$for_sync) {
            $_SESSION['db_type'] = $target['type'];
        }
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception("❌ Ни локальная, ни глобальная БД недоступны: " . $e->getMessage());
    }
}

// === Совместимость: оставляем старые переменные, но как fallback ===
// (чтобы не ломать старый код вроде `new PDO("host=$host;...")`)
try {
    $pdo_check = new PDO("mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8", $config['username'], $config['password']);
    $pdo_check->query("SELECT 1");
    $pdo_check = null;
    // Локальная БД доступна → оставляем старые переменные
    $host = $config['host'];
    $dbname = $config['dbname'];
    $username = $config['username'];
    $password = $config['password'];
    $_SESSION['db_type'] = 'local';
} catch (PDOException $e) {
    // Локальная недоступна → переключаемся на глобальную
    $host = $global_config['host'];
    $port = $global_config['port']; // добавляем порт для совместимости
    $dbname = $global_config['dbname'];
    $username = $global_config['username'];
    $password = $global_config['password'];
    $_SESSION['db_type'] = 'global';
    $_SESSION['db_fallback'] = true;
}
?>