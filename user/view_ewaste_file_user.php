<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    http_response_code(403);
    echo 'Unauthorized - Please log in first';
    exit();
}

if (!isset($_GET['submission_id'])) {
    http_response_code(400);
    echo 'Missing submission_id';
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "nothing_wasted";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Debug: Log all relevant information
    error_log("Session data: " . print_r($_SESSION, true));
    error_log("GET data: " . print_r($_GET, true));
    error_log("Attempting to fetch submission_id: " . $_GET['submission_id']);
    error_log("For user_id: " . $_SESSION['user']['user_id']);

    // List all submission IDs for debugging
    $list_stmt = $conn->prepare("SELECT submission_id, user_id FROM submission");
    $list_stmt->execute();
    $all_submissions = $list_stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("All submissions in database: " . print_r($all_submissions, true));

    // First, check if the submission exists at all
    $check_stmt = $conn->prepare("SELECT submission_id, user_id FROM submission WHERE submission_id = ?");
    $check_stmt->execute([$_GET['submission_id']]);
    $exists = $check_stmt->fetch(PDO::FETCH_ASSOC);

    error_log("Initial submission check result: " . print_r($exists, true));

    if (!$exists) {
        // Try to normalize the submission ID format
        $submission_id = $_GET['submission_id'];
        $original_id = $submission_id;
        
        // Handle URL-encoded # character
        if (strpos($submission_id, '%23') !== false) {
            $submission_id = str_replace('%23', '#', $submission_id);
        }
        
        // If it's just a number, add S# prefix
        if (is_numeric($submission_id)) {
            $submission_id = 'S#' . $submission_id;
        }
        // If it's missing the #, add it
        else if (strpos($submission_id, '#') === false && strpos($submission_id, 'S') === 0) {
            $number = substr($submission_id, 1);
            $submission_id = 'S#' . $number;
        }
        
        error_log("Original ID: " . $original_id);
        error_log("Normalized ID: " . $submission_id);
        
        // Try again with normalized format
        if ($submission_id !== $original_id) {
            error_log("Trying normalized format: " . $submission_id);
            $check_stmt->execute([$submission_id]);
            $exists = $check_stmt->fetch(PDO::FETCH_ASSOC);
            error_log("Normalized format check result: " . print_r($exists, true));
        }
        
        if (!$exists) {
            // Log all submissions for debugging
            error_log("All submissions in database for debugging:");
            foreach ($all_submissions as $sub) {
                error_log("Submission ID: " . $sub['submission_id'] . ", User ID: " . $sub['user_id']);
            }
            
            http_response_code(404);
            echo 'Submission not found. Please use the format S#1, S#2, etc.';
            exit();
        }
    }

    // Then verify it belongs to the current user
    if ($exists['user_id'] != $_SESSION['user']['user_id']) {
        http_response_code(403);
        echo 'Unauthorized access to this submission';
        exit();
    }

    // Now get the file data
    $stmt = $conn->prepare("
        SELECT ewaste_image, status
        FROM submission 
        WHERE submission_id = ? AND user_id = ?
    ");
    
    $stmt->execute([$exists['submission_id'], $_SESSION['user']['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    error_log("File data result: " . print_r($row ? 'Found' : 'Not found', true));

    if (!$row) {
        http_response_code(404);
        echo 'No file found for this submission';
        exit();
    }

    if (!$row['ewaste_image']) {
        http_response_code(404);
        echo 'No file found for this submission';
        exit();
    }

    // Try to get the mime type from the file content
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->buffer($row['ewaste_image']);
    
    // If mime type detection fails, try to determine based on content
    if (!$mime) {
        // Check if it's a PDF
        if (strpos($row['ewaste_image'], '%PDF') === 0) {
            $mime = 'application/pdf';
        } else {
            $mime = 'image/jpeg'; // Default to JPEG if we can't determine
        }
    }

    // Set appropriate headers
    header('Content-Type: ' . $mime);
    
    // For images and PDFs, display inline
    if ($mime === 'application/pdf' || strpos($mime, 'image/') === 0) {
        header('Content-Disposition: inline; filename="ewaste_file.' . ($mime === 'application/pdf' ? 'pdf' : 'jpg') . '"');
    } else {
        header('Content-Disposition: attachment; filename="ewaste_file.bin"');
    }

    // Output the file content
    echo $row['ewaste_image'];
    exit();

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    error_log("Error trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo 'Database error: ' . $e->getMessage();
    exit();
} 