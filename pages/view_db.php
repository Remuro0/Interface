<?php
// Начало сессии — ОБЯЗАТЕЛЬНО
session_start();
require_once '../auth.php'; // ← Изменили путь!
requireAuth(); // Проверяем, вошёл ли пользователь

// === 🔒 ДОСТУП ТОЛЬКО ДЛЯ АДМИНА ===
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['message'] = "❌ У вас нет прав на просмотр базы данных.";
    header("Location: ../index.php"); // ← Изменили путь!
    exit;
}
// === КОНЕЦ ОГРАНИЧЕНИЯ ===

// Подключаем конфигурацию БД
require_once '../config.php'; // ← Изменили путь!

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ошибка подключения к БД: " . htmlspecialchars($e->getMessage()));
}

// Получаем все таблицы из БД
$stmt = $pdo->query("SHOW TABLES");
$all_tables = [];
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    $all_tables[] = $row[0];
}

// Стандартные таблицы — в начале
$default_tables = ['users', 'servers', 'network_devices', 'changes', 'incidents', 'backups', 'logs'];
$allowed_tables = array_merge(
    array_intersect($default_tables, $all_tables),
    array_diff($all_tables, $default_tables)
);

$current_table = $_GET['table'] ?? '';
if (!in_array($current_table, $all_tables)) {
    $current_table = $all_tables[0] ?? '';
}

// ✅ Обновляем роль и аватарку из БД — ВСЕГДА
try {
    $stmt = $pdo->prepare("SELECT role, avatar FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $_SESSION['role'] = $user['role'] ?? 'user';
        $_SESSION['avatar'] = $user['avatar'] ?? 'imang/default.png';
    } else {
        session_destroy();
        header("Location: ../login.php"); // ← Изменили путь!
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['role'] = $_SESSION['role'] ?? 'user';
    $_SESSION['avatar'] = $_SESSION['avatar'] ?? 'imang/default.png';
}

function getUserName($pdo, $user_id) {
    if (!$user_id) return '—';
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ? htmlspecialchars($user['username']) : "ID: $user_id";
}

function getServerName($pdo, $server_id) {
    if (!$server_id) return '—';
    $stmt = $pdo->prepare("SELECT name FROM servers WHERE id = ?");
    $stmt->execute([$server_id]);
    $server = $stmt->fetch(PDO::FETCH_ASSOC);
    return $server ? htmlspecialchars($server['name']) : "ID: $server_id";
}

function getStatusColor($status) {
    $status = strtolower(trim($status));
    if (in_array($status, ['up', 'active', 'planned', 'approved', 'implemented', 'resolved', 'closed', 'success', 'online', 'running'])) {
        return 'rgba(40, 200, 80, 0.3)';
    } elseif (in_array($status, ['down', 'inactive', 'failed', 'error', 'offline', 'stopped', 'deleted'])) {
        return 'rgba(200, 50, 50, 0.3)';
    } elseif (in_array($status, ['maintenance', 'updating', 'restarting'])) {
        return 'rgba(255, 165, 0, 0.3)';
    } elseif (in_array($status, ['open', 'in_progress', 'pending', 'processing'])) {
        return 'rgba(50, 100, 255, 0.3)';
    } else {
        return 'rgba(100, 100, 100, 0.3)';
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>База данных: local</title>
    <link rel="stylesheet" href="../css/view_db.css"> <!-- ← Изменили путь! -->
</head>
<body>
<!-- Верхняя панель с пользователем -->
<div class="topbar">
    <img src="../<?= htmlspecialchars($_SESSION['avatar'] ?? '../imang/default.png') ?>" alt="Аватарка">
    <div class="user-info">
        <strong><?= htmlspecialchars($_SESSION['username'] ?? 'Гость') ?></strong><br>
        <span class="role">Роль: <?= htmlspecialchars($_SESSION['role'] ?? 'user') ?></span>
    </div>
    <!-- Кнопка для открытия меню -->
    <button onclick="toggleUserMenu()" style="background: none; border: none; color: #c7b8ff; font-size: 16px; cursor: pointer;">▼</button>
</div>
<!-- Выпадающее меню -->
<div id="userMenu" class="dropdown-content" style="display: none;">
    <<!-- Заголовок "Таблицы" с кнопкой-стрелкой -->
<div style="padding: 8px 12px; color: #c7b8ff; font-weight: bold; cursor: pointer; display: flex; justify-content: space-between; align-items: center;"
     onclick="toggleTablesMenu()">
    📋 Таблицы
    <span id="tablesArrow" style="font-size: 14px;">▼</span>
</div>
<!-- Скрытый список таблиц -->
<div id="tablesList" style="display: none; margin-top: 8px;">
    <?php foreach ($all_tables as $table): ?>
        <a href="?table=<?= urlencode($table) ?>" 
           style="display: block; padding: 6px 12px; color: #e0e0e0; text-decoration: none; border-radius: 4px; font-size: 13px;"
           onmouseover="this.style.backgroundColor='rgba(100, 80, 150, 0.3)'; this.style.color='white';"
           onmouseout="this.style.backgroundColor=''; this.style.color='#e0e0e0';">
            <?= htmlspecialchars($table) ?>
        </a>
    <?php endforeach; ?>
</div>
<script>
function toggleTablesMenu() {
    const list = document.getElementById('tablesList');
    const arrow = document.getElementById('tablesArrow');
    if (list.style.display === 'block') {
        list.style.display = 'none';
        arrow.textContent = '▼';
    } else {
        list.style.display = 'block';
        arrow.textContent = '▲';
    }
}
</script>
    <hr>
    <a href="edit_profile.php"> 🖊️ Редактировать профиль</a>
    <a href="../create_table_form.php">➕ Создать таблицу</a>
    <!-- Кнопка синхронизации -->
<?php if ($_SESSION['role'] === 'admin'): ?>
    <a href="../sync_to_global.php"
       onclick="return confirm('Вы уверены? Все данные в глобальной БД будут заменены данными из локальной!')"
       style="background: linear-gradient(to right, #ff9800, #ffcc00); color: #000; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: bold; text-decoration: none;">
        🌐 Синхронизировать в глобальную БД
    </a>
<?php endif; ?>
    </button>
    <?php if ($_SESSION['role'] === 'admin'): ?>
        <a href="../backup.php"
           style="display: block; margin-left: 5px; width: 90%; padding: 10px 12px; color: #e0e0e0; text-decoration: none; border-radius: 6px; font-size: 14px;">
            💾 Создать бэкап
        </a>
    <?php endif; ?>
    <?php if ($_SESSION['role'] === 'admin'): ?>
        <a href="../sql_console.php">💻 SQL-консоль</a>
    <?php endif; ?>
    <hr>
    <a href="../logout.php">🚪 Выйти</a>
</div>
<div class="content-wrapper">
    <?php if (!empty($_SESSION['message'])): ?>
        <div style="text-align: center; padding: 12px; background: rgba(40, 100, 40, 0.3); color: #aaffaa; margin: 10px auto; max-width: 600px; border-radius: 8px; border: 1px solid #3a8a3a;">
            <?= htmlspecialchars($_SESSION['message']) ?>
        </div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
        <h2 style="margin: 0;">Таблица: <?= htmlspecialchars($current_table) ?></h2>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="../add_record.php?table=<?= urlencode($current_table) ?>"
               style="background: linear-gradient(to right, #00c853, #64dd17); color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: bold; text-decoration: none;">
                + Добавить запись
            </a>
            <?php if ($_SESSION['role'] === 'admin' && $current_table !== 'users'): ?>
                <a href="../delete_table.php?table=<?= urlencode($current_table) ?>"
                   onclick="return confirm('Вы уверены, что хотите удалить ВСЮ таблицу <?= addslashes($current_table) ?>? Это действие нельзя отменить!')"
                   style="background: linear-gradient(to right, #ff3b3b, #ff6b6b); color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: bold; text-decoration: none;">
                    🗑️ Удалить таблицу
                </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="table-container">
        <?php
        try {
            $stmt = $pdo->query("SELECT * FROM `$current_table`");
            $columns = [];
            for ($i = 0; $i < $stmt->columnCount(); $i++) {
                $col = $stmt->getColumnMeta($i);
                $columns[] = $col['name'];
            }
            echo "<table>";
            echo "<tr>";
            foreach ($columns as $col) {
                echo "<th>" . htmlspecialchars($col) . "</th>";
            }
            if (in_array('id', $columns)) {
                echo "<th>Действия</th>";
            }
            echo "</tr>";
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "<tr>";
                foreach ($columns as $col) {
                    $value = $row[$col];
                    if ($current_table === 'changes' && in_array($col, ['requested_by', 'approved_by'])) {
                        $value = getUserName($pdo, $value);
                    } elseif ($current_table === 'incidents' && $col === 'assigned_to') {
                        $value = getUserName($pdo, $value);
                    } elseif ($current_table === 'backups' && $col === 'server_id') {
                        $value = getServerName($pdo, $value);
                    }
                    if (stripos($col, 'status') !== false) {
                        $bgColor = getStatusColor($value);
                        echo "<td style='background: {$bgColor}; padding: 10px 12px; border-bottom: 1px solid rgba(100, 80, 130, 0.2);'>";
                        echo htmlspecialchars($value);
                        echo "</td>";
                    } else {
                        echo "<td>" . htmlspecialchars($value) . "</td>";
                    }
                }
                if (in_array('id', $columns)) {
                    $id = $row['id'];
                    echo "<td class='actions'>";
                    echo "<a href='../edit_record.php?table=" . urlencode($current_table) . "&id=$id' class='btn-edit'>✏️ Изм</a> ";
                    echo "<a href='../delete_record.php?table=" . urlencode($current_table) . "&id=$id' class='btn-delete' onclick='return confirm(\"Удалить запись?\")'>🗑️ Уд</a>";
                    echo "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        } catch (PDOException $e) {
            echo "<div style='color: #ff6b6b; padding: 10px; background: rgba(200, 50, 50, 0.3); border-radius: 6px;'>❌ Ошибка при запросе к таблице: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
        ?>
    </div>
</div>
<script>
function toggleUserMenu() {
    const menu = document.getElementById('userMenu');
    const button = document.querySelector('.topbar button');
    if (menu.style.display === 'block') {
        menu.style.display = 'none';
    } else {
        menu.style.display = 'block';
    }
}
// Закрывать меню при клике вне его
document.addEventListener('click', function(event) {
    const menu = document.getElementById('userMenu');
    const button = document.querySelector('.topbar button');
    if (!button.contains(event.target) && !menu.contains(event.target)) {
        menu.style.display = 'none';
    }
});
</script>
<?php include '../footer.php'; ?>
</body>
</html>