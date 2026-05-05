<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once '../config/db.php';

    // Verify database connection
    if (!$conn || $conn->connect_error) {
        throw new Exception('Database connection failed: ' . ($conn ? $conn->connect_error : 'Connection not established'));
    }

    header('Content-Type: application/json');

    if (!isset($_SESSION['user'])) {
        throw new Exception('Not logged in');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Debug: Log received data
    error_log('Received POST data: ' . print_r($_POST, true));

    $verify_password = $_POST['verify_password'] ?? '';
    if (empty($verify_password)) {
        throw new Exception('Password is required');
    }

    $user_id = $_SESSION['user']['user_id'] ?? null;
    if (empty($user_id)) {
        throw new Exception('User ID not found in session');
    }

    // Debug: Log user ID
    error_log('Verifying password for user_id: ' . $user_id);

    // Fetch user's current password from database
    $sql = "SELECT password FROM user WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }

    $stmt->bind_param("s", $user_id);
    if (!$stmt->execute()) {
        throw new Exception('Database execute failed: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        throw new Exception('User not found in database');
    }

    // Debug: Log password comparison (don't log actual passwords in production)
    error_log('Password verification attempted');

    if ($verify_password === $user['password']) {
        echo json_encode([
            'success' => true,
            'message' => 'Password verified successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Incorrect password'
        ]);
    }

} catch (Exception $e) {
    error_log('Password verification error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
} 