<?php
session_start();

file_put_contents(__DIR__ . '/debug_update_payment.log', print_r($_POST, true), FILE_APPEND);

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "nothing_wasted";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Get form data
$payment_id = $_POST['payment_id'];
$delivery_id = isset($_POST['delivery_id']) ? $_POST['delivery_id'] : null;
$dropoff_id = isset($_POST['dropoff_id']) ? $_POST['dropoff_id'] : null;

// Start transaction
$conn->begin_transaction();

try {
    // Build dynamic SQL
    $fields = [];
    $params = [];
    $types = '';

    if ($delivery_id) {
        $fields[] = "delivery_id = ?";
        $params[] = $delivery_id;
        $types .= 's';
    }
    if ($dropoff_id) {
        $fields[] = "dropoff_id = ?";
        $params[] = $dropoff_id;
        $types .= 's';
    }
    if (empty($fields)) {
        throw new Exception('No delivery_id or dropoff_id provided');
    }
    $params[] = $payment_id;
    $types .= 's';

    $sql = "UPDATE payment SET " . implode(', ', $fields) . " WHERE payment_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        throw new Exception('No rows updated. Check payment_id.');
    }

    $conn->commit();

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Payment updated successfully']);
} catch (Exception $e) {
    $conn->rollback();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?> 