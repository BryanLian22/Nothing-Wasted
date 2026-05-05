<?php
session_start();
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profileImage'])) {
    $file = $_FILES['profileImage'];
    $user_id = $_SESSION['user']['user_id'];

    // Check for errors
    if ($file['error'] === 0) {
        // Read file content
        $imageData = file_get_contents($file['tmp_name']);
        
        // Update user's profile image in database
        $stmt = $conn->prepare("UPDATE user SET profile_pic = ? WHERE user_id = ?");
        $stmt->bind_param("ss", $imageData, $user_id);
        
        if ($stmt->execute()) {
            // Update session data
            $_SESSION['user']['profile_pic'] = $imageData;
            echo json_encode(['success' => true, 'message' => 'Profile image updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update profile image']);
        }
        
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Error uploading file']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

$conn->close();
?> 