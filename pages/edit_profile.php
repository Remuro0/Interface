<?php
session_start();
require_once '../auth.php';
requireAuth();
require_once '../config.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ошибка подключения к БД: " . htmlspecialchars($e->getMessage()));
}

$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
    die("Пользователь не найден.");
}

// Получаем данные пользователя
$stmt = $pdo->prepare("SELECT username, email, role, avatar FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Пользователь не найден.");
}

$error = '';
$success = '';

if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $new_username = trim($_POST['username'] ?? '');
    $new_email = trim($_POST['email'] ?? '');
    $new_password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Проверка логина
    if (empty($new_username)) {
        $error = "Логин не может быть пустым.";
    } else {
        $stmt_check = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt_check->execute([$new_username, $user_id]);
        if ($stmt_check->fetch()) {
            $error = "Пользователь с таким логином уже существует.";
        }
    }

    // Проверка email
    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error = "Неверный формат email.";
    }

    if ($error) {
        goto show_form;
    }

    // Обработка загрузки аватарки
    $avatar_path = $user['avatar']; // по умолчанию — старый аватар
    if (!empty($_FILES['avatar']['name'])) {
        $upload_dir = 'imang/';
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = $_FILES['avatar']['type'];

        if (!in_array($file_type, $allowed_types)) {
            $error = "Разрешены только JPG, PNG, GIF.";
            goto show_form;
        }

        $file_ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $new_filename = 'user_' . $user_id . '_' . time() . '.' . $file_ext;
        $target_path = $upload_dir . $new_filename;

        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $target_path)) {
            // Удаляем старый аватар (если не default)
            $old_avatar = $user['avatar'];
            if ($old_avatar !== 'imang/default.png' && file_exists($old_avatar)) {
                unlink($old_avatar);
            }
            $avatar_path = $target_path;
        } else {
            $error = "Ошибка при загрузке аватарки.";
            goto show_form;
        }
    }

    // Подготовка обновления
    $updates = [];
    $params = [];

    if ($new_username !== $user['username']) {
        $updates[] = "username = ?";
        $params[] = $new_username;
    }
    if ($new_email !== $user['email']) {
        $updates[] = "email = ?";
        $params[] = $new_email;
    }
    if (!empty($new_password)) {
        if ($new_password !== $confirm_password) {
            $error = "Пароли не совпадают.";
            goto show_form;
        }
        $updates[] = "password_hash = ?";
        $params[] = password_hash($new_password, PASSWORD_DEFAULT);
    }
    if ($avatar_path !== $user['avatar']) {
        $updates[] = "avatar = ?";
        $params[] = $avatar_path;
    }

    if (empty($updates)) {
        $success = "Нет изменений.";
    } else {
        $params[] = $user_id;
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt_update = $pdo->prepare($sql);
        if ($stmt_update->execute($params)) {
            $success = "✅ Профиль обновлён.";
            // Обновляем сессию
            if ($new_username !== $user['username']) {
                $_SESSION['username'] = $new_username;
            }
            $_SESSION['avatar'] = $avatar_path;
        } else {
            $error = "❌ Ошибка при сохранении.";
        }
    }

    if ($success) {
        $_SESSION['message'] = $success;
        header("Location: ../pages/view_db.php");
        exit;
    }
}

show_form:
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Редактировать профиль</title>
    <link rel="stylesheet" href="../css/view_db.css">
</head>
<body>
    <div style="max-width: 600px; margin: 30px auto; background: rgba(30,25,45,0.95); padding: 20px; border-radius: 12px; border: 1px solid #5a1a8f;">
        <h2 style="text-align: center; color: #c7b8ff;">Редактировать профиль</h2>

        <?php if ($error): ?>
            <div style="text-align: center; padding: 10px; background: rgba(200, 50, 50, 0.3); color: #ffaaaa; margin: 10px auto; max-width: 600px; border-radius: 6px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update_profile">

            <!-- Аватарка -->
            <div style="text-align: center; margin-bottom: 15px;">
                <img src="../<?= htmlspecialchars($_SESSION['avatar'] ?? '../imang/default.png') ?>" alt="Аватарка" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 2px solid #5a1a8f;">
                <div style="margin-top: 8px; font-size: 12px; color: #c7b8ff;">Текущая аватарка</div>
            </div>

            <div style="margin: 12px 0;">
                <label style="display: block; color: #c7b8ff; margin-bottom: 4px;">Новая аватарка (JPG/PNG/GIF):</label>
                <input type="file" name="avatar" accept="image/*"
                       style="width: 100%; padding: 4px; background: #1e192d; border: 1px solid #5a1a8f; color: white; border-radius: 6px;">
            </div>

            <div style="margin: 12px 0;">
                <label style="display: block; color: #c7b8ff; margin-bottom: 4px;">Логин:</label>
                <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>"
                       style="width: 100%; padding: 8px; background: #1e192d; border: 1px solid #5a1a8f; color: white; border-radius: 6px;" required>
            </div>

            <div style="margin: 12px 0;">
                <label style="display: block; color: #c7b8ff; margin-bottom: 4px;">Email:</label>
                <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>"
                       style="width: 100%; padding: 8px; background: #1e192d; border: 1px solid #5a1a8f; color: white; border-radius: 6px;" required>
            </div>

            <div style="margin: 12px 0;">
                <label style="display: block; color: #c7b8ff; margin-bottom: 4px;">Новый пароль (оставьте пустым, если не меняете):</label>
                <input type="password" name="password"
                       style="width: 100%; padding: 8px; background: #1e192d; border: 1px solid #5a1a8f; color: white; border-radius: 6px;">
            </div>

            <div style="margin: 12px 0;">
                <label style="display: block; color: #c7b8ff; margin-bottom: 4px;">Подтвердите пароль:</label>
                <input type="password" name="confirm_password"
                       style="width: 100%; padding: 8px; background: #1e192d; border: 1px solid #5a1a8f; color: white; border-radius: 6px;">
            </div>

            <div style="text-align: center; margin-top: 20px;">
                <button type="submit" 
                        style="background: linear-gradient(to right, #3a0d6a, #5a1a8f); color: white; padding: 10px 24px; border: none; border-radius: 6px; cursor: pointer;">
                    Сохранить изменения
                </button>
                <a href="view_db.php" style="color: #ff6b6b; text-decoration: none; margin-left: 15px;">Отмена</a>
            </div>
        </form>
    </div>
</body>
</html>