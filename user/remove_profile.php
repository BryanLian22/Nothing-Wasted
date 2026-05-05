<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user']['user_id'];

try {
    // Update user data to remove profile picture
    $sql = "UPDATE user SET profile_pic = NULL WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $user_id);
    
    if ($stmt->execute()) {
        // Update session data
        $_SESSION['user']['profile_pic'] = null;
        
        echo json_encode([
            'success' => true,
            'message' => 'Profile picture removed successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to remove profile picture'
        ]);
    }
} catch (Exception $e) {
    error_log('Profile picture removal error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error removing profile picture'
    ]);
}

$stmt->close();
$conn->close();
?> 