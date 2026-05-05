<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user']['user_id'];
$update_field = $_POST['update_field'] ?? '';
$allowed_fields = ['name' => 'user_name', 'email' => 'email', 'dob' => 'dob', 'contact_number' => 'contact_number'];

if (!array_key_exists($update_field, $allowed_fields)) {
    echo json_encode(['success' => false, 'message' => 'Invalid field']);
    exit();
}

$db_field = $allowed_fields[$update_field];
$value = $_POST[$update_field === 'contact_number' ? 'phone' : $update_field] ?? '';

if (empty($value)) {
    echo json_encode(['success' => false, 'message' => 'Value cannot be empty']);
    exit();
}

// Special validation for email
if ($update_field === 'email') {
    // Check in user table (excluding current user)
    $sql = "SELECT COUNT(*) as count FROM user WHERE email = ? AND user_id != ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $value, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_count = $result->fetch_assoc()['count'];

    // Check in admin table
    $sql = "SELECT COUNT(*) as count FROM admin WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $value);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin_count = $result->fetch_assoc()['count'];

    if ($user_count > 0 || $admin_count > 0) {
        echo json_encode(['success' => false, 'message' => 'This email is already in use']);
        exit();
    }
}

// Update the field
$sql = "UPDATE user SET $db_field = ? WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $value, $user_id);

if ($stmt->execute()) {
    // Update session data
    $_SESSION['user'][$db_field] = $value;
    echo json_encode(['success' => true, 'message' => ucfirst($update_field) . ' updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update ' . $update_field]);
} 