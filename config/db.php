<?php
$host = 'localhost';
$dbname = 'mcdzn3clinic';
$username = 'root';
$password = 'dhe5//wo]XN3_OoB';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Log error and return JSON
    error_log("Database connection failed: " . $e->getMessage());
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}