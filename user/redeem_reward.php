<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

if (!isset($_POST['reward_id'])) {
    echo json_encode(['success' => false, 'error' => 'No reward_id provided']);
    exit();
}

$user_id = $_SESSION['user']['user_id'];
$reward_id = $_POST['reward_id'];

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "nothing_wasted";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'DB connection failed']);
    exit();
}

// 1. Generate the lowest available redemption_id in the format red#
$red_id = 1;
$existing = [];
$res = $conn->query("SELECT redemption_id FROM redemption");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        if (preg_match('/^red(\d+)$/', $row['redemption_id'], $m)) {
            $existing[(int)$m[1]] = true;
        }
    }
}
while (isset($existing[$red_id])) {
    $red_id++;
}
$redemption_id = 'red' . $red_id;

// 2. Get a random available rewardkey_id for this reward
$sql = "SELECT rewardkey_id FROM rewardkey WHERE reward_id = ? AND is_used = 0 ORDER BY RAND() LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $reward_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $rewardkey_id = $row['rewardkey_id'];
} else {
    echo json_encode(['success' => false, 'error' => 'No available reward key']);
    exit();
}

// 3. Insert into redemption table
$date = date('Y-m-d H:i:s');
$sql = "INSERT INTO redemption (redemption_id, date, user_id, reward_id, rewardkey_id) VALUES (?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssss", $redemption_id, $date, $user_id, $reward_id, $rewardkey_id);
if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'error' => 'Failed to insert redemption']);
    exit();
}

// 4. Update rewardkey as used and assign user_id
$sql = "UPDATE rewardkey SET is_used = 1, user_id = ? WHERE rewardkey_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $user_id, $rewardkey_id);
if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'error' => 'Failed to update rewardkey']);
    exit();
}

// Deduct points from user
$sql = "SELECT points_needed FROM reward WHERE reward_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $reward_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$points_needed = $row['points_needed'];
$sql = "UPDATE user SET point = point - ? WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $points_needed, $user_id);
$stmt->execute();

// 6. Check if any more available reward keys for this reward
$sql = "SELECT COUNT(*) as cnt FROM rewardkey WHERE reward_id = ? AND is_used = 0";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $reward_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
if ($row['cnt'] == 0) {
    // No more available keys, set reward status to 0
    $sql = "UPDATE reward SET status = 0 WHERE reward_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $reward_id);
    $stmt->execute();
}

// 5. Optionally, deduct points from user (if needed)
// $sql = "UPDATE user SET point = point - (SELECT points_needed FROM reward WHERE reward_id = ?) WHERE user_id = ?";
// $stmt = $conn->prepare($sql);
// $stmt->bind_param("ss", $reward_id, $user_id);
// $stmt->execute();

// 6. Return success
echo json_encode([
    'success' => true,
    'redemption_id' => $redemption_id,
    'rewardkey_id' => $rewardkey_id,
    'date' => $date
]); 