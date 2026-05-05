<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $user_id = $_SESSION['user']['user_id'];

    // Get current user data
    $stmt = $conn->prepare("SELECT password FROM user WHERE user_id = ?");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    // Verify current password
    if ($current_password !== $user['password']) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect!']);
        exit();
    }

    // Verify new passwords match
    if ($new_password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => 'New passwords do not match!']);
        exit();
    }

    // Update password
    $update_stmt = $conn->prepare("UPDATE user SET password = ? WHERE user_id = ?");
    $update_stmt->bind_param("ss", $new_password, $user_id);
    
    if ($update_stmt->execute()) {
        $_SESSION['user']['password'] = $new_password;
        echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update password']);
    }

    $update_stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
?> 