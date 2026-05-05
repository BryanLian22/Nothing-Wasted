<?php
session_start();

if (!isset($_SESSION['user'])) {
    http_response_code(403);
    echo 'Unauthorized - Please log in first';
    exit();
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo 'Missing payment ID';
    exit();
}

$payment_id = $_GET['id'];
$user_id = $_SESSION['user']['user_id'];

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "nothing_wasted";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo 'Database connection failed';
    exit();
}

// Check if this payment belongs to the user (via history)
$stmt = $conn->prepare("
    SELECT p.receipt
    FROM payment p
    JOIN history h ON p.payment_id = h.payment_id
    WHERE p.payment_id = ? AND h.user_id = ?
    LIMIT 1
");
$stmt->bind_param('ss', $payment_id, $user_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    http_response_code(404);
    echo 'Receipt not found or you do not have access.';
    exit();
}

$stmt->bind_result($receipt);
$stmt->fetch();
$stmt->close();
$conn->close();

if (!$receipt) {
    http_response_code(404);
    echo 'No receipt uploaded.';
    exit();
}

// Detect file type
$finfo = finfo_open();
$mime = finfo_buffer($finfo, $receipt, FILEINFO_MIME_TYPE);
finfo_close($finfo);

if ($mime === 'application/pdf') {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="receipt.pdf"');
} elseif ($mime === 'image/jpeg' || $mime === 'image/jpg') {
    header('Content-Type: image/jpeg');
    header('Content-Disposition: inline; filename="receipt.jpg"');
} elseif ($mime === 'image/png') {
    header('Content-Type: image/png');
    header('Content-Disposition: inline; filename="receipt.png"');
} else {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="receipt.bin"');
}
header('Content-Length: ' . strlen($receipt));
echo $receipt;
exit(); 