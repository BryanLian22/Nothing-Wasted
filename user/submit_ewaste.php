<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "nothing_wasted";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Generate submission ID (S#number format) using gap-filling logic from auth.php
    $prefix = 'S#';
    $stmt = $conn->query("SELECT submission_id FROM submission WHERE submission_id LIKE 'S#%' ORDER BY submission_id");
    $existingNumbers = array();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $number = intval(substr($row['submission_id'], 2));
        $existingNumbers[] = $number;
    }
    // Find the first gap in the sequence
    $nextNumber = 1;
    foreach ($existingNumbers as $number) {
        if ($number != $nextNumber) {
            break;
        }
        $nextNumber++;
    }
    $submission_id = $prefix . $nextNumber;
    
    // Verify uniqueness (in case of concurrent submissions)
    while (true) {
        $check_stmt = $conn->prepare("SELECT 1 FROM submission WHERE submission_id = ?");
        $check_stmt->execute([$submission_id]);
        if (!$check_stmt->fetch()) {
            break; // ID is unique, we can use it
        }
        // If not unique, increment and try again
        $nextNumber++;
        $submission_id = $prefix . $nextNumber;
    }

    // Get current date
    $current_date = date('Y-m-d');

    // Get user ID from session
    if (!isset($_SESSION['user']) || !isset($_SESSION['user']['user_id'])) {
        throw new Exception("User not logged in");
    }
    $user_id = $_SESSION['user']['user_id'];

    // Prepare the SQL statement
    $sql = "INSERT INTO submission (
        submission_id, 
        date, 
        laptop_qty, 
        desktop_qty, 
        monitor_qty, 
        printer_qty, 
        phone_qty, 
        appliance_qty, 
        wearables_qty, 
        cables_qty, 
        accessories_qty, 
        ewaste_image,
        status,
        user_id
    ) VALUES (
        :submission_id,
        :date,
        :laptop_qty,
        :desktop_qty,
        :monitor_qty,
        :printer_qty,
        :phone_qty,
        :appliance_qty,
        :wearables_qty,
        :cables_qty,
        :accessories_qty,
        :ewaste_image,
        'Pending',
        :user_id
    )";

    $stmt = $conn->prepare($sql);

    // Handle file upload (only one file allowed)
    if (empty($_FILES['ewaste_image']['tmp_name'])) {
        throw new Exception("No file uploaded");
    }
    if (is_array($_FILES['ewaste_image']['tmp_name'])) {
        // If sent as array (from old form), only allow one
        if (count($_FILES['ewaste_image']['tmp_name']) > 1) {
            throw new Exception("Only one file is allowed. If you have many pictures, please combine them into a PDF and upload that.");
        }
        $tmp_name = $_FILES['ewaste_image']['tmp_name'][0];
        $file_name = $_FILES['ewaste_image']['name'][0];
        $file_type = $_FILES['ewaste_image']['type'][0];
        $file_size = $_FILES['ewaste_image']['size'][0];
    } else {
        $tmp_name = $_FILES['ewaste_image']['tmp_name'];
        $file_name = $_FILES['ewaste_image']['name'];
        $file_type = $_FILES['ewaste_image']['type'];
        $file_size = $_FILES['ewaste_image']['size'];
    }
    $allowed_types = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
    if (!in_array($file_type, $allowed_types)) {
        throw new Exception("Only PDF, JPG, JPEG, and PNG files are allowed.");
    }
    if ($file_size > 16 * 1024 * 1024) {
        throw new Exception("File size exceeds 16MB limit.");
    }
    $imageContent = file_get_contents($tmp_name);

    // Bind parameters
    $stmt->bindParam(':submission_id', $submission_id);
    $stmt->bindParam(':date', $current_date);
    $stmt->bindParam(':laptop_qty', $_POST['laptop_qty']);
    $stmt->bindParam(':desktop_qty', $_POST['desktop_qty']);
    $stmt->bindParam(':monitor_qty', $_POST['monitor_qty']);
    $stmt->bindParam(':printer_qty', $_POST['printer_qty']);
    $stmt->bindParam(':phone_qty', $_POST['phone_qty']);
    $stmt->bindParam(':appliance_qty', $_POST['appliance_qty']);
    $stmt->bindParam(':wearables_qty', $_POST['wearables_qty']);
    $stmt->bindParam(':cables_qty', $_POST['cables_qty']);
    $stmt->bindParam(':accessories_qty', $_POST['accessories_qty']);
    $stmt->bindParam(':ewaste_image', $imageContent, PDO::PARAM_LOB);
    $stmt->bindParam(':user_id', $user_id);

    // Execute the statement
    $stmt->execute();

    // Return success response
    echo json_encode([
        'status' => 'success',
        'message' => 'Submission successful',
        'submission_id' => $submission_id
    ]);

} catch(PDOException $e) {
    // Return error response
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch(Exception $e) {
    // Return error response
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?> 