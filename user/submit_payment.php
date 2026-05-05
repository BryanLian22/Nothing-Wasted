<?php
session_start();

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
$bank_acc = $_POST['bank_acc'];
$bank_name = $_POST['bank_name'];
$name = $_POST['name'];
$quotation_id = $_POST['quotationId'];
$handover_method = $_POST['handover_method'];

// Get user's address if pickup method is selected
if ($handover_method === 'pickup' && isset($_POST['pickup_address'])) {
    $address_number = $_POST['pickup_address'];
    
    // Get user's address details
    $sql = "SELECT 
        addressline1_$address_number as addressline1,
        addressline2_$address_number as addressline2,
        zipcode_$address_number as zipcode,
        city_$address_number as city,
        state_$address_number as state
        FROM user WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $_SESSION['user']['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $address = $result->fetch_assoc();
}

// Generate payment ID
function generatePaymentId($conn) {
    // Get all existing payment IDs
    $sql = "SELECT payment_id FROM payment ORDER BY payment_id";
    $result = $conn->query($sql);
    
    $existing_ids = [];
    while ($row = $result->fetch_assoc()) {
        $existing_ids[] = intval(substr($row['payment_id'], 2)); // Remove 'P#' and convert to int
    }
    
    // Find the first available number
    $new_id = 1;
    while (in_array($new_id, $existing_ids)) {
        $new_id++;
    }
    
    return 'P#' . $new_id;
}

// Function to generate dropoff ID
function generateDropoffId($conn) {
    $sql = "SELECT dropoff_id FROM dropoff ORDER BY CAST(SUBSTRING(dropoff_id, 6) AS UNSIGNED)";
    $result = $conn->query($sql);
    
    $used_numbers = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $number = intval(substr($row['dropoff_id'], 5)); // Extract number after 'drop#'
            $used_numbers[] = $number;
        }
    }
    
    // Find the first available number
    $next_number = 1;
    sort($used_numbers);
    foreach ($used_numbers as $num) {
        if ($num != $next_number) {
            break;
        }
        $next_number++;
    }
    
    return "drop#" . $next_number;
}

// Function to generate delivery ID
function generateDeliveryId($conn) {
    $sql = "SELECT delivery_id FROM delivery ORDER BY CAST(SUBSTRING(delivery_id, 10) AS UNSIGNED)";
    $result = $conn->query($sql);
    
    $used_numbers = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $number = intval(substr($row['delivery_id'], 9)); // Extract number after 'delivery#'
            $used_numbers[] = $number;
        }
    }
    
    // Find the first available number
    $next_number = 1;
    sort($used_numbers);
    foreach ($used_numbers as $num) {
        if ($num != $next_number) {
            break;
        }
        $next_number++;
    }
    
    return "delivery#" . $next_number;
}

// Start transaction
$conn->begin_transaction();

try {
    // Generate new payment ID
    $payment_id = generatePaymentId($conn);
    
    // Insert payment information
    $sql = "INSERT INTO payment (payment_id, bank_acc, bank_name, name, status, quotation_id) VALUES (?, ?, ?, ?, 0, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $payment_id, $bank_acc, $bank_name, $name, $quotation_id);
    $stmt->execute();

    // Get submission_id from quotation
    $sql = "SELECT submission_id FROM quotation WHERE quotation_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $quotation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $quotation_data = $result->fetch_assoc();
    $submission_id = $quotation_data['submission_id'];
    
    if ($handover_method === 'dropoff') {
        // Generate dropoff ID
        $dropoff_id = generateDropoffId($conn);
        
        // Calculate drop-off date (7 days from now)
        $dropoff_date = new DateTime();
        $dropoff_date->modify('+7 days');
        $dropoff_date_str = $dropoff_date->format('Y-m-d');
        
        // Update quotation
        $sql = "UPDATE quotation SET status = 'Accepted', method = 0 WHERE quotation_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $quotation_id);
        $stmt->execute();
        
        // Insert into dropoff table
        $sql = "INSERT INTO dropoff (dropoff_id, quotation_id, dropoff_date, status) VALUES (?, ?, ?, 0)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $dropoff_id, $quotation_id, $dropoff_date_str);
        $stmt->execute();
    } else {
        // Generate delivery ID
        $delivery_id = generateDeliveryId($conn);
        
        // Calculate dates
        $current_date = new DateTime();
        $est_arrival = new DateTime();
        $est_arrival->modify('+14 days');
        
        $current_date_str = $current_date->format('Y-m-d');
        $est_arrival_str = $est_arrival->format('Y-m-d');
        
        // Update quotation with address and status
        $sql = "UPDATE quotation SET 
            status = 'Accepted',
            method = 1,
            addressline1 = ?,
            addressline2 = ?,
            zipcode = ?,
            city = ?,
            state = ?
            WHERE quotation_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssss", 
            $address['addressline1'],
            $address['addressline2'],
            $address['zipcode'],
            $address['city'],
            $address['state'],
            $quotation_id
        );
        $stmt->execute();
        
        // Insert into delivery table
        $sql = "INSERT INTO delivery (delivery_id, date, status, est_arrival, user_id, quotation_id) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $status = 1;
        $stmt->bind_param("ssssss", $delivery_id, $current_date_str, $status, $est_arrival_str, $_SESSION['user']['user_id'], $quotation_id);
        $stmt->execute();
    }

    // Update submission status
    $sql = "UPDATE submission SET status = 'Completed' WHERE submission_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $submission_id);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Payment information submitted successfully',
        'payment_id' => $payment_id
    ]);
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?> 