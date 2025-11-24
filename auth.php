<?php

function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['message'] = "❌ Необходимо войти в систему.";
        header("Location: login.php");
        exit;
    }

    if (!isset($_SESSION['role'])) {
        require_once 'config.php';
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $stmt = $pdo->prepare("SELECT role, avatar FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $_SESSION['role'] = $user['role'] ?? 'user';
                $_SESSION['avatar'] = $user['avatar'] ?? 'imang/default.png';
            } else {
                session_destroy();
                header("Location: login.php");
                exit;
            }
        } catch (PDOException $e) {
            session_destroy();
            header("Location: login.php");
            exit;
        }
    }
}

function logout() {
    session_destroy();
    header("Location: index.php");
    exit;
}
?>