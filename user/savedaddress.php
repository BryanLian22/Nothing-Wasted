<?php
session_start();
require_once '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: auth.php");
    exit();
}

$user_id = $_SESSION['user']['user_id'];
$message = '';
$error = '';
$address_number = 0;

// Get the current address number if we're editing
if (isset($_POST['address_line1'])) {
    $current_address = $_POST['address_line1'];
    if (!empty($user['addressline1_2']) && $current_address === $user['addressline1_2']) {
        $address_number = 2;
    } elseif (!empty($user['addressline1_3']) && $current_address === $user['addressline1_3']) {
        $address_number = 3;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $address_number = $_POST['address_number'];
        if ($_POST['action'] === 'remove') {
            // Get current addresses
            $sql = "SELECT addressline1_1, addressline2_1, zipcode_1, city_1, state_1,
                           addressline1_2, addressline2_2, zipcode_2, city_2, state_2,
                           addressline1_3, addressline2_3, zipcode_3, city_3, state_3 
                    FROM user WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $userRow = $result->fetch_assoc();
            
            // Count non-empty addresses
            $addressCount = 0;
            for ($i = 1; $i <= 3; $i++) {
                if (!empty($userRow["addressline1_$i"])) {
                    $addressCount++;
                }
            }
            
            if ($addressCount <= 1) {
                $error = "You must have at least one address.";
            } else {
                if ($address_number == 1) {
                    // Shift up: address2 -> address1, address3 -> address2, address3 = NULL
                    $sql = "UPDATE user SET 
                            addressline1_1 = addressline1_2, addressline2_1 = addressline2_2, 
                            zipcode_1 = zipcode_2, city_1 = city_2, state_1 = state_2,
                            addressline1_2 = addressline1_3, addressline2_2 = addressline2_3,
                            zipcode_2 = zipcode_3, city_2 = city_3, state_2 = state_3,
                            addressline1_3 = NULL, addressline2_3 = NULL,
                            zipcode_3 = NULL, city_3 = NULL, state_3 = NULL
                            WHERE user_id = ?";
                } elseif ($address_number == 2) {
                    // Shift up: address3 -> address2, address3 = NULL
                    $sql = "UPDATE user SET 
                            addressline1_2 = addressline1_3, addressline2_2 = addressline2_3,
                            zipcode_2 = zipcode_3, city_2 = city_3, state_2 = state_3,
                            addressline1_3 = NULL, addressline2_3 = NULL,
                            zipcode_3 = NULL, city_3 = NULL, state_3 = NULL
                            WHERE user_id = ?";
                } elseif ($address_number == 3) {
                    // Just remove address3
                    $sql = "UPDATE user SET 
                            addressline1_3 = NULL, addressline2_3 = NULL,
                            zipcode_3 = NULL, city_3 = NULL, state_3 = NULL
                            WHERE user_id = ?";
                }
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $user_id);
                if ($stmt->execute()) {
                    $message = "Address removed successfully";
                } else {
                    $error = "Error removing address";
                }
            }
        } elseif ($_POST['action'] === 'make_primary') {
            // Get the current address number from the form
            $current_address_number = $_POST['address_number'];
            
            if ($current_address_number > 1) {
                // First, get the current values of both addresses
                $sql = "SELECT 
                        addressline1_1, addressline2_1, zipcode_1, city_1, state_1,
                        addressline1_$current_address_number, addressline2_$current_address_number, 
                        zipcode_$current_address_number, city_$current_address_number, state_$current_address_number
                        FROM user WHERE user_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();

                // Now swap the values
                $sql = "UPDATE user SET 
                        addressline1_1 = ?,
                        addressline2_1 = ?,
                        zipcode_1 = ?,
                        city_1 = ?,
                        state_1 = ?,
                        addressline1_$current_address_number = ?,
                        addressline2_$current_address_number = ?,
                        zipcode_$current_address_number = ?,
                        city_$current_address_number = ?,
                        state_$current_address_number = ?
                        WHERE user_id = ?";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssissssisss", 
                    $row["addressline1_$current_address_number"],
                    $row["addressline2_$current_address_number"],
                    $row["zipcode_$current_address_number"],
                    $row["city_$current_address_number"],
                    $row["state_$current_address_number"],
                    $row["addressline1_1"],
                    $row["addressline2_1"],
                    $row["zipcode_1"],
                    $row["city_1"],
                    $row["state_1"],
                    $user_id
                );
                
                if ($stmt->execute()) {
                    $message = "Address set as primary successfully";
                } else {
                    $error = "Error setting address as primary";
                }
            }
        } else {
            // Handle update or add
            $address_line1 = trim($_POST['address_line1']);
            $address_line2 = trim($_POST['address_line2']);
            $zipcode = trim($_POST['zipcode']);
            $city = trim($_POST['city']);
            $state = trim($_POST['state']);

            // Validate address - all fields are required
            if (empty($address_line1) || empty($address_line2) || empty($zipcode) || empty($city) || empty($state)) {
                $error = "All fields must be filled.";
            } else {
                // Update the address in the database
                $sql = "UPDATE user SET 
                        addressline1_$address_number = ?, 
                        addressline2_$address_number = ?,
                        zipcode_$address_number = ?,
                        city_$address_number = ?,
                        state_$address_number = ?
                        WHERE user_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssisss", $address_line1, $address_line2, $zipcode, $city, $state, $user_id);
                if ($stmt->execute()) {
                    $message = "Address updated successfully";
                } else {
                    $error = "Error updating address";
                }
            }
        }
    }
}

// Get user's addresses
$sql = "SELECT addressline1_1, addressline2_1, zipcode_1, city_1, state_1,
               addressline1_2, addressline2_2, zipcode_2, city_2, state_2,
               addressline1_3, addressline2_3, zipcode_3, city_3, state_3
        FROM user WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saved Addresses - Nothing Wasted</title>
    <style>
        /* Base styles and variables */
        :root {
            --primary-color: #59B8A0;
            --text-color: #FFFFFF;
            --card-bg: rgba(255, 255, 255, 0.1);
            --border-radius: 12px;
            --spacing-unit: 1rem;
            --transition-speed: 0.3s;
        }

        
        body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            background: url('assets/edit/bg.png') no-repeat center center fixed;
            background-size: cover;
            color: var(--text-color);
            font-family: Arial, sans-serif;
            overflow-x: hidden;
            position: relative;
        }

        /* Add an overlay to ensure text readability */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1;
        }

        /* Container styles */
        .saved-addresses-container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: calc(var(--spacing-unit) * 2);
            position: relative;
            z-index: 2;
        }

        /* Responsive styles */
        @media screen and (max-width: 1200px) {
            .saved-addresses-container {
                padding: calc(var(--spacing-unit) * 1.5);
            }
        }

        @media screen and (max-width: 768px) {
            .saved-addresses-container {
                padding: calc(var(--spacing-unit));
            }

            .title {
                font-size: 2rem;
            }

            .addresses-grid {
                gap: 0.8rem;
            }

            .address-card {
                min-width: 100%;
                max-width: 100%;
            }

            .add-address-btn {
                min-width: 100%;
                max-width: 100%;
                height: 140px;
            }

            .modal-content {
                width: 95%;
                padding: 1.5rem;
            }

            .modal-title {
                font-size: 1.3rem;
            }

            .modal-form input[type="text"],
            .modal-form select {
                padding: 0.6rem 1rem;
                font-size: 0.95rem;
            }

            .modal-btn-row {
                flex-direction: column;
                gap: 0.8rem;
            }

            .modal-btn-row button {
                width: 100%;
            }

            .group {
                width: 100px;
                height: 45px;
                top: 20px;
                left: 20px;
            }

            .back-btn {
                width: 100px;
                height: 45px;
            }

            .back {
                font-size: 20px;
                top: 12px;
                left: 18px;
            }
        }

        @media screen and (max-width: 480px) {
            .saved-addresses-container {
                padding: calc(var(--spacing-unit) * 0.8);
            }

            .title {
                font-size: 1.8rem;
            }

            .subtitle {
                font-size: 0.9rem;
            }

            .address-card {
                padding: 1rem;
            }

            .address-title {
                font-size: 1rem;
            }

            .address-details {
                font-size: 0.95rem;
            }

            .modal-content {
                padding: 1.2rem;
            }

            .modal-title {
                font-size: 1.2rem;
                margin-bottom: 1.2rem;
            }

            .modal-form label {
                font-size: 0.95rem;
            }

            .modal-form input[type="text"],
            .modal-form select {
                padding: 0.5rem 0.8rem;
                font-size: 0.9rem;
            }

            .group {
                width: 90px;
                height: 40px;
                top: 15px;
                left: 15px;
            }

            .back-btn {
                width: 90px;
                height: 40px;
            }

            .back {
                font-size: 18px;
                top: 10px;
                left: 16px;
            }

            .pill-alert {
                width: 90%;
                font-size: 0.95rem;
                padding: 0.8rem 1.5rem;
            }
        }

        /* Header styles */
        .header {
            text-align: center;
            margin-bottom: calc(var(--spacing-unit) * 3);
        }

        .title {
            font-size: 2.5rem;
            margin: 0;
            text-shadow: 0 0 10px rgba(89, 184, 160, 0.3);
        }

        .title .white-text {
            color: var(--text-color);
        }

        .title .colored-text {
            color: var(--primary-color);
        }

        .subtitle {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.9);
            margin-top: var(--spacing-unit);
            text-shadow: 0 0 5px rgba(0, 0, 0, 0.5);
        }

        /* Addresses grid */
        .addresses-grid {
            display: flex;
            flex-direction: row;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }

        /* Address card styles */
        .address-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 1.2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            position: relative;
            transition: all 0.3s ease;
            border-left: 4px solid var(--primary-color);
            flex: 1;
            min-width: 280px;
            max-width: 320px;
            margin: 0;
        }

        .address-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(89, 184, 160, 0.2);
        }

        .address-title {
            color: var(--primary-color);
            font-size: 1.1rem;
            margin-bottom: 0.8rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .address-title::before {
            content: '';
            display: inline-block;
            width: 6px;
            height: 6px;
            background-color: var(--primary-color);
            border-radius: 50%;
        }

        .address-details {
            color: #333;
            font-size: 1rem;
            line-height: 1.4;
            white-space: pre-line;
        }

        .complete-address {
            margin: 0;
            padding: 0;
            color: #1a1a1a;
        }

        .address-line {
            margin: 0.5rem 0;
            color: #333;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .address-line.primary {
            font-weight: 500;
            color: #1a1a1a;
        }

        .address-row {
            display: flex;
            gap: 1rem;
            margin: 0.5rem 0;
        }

        .address-row .address-line {
            margin: 0;
        }

        .address-label {
            color: #666;
            font-size: 0.9rem;
            min-width: 80px;
        }

        /* Status indicator */
        .address-status {
            position: absolute;
            top: var(--spacing-unit);
            right: var(--spacing-unit);
        }

        .status-indicator {
            background: rgba(255, 0, 0, 0.2);
            color: #FF4444;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
        }

        /* Add address button */
        .add-address-btn {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 2px dashed rgba(255, 255, 255, 0.3);
            border-radius: 15px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all var(--transition-speed);
            padding: 0;
            flex: 1;
            min-width: 280px;
            max-width: 320px;
            height: 160px;
            margin: 0;
        }

        .add-address-btn:hover {
            border-color: var(--primary-color);
            background: rgba(89, 184, 160, 0.2);
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(89, 184, 160, 0.2);
        }

        .plus-icon {
            width: 48px;
            height: 48px;
            color: var(--text-color);
            transition: all var(--transition-speed);
        }

        .add-address-btn:hover .plus-icon {
            color: var(--primary-color);
            transform: scale(1.1);
        }

        /* Star background */
        .star-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            background: 
                radial-gradient(1px 1px at 10% 10%, white 1px, transparent 0),
                radial-gradient(1px 1px at 20% 30%, white 1px, transparent 0),
                radial-gradient(1px 1px at 30% 50%, white 1px, transparent 0),
                radial-gradient(1px 1px at 40% 70%, white 1px, transparent 0),
                radial-gradient(1px 1px at 50% 90%, white 1px, transparent 0),
                radial-gradient(1px 1px at 60% 10%, white 1px, transparent 0),
                radial-gradient(1px 1px at 70% 30%, white 1px, transparent 0),
                radial-gradient(1px 1px at 80% 50%, white 1px, transparent 0),
                radial-gradient(1px 1px at 90% 70%, white 1px, transparent 0),
                radial-gradient(1px 1px at 100% 90%, white 1px, transparent 0);
            background-size: 200% 200%;
            animation: moveStars 100s linear infinite;
        }

        /* Constellation lines */
        .constellation-lines {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
        }

        .constellation-line {
            position: absolute;
            background: linear-gradient(90deg, rgba(255,255,255,0.1), rgba(255,255,255,0));
            height: 1px;
            transform-origin: left;
            animation: twinkle 4s ease-in-out infinite;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            backdrop-filter: blur(5px);
        }

        .modal.show {
            display: block;
        }

        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.85);
            background: #18332b;
            padding: 2rem;
            border-radius: 25px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            opacity: 0;
            transition: transform 0.35s cubic-bezier(.4,2,.6,1), opacity 0.25s cubic-bezier(.4,2,.6,1);
        }

        .modal.show .modal-content {
            transform: translate(-50%, -50%) scale(1);
            opacity: 1;
        }

        .modal.hide .modal-content {
            transform: translate(-50%, -50%) scale(0.85);
            opacity: 0;
        }

        .modal-title {
            color: #fff;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            font-weight: bold;
        }

        .modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 1.5rem;
            cursor: pointer;
            color: #fff;
            transition: color 0.3s;
        }

        .modal-close:hover {
            color: var(--primary-color);
        }

        .modal-form label {
            color: #fff;
            margin-bottom: 0.2rem;
            font-size: 1rem;
            display: block;
            margin-top: 0.7rem;
            margin-bottom: 0.5rem;
        }

        .modal-form label .required {
            color:rgb(255, 0, 0);
            margin-left: 0.2em;
        }

        .modal-form input[type="text"] {
            width: 100%;
            padding: 0.7rem 1.2rem;
            border: none;
            border-radius: 25px;
            background: #fff;
            color: #18332b;
            font-size: 1rem;
            margin-bottom: 1.2rem;
            outline: none;
            box-sizing: border-box;
            transition: border 0.2s;
        }

        .modal-form input[type="text"]:focus {
            border: 2px solid #59B8A0;
        }

        .modal-form .modal-btn-row {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .modal-form .remove-btn {
            background: #888;
            color: #fff;
            border: none;
            border-radius: 25px;
            padding: 0.7rem 2.2rem;
            font-size: 1.1rem;
            cursor: pointer;
            transition: background 0.2s, transform 0.3s ease-in-out;
        }

        .modal-form .remove-btn:hover {
            background: #b33a3a;
            transform: scale(1.05);
            animation: pulse-remove 2s infinite;
        }

        @keyframes pulse-remove {
            0%, 100% {
                box-shadow: 0 0 5px 0 rgba(179, 58, 58, 0.7);
            }
            50% {
                box-shadow: 0 0 15px 5px rgba(179, 58, 58, 1);
            }
        }

        .modal-form .save-btn {
            background: #e84a8a;
            color: #fff;
            border: none;
            border-radius: 25px;
            padding: 0.7rem 2.2rem;
            font-size: 1.1rem;
            cursor: pointer;
            transition: background 0.2s, transform 0.3s ease-in-out;
        }

        .modal-form .save-btn:hover {
            background: #c13c72;
            transform: scale(1.05);
            animation: pulse-save 2s infinite;
        }

        @keyframes pulse-save {
            0%, 100% {
                box-shadow: 0 0 5px 0 rgba(232, 74, 138, 0.7);
            }
            50% {
                box-shadow: 0 0 15px 5px rgba(232, 74, 138, 1);
            }
        }

        .make-primary-btn {
            background: #59B8A0;
            color: #fff;
            border: none;
            border-radius: 25px;
            padding: 0.7rem 2.2rem;
            font-size: 1.1rem;
            cursor: pointer;
            transition: background 0.2s, transform 0.3s ease-in-out;
        }

        .make-primary-btn:hover {
            background: #4a9c88;
            transform: scale(1.05);
            animation: pulse-primary 2s infinite;
        }

        @keyframes pulse-primary {
            0%, 100% {
                box-shadow: 0 0 5px 0 rgba(89, 184, 160, 0.7);
            }
            50% {
                box-shadow: 0 0 15px 5px rgba(89, 184, 160, 1);
            }
        }

        .remove-confirm-cancel {
            background: #888;
            color: #fff;
            border: none;
            border-radius: 25px;
            padding: 0.7rem 2.2rem;
            font-size: 1.1rem;
            cursor: pointer;
            transition: background 0.2s, transform 0.3s ease-in-out;
        }

        .remove-confirm-cancel:hover {
            background: #b3b3b3;
            transform: scale(1.05);
            animation: pulse-cancel 2s infinite;
        }

        @keyframes pulse-cancel {
            0%, 100% {
                box-shadow: 0 0 5px 0 rgba(179, 179, 179, 0.7);
            }
            50% {
                box-shadow: 0 0 15px 5px rgba(179, 179, 179, 1);
            }
        }

        .remove-confirm-remove {
            background: #d32f2f;
            color: #fff;
            border: none;
            border-radius: 25px;
            padding: 0.7rem 2.2rem;
            font-size: 1.1rem;
            cursor: pointer;
            transition: background 0.2s, transform 0.3s ease-in-out;
        }

        .remove-confirm-remove:hover {
            background: #a31515;
            transform: scale(1.05);
            animation: pulse-confirm-remove 2s infinite;
        }

        @keyframes pulse-confirm-remove {
            0%, 100% {
                box-shadow: 0 0 5px 0 rgba(211, 47, 47, 0.7);
            }
            50% {
                box-shadow: 0 0 15px 5px rgba(211, 47, 47, 1);
            }
        }

        /* Animations */
        @keyframes moveStars {
            0% { background-position: 0% 0%; }
            100% { background-position: 200% 200%; }
        }

        @keyframes twinkle {
            0%, 100% { opacity: 0.1; }
            50% { opacity: 0.3; }
        }

        /* Message styles */
        .message {
            text-align: center;
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 4px;
        }

        .success {
            background-color: rgba(89, 184, 160, 0.2);
            color: var(--primary-color);
        }

        .error {
            background-color: rgba(255, 0, 0, 0.2);
            color:rgb(255, 0, 0);
        }

        .edit-address-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.3rem;
            border-radius: 50%;
            transition: background 0.2s;
            position: absolute;
            top: 1rem;
            right: 1rem;
            z-index: 2;
        }

        .edit-address-btn:hover {
            background: rgba(89, 184, 160, 0.1);
        }

        .edit-pencil-icon {
            width: 24px;
            height: 24px;
            display: block;
        }

        .zip-error {
            color:rgb(255, 0, 0);
            font-size: 0.95rem;
            margin-top: -0.7rem;
            margin-bottom: 0.7rem;
            display: none;
        }

        .pill-alert {
            position: fixed;
            left: 50%;
            top: 2.5rem;
            transform: translateX(-50%) translateY(-100px);
            background: #59B8A0;
            color: #fff;
            padding: 1rem 2.5rem;
            border-radius: 2rem;
            font-size: 1.1rem;
            font-weight: 500;
            box-shadow: 0 4px 24px rgba(0,0,0,0.18);
            opacity: 0;
            z-index: 9999;
            transition: opacity 0.4s cubic-bezier(.4,2,.6,1), transform 0.4s cubic-bezier(.4,2,.6,1);
            pointer-events: none;
        }
        .pill-alert.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
        .pill-alert.hide {
            opacity: 0;
            transform: translateX(-50%) translateY(-100px);
        }
        .pill-alert.error {
            background: #e84a8a;
            color: #fff;
        }

        .group {
            position: fixed;
            width: 124px;
            height: 57px;
            top: 27px;
            left: 45px;
            z-index: 10;
        }

        .back-btn {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.84);
            border-radius: 50px;
            text-decoration: none;
            cursor: pointer;
            transform-origin: center;
            transition: transform 0.3s ease-in-out;
        }

        .back-btn .back {
            color: black;
            font-family: "Arial Rounded MT Bold", Helvetica;
            font-size: 24px;
            user-select: none;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            white-space: nowrap;
        }

        @media screen and (max-width: 768px) {
            .group {
                width: 100px;
                height: 45px;
                top: 20px;
                left: 20px;
            }

            .back-btn .back {
                font-size: 20px;
            }
        }

        @media screen and (max-width: 480px) {
            .group {
                width: 90px;
                height: 40px;
                top: 15px;
                left: 15px;
            }

            .back-btn .back {
                font-size: 18px;
            }
        }

        .back {
            position: absolute;
            top: 15px;
            left: 22px;
            font-family: "Arial Rounded MT Bold-Regular", Helvetica;
            font-weight: 400;
            color:rgb(0, 0, 0);
            font-size: 24px;
            letter-spacing: 0;
            line-height: normal;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <canvas id="starfield-canvas" style="position:fixed;top:0;left:0;width:100vw;height:100vh;z-index:0;pointer-events:none;"></canvas>
    <div class="star-background"></div>
    <div class="constellation-lines"></div>
    <div class="group">
        <a href="account.php" class="back-btn">
            <div class="back">&lt; Back</div>
        </a>
    </div>
    <div class="saved-addresses-container">
        <div class="header">
            <h1 class="title">
                <span class="white-text">Saved</span>
                <span class="colored-text">Addresses</span>
            </h1>
            <p class="subtitle">Maximum 3 addresses only.</p>
        </div>

        <?php if ($message): ?>
            <!-- pill alert only, no message above cards -->
        <?php endif; ?>

        <?php if ($error): ?>
            <!-- pill alert only, no error message above cards -->
        <?php endif; ?>

        <div class="addresses-grid">
            <?php 
            $addressCount = 0;
            for ($i = 1; $i <= 3; $i++): 
                if (!empty($user["addressline1_$i"])):
                    $addressCount++;
            ?>
                <div class="address-card">
                    <h3 class="address-title">Address <?php echo $i; ?><?php if ($i == 1): ?> <span style="color: #59B8A0; font-size: 0.8em; background: rgba(89, 184, 160, 0.1); padding: 2px 8px; border-radius: 12px; margin-left: 8px;">Primary</span><?php endif; ?></h3>
                    <div class="address-details">
                        <div><strong><?php echo htmlspecialchars($user["addressline1_$i"]); ?>,</strong></div>
                        <?php if (!empty($user["addressline2_$i"])): ?>
                            <div><?php echo htmlspecialchars($user["addressline2_$i"]); ?>,</div>
                        <?php endif; ?>
                        <div><?php echo htmlspecialchars($user["zipcode_$i"]); ?>, <?php echo htmlspecialchars($user["city_$i"]); ?>,</div>
                        <div><?php echo htmlspecialchars($user["state_$i"]); ?>, Malaysia.</div>
                    </div>
                    <button class="edit-address-btn" onclick="openEditModal(<?php echo $i; ?>)" title="Edit Address <?php echo $i; ?>">
                        <svg class="edit-pencil-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#59B8A0" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 20h9"/>
                            <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19.5 3 21l1.5-4L16.5 3.5z"/>
                        </svg>
                    </button>
                </div>
            <?php 
                endif;
            endfor; 
            
            // Only show add button if less than 3 addresses
            if ($addressCount < 3):
            ?>
                <button class="add-address-btn" onclick="openAddModal()">
                    <svg class="plus-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 5v14M5 12h14"/>
                    </svg>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Address Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal('editModal')">&times;</span>
            <h2 class="modal-title">Edit <span style="color:#59B8A0;">Address <span id="editAddressTitle"></span></span></h2>
            <form class="modal-form" method="POST" id="editAddressForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="address_number" id="editAddressNumber" value="<?php echo $address_number; ?>">
                <label>Address Line 1 <span class="required">*</span></label>
                <input type="text" name="address_line1" id="editAddressLine1" required>
                <label>Address Line 2 <span class="required">*</span></label>
                <input type="text" name="address_line2" id="editAddressLine2" required>
                <div style="display:flex; gap:1rem;">
                    <div style="flex:1;">
                        <label>Zip Code <span class="required">*</span></label>
                        <input type="text" name="zipcode" id="editZipcode" required>
                        <div class="zip-error" id="editZipError">Zip Code must be numbers only.</div>
                    </div>
                    <div style="flex:1;">
                        <label>City <span class="required">*</span></label>
                        <input type="text" name="city" id="editCity" required>
                    </div>
                </div>
                <label>State <span class="required">*</span></label>
                <select name="state" id="editState" required style="width:100%;padding:0.7rem 1.2rem;border:none;border-radius:25px;background:#fff;color:#18332b;font-size:1rem;margin-bottom:1.2rem;outline:none;box-sizing:border-box;">
                    <option value="">Select State</option>
                    <option value="Johor">Johor</option>
                    <option value="Kedah">Kedah</option>
                    <option value="Kelantan">Kelantan</option>
                    <option value="Melaka">Melaka</option>
                    <option value="Negeri Sembilan">Negeri Sembilan</option>
                    <option value="Pahang">Pahang</option>
                    <option value="Perak">Perak</option>
                    <option value="Perlis">Perlis</option>
                    <option value="Pulau Pinang">Pulau Pinang</option>
                    <option value="Sabah">Sabah</option>
                    <option value="Sarawak">Sarawak</option>
                    <option value="Selangor">Selangor</option>
                    <option value="Terengganu">Terengganu</option>
                    <option value="Wilayah Persekutuan Kuala Lumpur">Wilayah Persekutuan Kuala Lumpur</option>
                    <option value="Wilayah Persekutuan Labuan">Wilayah Persekutuan Labuan</option>
                    <option value="Wilayah Persekutuan Putrajaya">Wilayah Persekutuan Putrajaya</option>
                </select>
                <div class="modal-btn-row">
                    <?php 
                    $addressCount = 0;
                    if (!empty($user['addressline1_1'])) $addressCount++;
                    if (!empty($user['addressline1_2'])) $addressCount++;
                    if (!empty($user['addressline1_3'])) $addressCount++;
                    if ($addressCount > 1): ?>
                        <button type="button" class="remove-btn" onclick="removeAddress()">Remove</button>
                    <?php endif; ?>
                    <button type="button" class="make-primary-btn" onclick="makePrimary()" style="display: none;">Make Primary</button>
                    <button type="submit" class="save-btn">Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Address Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal('addModal')">&times;</span>
            <h2 class="modal-title">Add <span style="color:#59B8A0;">New Address</span></h2>
            <form class="modal-form" method="POST" id="addAddressForm">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="address_number" value="<?php 
                    for ($i = 1; $i <= 3; $i++) {
                        if (empty($user["addressline1_$i"])) {
                            echo $i;
                            break;
                        }
                    }
                ?>">
                <label>Address Line 1 <span class="required">*</span></label>
                <input type="text" name="address_line1" required>
                <label>Address Line 2 <span class="required">*</span></label>
                <input type="text" name="address_line2" required>
                <div style="display:flex; gap:1rem;">
                    <div style="flex:1;">
                        <label>Zip Code <span class="required">*</span></label>
                        <input type="text" name="zipcode" id="addZipcode" required>
                        <div class="zip-error" id="addZipError">Zip Code must be numbers only.</div>
                    </div>
                    <div style="flex:1;">
                        <label>City <span class="required">*</span></label>
                        <input type="text" name="city" required>
                    </div>
                </div>
                <label>State <span class="required">*</span></label>
                <select name="state" id="addState" required style="width:100%;padding:0.7rem 1.2rem;border:none;border-radius:25px;background:#fff;color:#18332b;font-size:1rem;margin-bottom:1.2rem;outline:none;box-sizing:border-box;">
                    <option value="">Select State</option>
                    <option value="Johor">Johor</option>
                    <option value="Kedah">Kedah</option>
                    <option value="Kelantan">Kelantan</option>
                    <option value="Melaka">Melaka</option>
                    <option value="Negeri Sembilan">Negeri Sembilan</option>
                    <option value="Pahang">Pahang</option>
                    <option value="Perak">Perak</option>
                    <option value="Perlis">Perlis</option>
                    <option value="Pulau Pinang">Pulau Pinang</option>
                    <option value="Sabah">Sabah</option>
                    <option value="Sarawak">Sarawak</option>
                    <option value="Selangor">Selangor</option>
                    <option value="Terengganu">Terengganu</option>
                    <option value="Wilayah Persekutuan Kuala Lumpur">Wilayah Persekutuan Kuala Lumpur</option>
                    <option value="Wilayah Persekutuan Labuan">Wilayah Persekutuan Labuan</option>
                    <option value="Wilayah Persekutuan Putrajaya">Wilayah Persekutuan Putrajaya</option>
                </select>
                <div class="modal-btn-row">
                    <button type="submit" class="save-btn">Add Address</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Remove Address Confirmation Modal -->
    <div id="removeConfirmModal" class="modal">
        <div class="modal-content" style="max-width: 400px; text-align: center;">
            <span class="modal-close" onclick="closeModal('removeConfirmModal')">&times;</span>
            <h2 class="modal-title" style="margin-bottom:1.5rem;">Remove Address</h2>
            <p style="color:#fff; font-size:1.1rem; margin-bottom:2rem;">Are you sure you want to remove this address?</p>
            <div class="modal-btn-row" style="justify-content: center; gap: 1.5rem;">
                <button type="button" class="remove-confirm-cancel" onclick="closeModal('removeConfirmModal')">Cancel</button>
                <button type="button" class="remove-confirm-remove" id="confirmRemoveBtn">Remove</button>
            </div>
        </div>
    </div>

    <div id="pillAlert" class="pill-alert"></div>

    <script>
        // Create constellation lines
        function createConstellationLines() {
            const container = document.querySelector('.constellation-lines');
            const lines = 20;
            
            for (let i = 0; i < lines; i++) {
                const line = document.createElement('div');
                line.className = 'constellation-line';
                
                // Random position
                const x1 = Math.random() * 100;
                const y1 = Math.random() * 100;
                const x2 = Math.random() * 100;
                const y2 = Math.random() * 100;
                
                // Calculate length and angle
                const length = Math.sqrt(Math.pow(x2 - x1, 2) + Math.pow(y2 - y1, 2));
                const angle = Math.atan2(y2 - y1, x2 - x1) * 180 / Math.PI;
                
                // Set styles
                line.style.left = `${x1}%`;
                line.style.top = `${y1}%`;
                line.style.width = `${length}%`;
                line.style.transform = `rotate(${angle}deg)`;
                line.style.animationDelay = `${Math.random() * 4}s`;
                
                container.appendChild(line);
            }
        }

        // Modal functions
        function showModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.add('show');
            modal.classList.remove('hide');
        }

        function hideModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.add('hide');
            modal.classList.remove('show');
            setTimeout(() => { modal.style.display = 'none'; modal.classList.remove('hide'); }, 350);
        }

        function openEditModal(addressNumber) {
            const modal = document.getElementById('editModal');
            document.getElementById('editAddressNumber').value = addressNumber;
            document.getElementById('editAddressTitle').textContent = addressNumber;
            // Get current address string from PHP-rendered card
            const card = document.querySelector(`.address-card:nth-child(${addressNumber}) .address-details`);
            if (card) {
                // Get the address parts from the PHP array (rendered as text)
                const lines = card.querySelectorAll('div');
                // Remove comma and trim for address lines
                const line1 = lines[0]?.textContent.replace(/,/g, '').trim() || '';
                const line2 = lines[1]?.textContent.replace(/,/g, '').trim() || '';
                let zip = '', city = '', state = '';
                if (lines[2]) {
                    const parts = lines[2].textContent.split(',');
                    zip = parts[0]?.trim() || '';
                    city = parts[1]?.trim() || '';
                }
                if (lines[3]) {
                    state = lines[3].textContent.replace(', Malaysia.', '').trim();
                }
                document.getElementById('editAddressLine1').value = line1;
                document.getElementById('editAddressLine2').value = line2;
                document.getElementById('editZipcode').value = zip;
                document.getElementById('editCity').value = city;
                document.getElementById('editState').value = state;
            }
            // Only try to set removeBtn.disabled if removeBtn exists
            const removeBtn = document.querySelector('#editAddressForm .remove-btn');
            if (removeBtn) {
                let addressCount = 0;
                if (<?php echo json_encode(!empty($user['addressline1_1'])); ?>) addressCount++;
                if (<?php echo json_encode(!empty($user['addressline1_2'])); ?>) addressCount++;
                if (<?php echo json_encode(!empty($user['addressline1_3'])); ?>) addressCount++;
                if (addressNumber == 1 && addressCount <= 1) {
                    removeBtn.disabled = true;
                    removeBtn.title = 'You must have at least one address.';
                } else {
                    removeBtn.disabled = false;
                    removeBtn.title = '';
                }
            }
            // Show/hide Make Primary button based on address number
            const makePrimaryBtn = document.querySelector('#editAddressForm .make-primary-btn');
            if (makePrimaryBtn) {
                makePrimaryBtn.style.display = addressNumber > 1 ? 'block' : 'none';
            }
            modal.style.display = 'block';
            setTimeout(() => showModal('editModal'), 10);
        }

        function openAddModal() {
            const modal = document.getElementById('addModal');
            modal.style.display = 'block';
            setTimeout(() => showModal('addModal'), 10);
        }

        function closeModal(modalId) {
            hideModal(modalId);
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                hideModal(event.target.id);
            }
        }

        let pendingRemove = false;
        function removeAddress() {
            const removeBtn = document.querySelector('#editAddressForm .remove-btn');
            // JS check for only one address left
            let addressCount = 0;
            if (<?php echo json_encode(!empty($user['addressline1_1'])); ?>) addressCount++;
            if (<?php echo json_encode(!empty($user['addressline1_2'])); ?>) addressCount++;
            if (<?php echo json_encode(!empty($user['addressline1_3'])); ?>) addressCount++;
            const addressNumber = document.getElementById('editAddressNumber').value;
            if (addressCount <= 1 && addressNumber == 1) {
                showPillAlert('You must have at least one address.', true);
                return;
            }
            if (removeBtn.disabled) {
                showPillAlert('You must have at least one address.', true);
                return;
            }
            // Show custom confirmation modal
            pendingRemove = true;
            const modal = document.getElementById('removeConfirmModal');
            modal.style.display = 'block';
            setTimeout(() => showModal('removeConfirmModal'), 10);
        }
        document.getElementById('confirmRemoveBtn').onclick = function() {
            if (pendingRemove) {
                const form = document.getElementById('editAddressForm');
                form.action.value = 'remove';
                pendingRemove = false;
                closeModal('removeConfirmModal');
                setTimeout(() => form.submit(), 350); // Wait for animation
            }
        };

        // Zip code validation for Edit Address
        const editForm = document.getElementById('editAddressForm');
        const editZip = document.getElementById('editZipcode');
        const editZipError = document.getElementById('editZipError');
        if (editForm) {
            editForm.addEventListener('submit', function(e) {
                if (!/^\d+$/.test(editZip.value)) {
                    editZipError.style.display = 'block';
                    editZip.focus();
                    e.preventDefault();
                } else {
                    editZipError.style.display = 'none';
                }
            });
            editZip.addEventListener('input', function() {
                if (/^\d*$/.test(editZip.value)) {
                    editZipError.style.display = 'none';
                }
            });
        }

        // Zip code validation for Add Address
        const addForm = document.getElementById('addAddressForm');
        const addZip = document.getElementById('addZipcode');
        const addZipError = document.getElementById('addZipError');
        if (addForm) {
            addForm.addEventListener('submit', function(e) {
                if (!/^\d+$/.test(addZip.value)) {
                    addZipError.style.display = 'block';
                    addZip.focus();
                    e.preventDefault();
                } else {
                    addZipError.style.display = 'none';
                }
            });
            addZip.addEventListener('input', function() {
                if (/^\d*$/.test(addZip.value)) {
                    addZipError.style.display = 'none';
                }
            });
        }

        // Initialize constellation lines
        createConstellationLines();

        function showPillAlert(message, isError = false) {
            const alert = document.getElementById('pillAlert');
            alert.textContent = message;
            if (isError) {
                alert.classList.add('error');
            } else {
                alert.classList.remove('error');
            }
            alert.classList.add('show');
            alert.classList.remove('hide');
            setTimeout(() => {
                alert.classList.add('hide');
                alert.classList.remove('show');
            }, 3000);
        }

        // Show alert if PHP set a message
        <?php if (!empty($message)): ?>
            window.addEventListener('DOMContentLoaded', function() {
                showPillAlert(<?php echo json_encode($message); ?>);
            });
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            window.addEventListener('DOMContentLoaded', function() {
                showPillAlert(<?php echo json_encode($error); ?>, true);
            });
        <?php endif; ?>

        // Animated starfield background
        (function() {
            const canvas = document.getElementById('starfield-canvas');
            const ctx = canvas.getContext('2d');
            let stars = [];
            const STAR_COUNT = 250;
            const STAR_MIN_RADIUS = 0.7;
            const STAR_MAX_RADIUS = 1.8;
            const STAR_MIN_SPEED = 0.05;
            const STAR_MAX_SPEED = 0.25;

            function resizeCanvas() {
                canvas.width = window.innerWidth;
                canvas.height = window.innerHeight;
            }
            window.addEventListener('resize', resizeCanvas);
            resizeCanvas();

            function randomBetween(a, b) {
                return a + Math.random() * (b - a);
            }

            function createStar() {
                const angle = Math.random() * 2 * Math.PI;
                const speed = randomBetween(STAR_MIN_SPEED, STAR_MAX_SPEED);
                return {
                    x: Math.random() * canvas.width,
                    y: Math.random() * canvas.height,
                    r: randomBetween(STAR_MIN_RADIUS, STAR_MAX_RADIUS),
                    dx: Math.cos(angle) * speed,
                    dy: Math.sin(angle) * speed,
                    twinkle: Math.random() * Math.PI * 2
                };
            }

            function initStars() {
                stars = [];
                for (let i = 0; i < STAR_COUNT; i++) {
                    stars.push(createStar());
                }
            }
            initStars();

            function animateStars() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                for (let star of stars) {
                    // Twinkle effect
                    const twinkle = 0.5 + 0.5 * Math.sin(star.twinkle + Date.now() * 0.002);
                    ctx.globalAlpha = 0.7 * twinkle;
                    ctx.beginPath();
                    ctx.arc(star.x, star.y, star.r, 0, 2 * Math.PI);
                    ctx.fillStyle = '#fff';
                    ctx.shadowColor = '#fff';
                    ctx.shadowBlur = 8 * twinkle;
                    ctx.fill();
                    ctx.shadowBlur = 0;
                    ctx.globalAlpha = 1;
                    // Move star
                    star.x += star.dx;
                    star.y += star.dy;
                    // Bounce off edges
                    if (star.x < 0 || star.x > canvas.width) star.dx *= -1;
                    if (star.y < 0 || star.y > canvas.height) star.dy *= -1;
                }
                requestAnimationFrame(animateStars);
            }
            animateStars();
        })();

        function makePrimary() {
            const addressNumber = document.getElementById('editAddressNumber').value;
            if (addressNumber > 1) {
                const form = document.getElementById('editAddressForm');
                form.action.value = 'make_primary';
                form.submit();
            }
        }
    </script>
</body>
</html> 