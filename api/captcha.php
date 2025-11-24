<?php
session_start();
// Генерация капчи
$pieces = range(1, 4);
shuffle($pieces);
$_SESSION['captcha_solution'] = $pieces;

header('Content-Type: application/json');
echo json_encode(['pieces' => $pieces]);
?>