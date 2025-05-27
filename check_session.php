<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is a patient
$response = [
    'active' => isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'patient',
    'message' => isset($_SESSION['user_role']) ? 'Session active' : 'No active session'
];

echo json_encode($response);
exit(); 