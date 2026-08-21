<?php
require_once '../config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in and has admin role
if (!isLoggedIn() || !hasRole(['Admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit();
}

$user_id = (int)$_GET['id'];

try {
    // Get user data
    $user = $db->fetch("
        SELECT user_id, username, email, first_name, last_name, role, department, status, created_at, updated_at, last_login
        FROM users 
        WHERE user_id = ?", [$user_id]);
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    
    // Return user data
    echo json_encode([
        'success' => true,
        'user' => $user
    ]);
    
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>