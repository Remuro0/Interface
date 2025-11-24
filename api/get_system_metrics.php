<?php
header('Content-Type: application/json');
session_start();
require_once '../auth.php';
requireAuth();

if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Доступ запрещён']);
    exit;
}

// Генерация фальшивых данных в реальном времени
$labels = [];
$cpu = [];
$memory = [];
$disk = [];

for ($i = 19; $i >= 0; $i--) {
    $time = date('H:i:s', strtotime("-$i seconds"));
    $labels[] = $time;
    $cpu[] = rand(10, 90);    // CPU от 10% до 90%
    $memory[] = rand(20, 85); // Память от 20% до 85%
    $disk[] = rand(30, 75);   // Диск от 30% до 75%
}

echo json_encode([
    'labels' => $labels,
    'cpu' => $cpu,
    'memory' => $memory,
    'disk' => $disk,
    'source' => 'Данные: симулированные (фальшивые, обновляются в реальном времени)'
]);
?>