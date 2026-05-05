<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: auth.php");
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "nothing_wasted";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get user's points
$user_id = $_SESSION['user']['user_id'];
$sql = "SELECT point FROM user WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$points = $user['point'];

// Fetch available rewards (status = 1 and has available keys)
$sql = "SELECT r.*, COUNT(CASE WHEN rk.is_used = 0 THEN 1 END) as available_keys 
        FROM reward r 
        LEFT JOIN rewardkey rk ON r.reward_id = rk.reward_id 
        WHERE r.status = 1 
        GROUP BY r.reward_id 
        HAVING available_keys > 0 
        ORDER BY r.points_needed ASC";
$rewards = $conn->query($sql);

// Fetch user's redemptions with reward info
$user_redemptions = [];
$sql = "SELECT r.redemption_id, r.date, r.reward_id, r.rewardkey_id, rw.reward_name, rw.description, rw.points_needed
        FROM redemption r
        JOIN reward rw ON r.reward_id = rw.reward_id
        WHERE r.user_id = ?
        ORDER BY r.date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $user_redemptions[] = $row;
}
// Sort redemptions by date in descending order
usort($user_redemptions, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rewards - Nothing Wasted</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
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

        body, html {
            overflow-x: hidden;
        }

        body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            background: url('assets/edit/bg.png') no-repeat center center fixed;
            background-size: cover;
            color: var(--text-color);
            font-family: Arial, sans-serif;
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
        .rewards-container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: calc(var(--spacing-unit) * 2);
            position: relative;
            z-index: 2;
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
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.9);
            margin-top: var(--spacing-unit);
            text-shadow: 0 0 5px rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .points-display {
            display: inline-flex;
            align-items: center;
            background: rgba(89, 184, 160, 0.2);
            padding: 12px 24px;
            border-radius: 25px;
            margin-left: 10px;
            gap: 12px;
        }

        .points-icon {
            width: 32px;
            height: 32px;
            object-fit: contain;
            margin-right: 0;
        }

        /* Rewards grid */
        .rewards-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            padding: 1rem;
            min-height: 200px;
        }

        /* Reward card styles */
        .reward-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            cursor: pointer;
        }

        .reward-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(89, 184, 160, 0.2);
        }

        .reward-image {
            width: 100%;
            height: 180px;
            object-fit: cover;
            border-bottom: 3px solid var(--primary-color);
        }

        .reward-content {
            padding: 1.5rem;
        }

        .reward-name {
            color: #333;
            font-size: 1.2rem;
            font-weight: bold;
            margin: 0 0 0.5rem 0;
        }

        .reward-description {
            color: #666;
            font-size: 0.9rem;
            margin: 0 0 1rem 0;
            line-height: 1.4;
        }

        .reward-points {
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--primary-color);
            font-weight: bold;
            font-size: 1.1rem;
        }

        .points-needed {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .points-icon-small {
            width: 24px;
            height: 24px;
            object-fit: contain;
        }

        /* Back button styles */
        .group {
            position: absolute;
            width: 124px;
            height: 57px;
            top: 27px;
            left: 45px;
            z-index: 3;
        }

        .back-btn {
            position: fixed;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 122px;
            height: 57px;
            background-color: rgba(255, 255, 255, 0.84);
            border-radius: 50px;
            text-decoration: none;
            cursor: pointer;
            transform-origin: center;
            transition: transform 0.3s ease-in-out;
        }

        .back-btn:hover {
            transform: scale(1.05);
            animation: pulse-back 2s infinite;
        }

        @keyframes pulse-back {
            0%, 100% {
                box-shadow: 0 0 5px 0 rgba(255, 255, 255, 0.7);
            }
            50% {
                box-shadow: 0 0 15px 5px rgba(255, 255, 255, 1);
            }
        }

        .back {
            position: absolute;
            top: 15px;
            left: 22px;
            font-family: "Arial Rounded MT Bold-Regular", Helvetica;
            font-weight: 400;
            color: rgb(0, 0, 0);
            font-size: 24px;
            letter-spacing: 0;
            line-height: normal;
            white-space: nowrap;
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
            max-width: 600px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            opacity: 0;
            transition: transform 0.35s cubic-bezier(.4,2,.6,1), opacity 0.25s cubic-bezier(.4,2,.6,1);
            color: #fff;
        }

        .modal.show .modal-content {
            transform: translate(-50%, -50%) scale(1);
            opacity: 1;
        }

        .modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 1.5rem;
            cursor: pointer;
            color: #fff;
            background: none;
            border: none;
            padding: 0.5rem;
            transition: color 0.3s;
        }

        .modal-close:hover {
            color: var(--primary-color);
        }

        .modal-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .modal-title {
            font-size: 2rem;
            margin: 0;
            color: var(--primary-color);
            text-shadow: 0 0 10px rgba(89, 184, 160, 0.3);
        }

        .modal-body {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            align-items: start;
        }

        .modal-image {
            width: 100%;
            height: 300px;
            object-fit: cover;
            border-radius: 12px;
            border: 2px solid var(--primary-color);
        }

        .modal-details {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            height: 100%;
            align-items: center;
        }

        .modal-description {
            font-size: 1.1rem;
            line-height: 1.6;
            color: rgba(255, 255, 255, 0.9);
        }

        .modal-points {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.3rem;
            color: var(--primary-color);
            font-weight: bold;
        }

        .modal-points .points-icon-small {
            width: 32px;
            height: 32px;
        }

        .redeem-btn {
            width: auto;
            min-width: 180px;
            padding: 1rem 2.5rem;
            background: var(--primary-color);
            border: none;
            border-radius: 25px;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: auto;
            display: block;
        }

        .redeem-btn:hover {
            background: #4a9c88;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(89, 184, 160, 0.3);
        }

        .redeem-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .insufficient-points {
            color: #ff6b6b;
            text-align: center;
            margin-top: 1rem;
            font-size: 1rem;
            padding: 0.5rem;
            background: rgba(255, 107, 107, 0.1);
            border-radius: 8px;
        }

        /* Star background animation */
        .star-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
        }

        @keyframes twinkle {
            0%, 100% { opacity: 0.3; }
            50% { opacity: 0.8; }
        }

        .star {
            position: absolute;
            background: white;
            border-radius: 50%;
            animation: twinkle var(--twinkle-duration) ease-in-out infinite;
        }

        /* Search bar styles */
        .search-container {
            margin-bottom: 2rem;
            display: flex;
            justify-content: center;
        }

        .search-bar {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 25px;
            padding: 0.8rem 1.5rem;
            width: 100%;
            max-width: 400px;
            display: flex;
            align-items: center;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .search-bar input {
            background: none;
            border: none;
            color: white;
            width: 100%;
            padding: 0;
            margin-left: 10px;
            font-size: 1rem;
        }

        .search-bar input::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        .search-bar input:focus {
            outline: none;
        }

        .search-icon {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.2rem;
        }

        /* No rewards message */
        .no-rewards {
            text-align: center;
            color: #fff;
            font-size: 1.2rem;
            padding: 2rem;
            background: rgba(89, 184, 160, 0.1);
            border-radius: 15px;
            margin: 2rem auto;
            max-width: 500px;
            width: 100%;
            grid-column: 1 / -1;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .modal-actions {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: 2rem;
        }

        /* Message Modal styles */
        #messageModal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0; top: 0; width: 100vw; height: 100vh;
            background: rgba(0,0,0,0.6);
            align-items: center;
            justify-content: center;
        }

        #messageModal.show {
            display: flex;
        }

        /* Pill Popup styles */
        .pill-popup {
            position: fixed;
            top: -100px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            min-width: 320px;
            max-width: 90vw;
            padding: 18px 40px;
            border-radius: 50px;
            font-size: 20px;
            font-family: "Arial Rounded MT Bold", Helvetica;
            color: #fff;
            text-align: center;
            box-shadow: 0 4px 24px rgba(0,0,0,0.18);
            opacity: 0;
            pointer-events: none;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .pill-popup.show {
            top: 40px;
            opacity: 1;
            pointer-events: auto;
        }
        .pill-popup.error {
            background: #e74c3c;
        }
        .pill-popup.success {
            background: #27ae60;
        }

        .points-section-modal {
            margin: 0 2rem 0 2rem;
            margin-top: 20px;
            margin-bottom: 0;
            padding: 15px;
            background: rgba(0,0,0,0.7);
            border-radius: 12px;
            border: 1px solid #222;
            color: #fff;
            font-size: 1.1rem;
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: flex-start;
        }
        .points-section-modal .points-row {
            display: flex;
            justify-content: space-between;
            width: 100%;
        }
        .points-section-modal .points-label {
            font-weight: 600;
            color: #fff;
        }
        .points-section-modal .points-value {
            font-weight: 700;
            color: #59B8A0;
        }

        /* Inventory Icon Button styles */
        .inventory-icon-btn {
            position: fixed;
            top: 32px;
            right: 40px;
            z-index: 3000;
            width: 60px;
            height: 60px;
            background: rgba(255,255,255,0.7);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            cursor: pointer;
            transition: background 0.2s, box-shadow 0.2s;
        }
        .inventory-icon-btn:hover {
            background: rgba(255,255,255,0.9);
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
        }
        .inventory-icon-btn i {
            font-size: 2rem;
            color: #18332b;
        }

        /* Inventory Sidebar styles */
        .inventory-sidebar {
            position: fixed;
            top: 0;
            right: 0;
            width: 50vw;
            max-width: 100vw;
            height: 100vh;
            background: rgba(24, 51, 43, 0.98);
            box-shadow: -2px 0 16px rgba(0,0,0,0.18);
            z-index: 4000;
            transition: transform 0.7s cubic-bezier(.4,2,.6,1);
            display: flex;
            flex-direction: column;
            transform: translateX(100%);
            will-change: transform;
            overflow-y: hidden;
        }
        .inventory-sidebar.open {
            transform: translateX(0);
        }
        .sidebar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 2rem 1rem 2rem;
            font-size: 1.5rem;
            color: #fff;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .close-sidebar-btn {
            background: none;
            border: none;
            color: #fff;
            font-size: 2rem;
            cursor: pointer;
            padding: 0 0.5rem;
            transition: color 0.2s;
        }
        .close-sidebar-btn:hover {
            color: #59B8A0;
        }
        .sidebar-content {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #59B8A0 #18332b;
            max-height: calc(100vh - 80px);
        }
        .sidebar-content::-webkit-scrollbar {
            width: 8px;
        }
        .sidebar-content::-webkit-scrollbar-thumb {
            background: #59B8A0;
        }
        .sidebar-content::-webkit-scrollbar-track {
            background: #18332b;
        }
        .sidebar-search-bar input::placeholder {
            color: #b2dfdb;
            opacity: 1;
        }
        .sidebar-search-bar input:focus {
            outline: 2px solid #59B8A0;
        }
        .sidebar-header span {
            font-weight: bold;
            letter-spacing: 1px;
        }
        @media (max-width: 600px) {
            .inventory-sidebar {
                width: 100vw;
            }
        }
        .redemption-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        .redemption-row {
            background: rgba(255,255,255,0.07);
            border-radius: 10px;
            padding: 1.2rem 1.5rem;
            color: #fff;
            cursor: pointer;
            transition: background 0.2s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .redemption-row:hover {
            background: rgba(89,184,160,0.18);
        }
        .redemption-row-main {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .redemption-row-title {
            font-weight: bold;
            font-size: 1.1rem;
        }
        .redemption-row-date {
            font-size: 0.95rem;
            color: #b2dfdb;
        }
        .redemption-row-desc {
            font-size: 0.98rem;
            margin-bottom: 0.5rem;
            color: #e0e0e0;
        }
        .redemption-row-points {
            font-size: 0.98rem;
            color: #59B8A0;
            font-weight: 600;
        }
        .redeemkey-modal {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(0,0,0,0.5);
            z-index: 99999;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s cubic-bezier(.4,2,.6,1);
            display: flex;
        }
        .redeemkey-modal.show {
            opacity: 1;
            pointer-events: auto;
        }
        .redeemkey-modal-content {
            background: #18332b;
            color: #fff;
            border-radius: 16px;
            padding: 2rem 2.5rem;
            min-width: 320px;
            max-width: 90vw;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0,0,0,0.25);
            transform: scale(0.7);
            opacity: 0;
            transition: transform 0.3s cubic-bezier(.4,2,.6,1), opacity 0.3s cubic-bezier(.4,2,.6,1);
        }
        .redeemkey-modal.show .redeemkey-modal-content {
            transform: scale(1);
            opacity: 1;
        }
        .redeemkey-modal-content button.copied {
            background: #35796a !important;
            color: #fff !important;
            transition: background 0.2s;
        }
        .redeemkey-modal-content .close-btn {
            background: #888;
            color: #fff;
            border: none;
            border-radius: 20px;
            padding: 0.5rem 2rem;
            cursor: pointer;
            font-size: 1rem;
            margin-top: 1rem;
            transition: background 0.2s;
        }
        .redeemkey-modal-content .close-btn:hover {
            background: #555;
        }
    </style>
</head>
<body>
    <div class="star-background"></div>
    <div class="group">
        <a href="account.php" class="back-btn">
            <div class="back">&lt; Back</div>
        </a>
    </div>
    <div class="rewards-container">
        <div class="header">
            <h1 class="title">
                <span class="white-text">Available</span>
                <span class="colored-text">Rewards</span>
            </h1>
            <p class="subtitle">
                Your current points:
                <span class="points-display">
                    <img src="assets/account/point.png" alt="Points" class="points-icon">
                    <?php echo htmlspecialchars($points); ?> Points
                </span>
            </p>
        </div>

        <div class="search-container">
            <div class="search-bar">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="searchInput" placeholder="Search rewards...">
            </div>
        </div>

        <div class="rewards-grid">
            <?php 
            if ($rewards->num_rows > 0):
                while ($reward = $rewards->fetch_assoc()):
                    $image_src = $reward['picture'] ? 'data:image/jpeg;base64,' . base64_encode($reward['picture']) : 'assets/account/no-image.png';
                    $reward_for_json = $reward;
                    unset($reward_for_json['picture']);
                    $reward_json = json_encode($reward_for_json, JSON_HEX_APOS | JSON_HEX_QUOT);
                    echo "<!-- DEBUG: " . htmlspecialchars($reward_json) . " -->";
            ?>
                <div class="reward-card" data-reward='<?php echo $reward_json; ?>' data-image="<?php echo htmlspecialchars($image_src); ?>">
                    <img src="<?php echo htmlspecialchars($image_src); ?>" alt="<?php echo htmlspecialchars($reward['reward_name']); ?>" class="reward-image">
                    <div class="reward-content">
                        <h3 class="reward-name"><?php echo htmlspecialchars($reward['reward_name']); ?></h3>
                        <p class="reward-description"><?php echo htmlspecialchars($reward['description']); ?></p>
                        <div class="reward-points">
                            <span class="points-needed">
                                <img src="assets/account/point.png" alt="Points" class="points-icon-small">
                                <?php echo number_format($reward['points_needed']); ?>
                            </span>
                            Points
                        </div>
                    </div>
                </div>
            <?php 
                endwhile;
            else:
            ?>
                <div class="no-rewards">
                    <p>No rewards available at the moment.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Reward Modal -->
    <div id="rewardModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal()">&times;</button>
            <div class="modal-header">
                <h2 id="modalTitle" class="modal-title"></h2>
            </div>
            <div class="modal-body">
                <div>
                    <img id="modalImage" src="" alt="Reward" class="modal-image">
                    <div class="modal-points" style="margin-top: 1.5rem; justify-content: center;">
                        <img src="assets/account/point.png" alt="Points" class="points-icon-small">
                        <span id="modalPoints"></span> Points
                    </div>
                </div>
                <div class="modal-details">
                    <p id="modalDescription" class="modal-description"></p>
                </div>
            </div>
            <div class="points-section-modal">
                <div class="points-row">
                    <span class="points-label">Your Points:</span>
                    <span class="points-value" id="userCurrentPoints"></span>
                </div>
                <div class="points-row">
                    <span class="points-label">Points Needed:</span>
                    <span class="points-value" id="rewardPointsNeeded"></span>
                </div>
                <div class="points-row">
                    <span class="points-label">Points After Redemption:</span>
                    <span class="points-value" id="userRemainingPoints"></span>
                </div>
            </div>
            <div class="modal-actions">
                <button id="redeemButton" class="redeem-btn" onclick="redeemReward()">Redeem Reward</button>
                <div id="insufficientPoints" class="insufficient-points" style="display: none;">
                    You don't have enough points for this reward
                </div>
            </div>
        </div>
    </div>

    <!-- Pill Popup for Redemption Message -->
    <div class="pill-popup success" id="rewardPillPopup" style="display:none;"></div>

    <!-- Inventory Icon Button -->
    <div id="inventoryIconBtn" class="inventory-icon-btn">
        <i class="fas fa-box-open"></i>
    </div>
    <!-- Inventory Sidebar -->
    <div id="inventorySidebar" class="inventory-sidebar">
        <div class="sidebar-header">
            <span>Redemptions</span>
            <button class="close-sidebar-btn" onclick="closeInventorySidebar()">&times;</button>
        </div>
        <div class="sidebar-content">
            <div class="sidebar-search-bar" style="margin-bottom:1.5rem;display:flex;align-items:center;gap:10px;">
                <i class="fas fa-search search-icon" style="color:#fff;"></i>
                <input type="text" id="sidebarSearchInput" placeholder="Search redemptions..." style="flex:1;background:rgba(255,255,255,0.08);border:none;color:#fff;padding:0.7rem 1.2rem;border-radius:20px;font-size:1rem;outline:none;">
            </div>
            <?php if (empty($user_redemptions)): ?>
                <p style="color: #fff;">No redemptions yet.</p>
            <?php else: ?>
                <div class="redemption-list">
                <?php foreach ($user_redemptions as $red): ?>
                    <div class="redemption-row" 
                        data-redeemkey-id="<?php echo htmlspecialchars($red['rewardkey_id']); ?>"
                        data-reward-name="<?php echo htmlspecialchars($red['reward_name']); ?>"
                        data-redemption-id="<?php echo htmlspecialchars($red['redemption_id']); ?>"
                        data-description="<?php echo htmlspecialchars($red['description']); ?>"
                        data-date="<?php echo htmlspecialchars($red['date']); ?>">
                        <div class="redemption-row-main">
                            <div class="redemption-row-title"><?php echo htmlspecialchars($red['reward_name']); ?></div>
                            <div class="redemption-row-date"><?php echo date('d M Y', strtotime($red['date'])); ?></div>
                        </div>
                        <div class="redemption-row-desc"><?php echo htmlspecialchars($red['description']); ?></div>
                        <div class="redemption-row-points">Points spent: <span><?php echo htmlspecialchars($red['points_needed']); ?></span></div>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Redeem Key Modal -->
    <div id="redeemKeyModal" class="redeemkey-modal">
        <div class="redeemkey-modal-content">
            <span id="redeemKeyModalTitle" style="font-weight:bold;font-size:1.2rem;"></span>
            <div style="margin: 1.5rem 0;">
                <span id="redeemKeyValue" style="font-size:1.3rem;background:#222;color:#fff;padding:0.7rem 1.2rem;border-radius:8px;display:inline-block;"></span>
                <button onclick="copyRedeemKey()" style="margin-left:1rem;padding:0.5rem 1.2rem;border-radius:8px;background:#59B8A0;color:#fff;border:none;cursor:pointer;">Copy</button>
            </div>
            <button class="close-btn" onclick="closeRedeemKeyModal()">Close</button>
        </div>
    </div>

    <script>
        // Test if JavaScript is loaded
        console.log('JavaScript loaded');

        // Modal functionality
        let currentReward = null;
        const userPoints = <?php echo $points; ?>;

        // Test click handler
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded');
            const cards = document.querySelectorAll('.reward-card');
            console.log('Found cards:', cards.length);
            
            cards.forEach(card => {
                card.addEventListener('click', function() {
                    console.log('Card clicked');
                    const reward = JSON.parse(this.getAttribute('data-reward'));
                    const imageSrc = this.getAttribute('data-image');
                    openRewardModal(reward, imageSrc);
                });
            });
        });

        function openRewardModal(reward, imageSrc) {
            console.log('Opening modal with reward:', reward);
            console.log('Image source:', imageSrc);
            
            currentReward = reward;
            const modal = document.getElementById('rewardModal');
            console.log('Modal element:', modal);
            
            const modalImage = document.getElementById('modalImage');
            const modalTitle = document.getElementById('modalTitle');
            const modalDescription = document.getElementById('modalDescription');
            const modalPoints = document.getElementById('modalPoints');
            const redeemButton = document.getElementById('redeemButton');
            const insufficientPoints = document.getElementById('insufficientPoints');

            modalImage.src = imageSrc;
            modalTitle.textContent = reward.reward_name;
            modalDescription.textContent = reward.description;
            modalPoints.textContent = reward.points_needed;

            // Points section
            document.getElementById('userCurrentPoints').textContent = userPoints;
            document.getElementById('rewardPointsNeeded').textContent = reward.points_needed;
            document.getElementById('userRemainingPoints').textContent = userPoints - reward.points_needed;

            // Check if user has enough points
            if (userPoints < reward.points_needed) {
                redeemButton.disabled = true;
                insufficientPoints.style.display = 'block';
            } else {
                redeemButton.disabled = false;
                insufficientPoints.style.display = 'none';
            }

            modal.style.display = 'block';
            setTimeout(() => {
                modal.classList.add('show');
            }, 10);
        }

        function closeModal() {
            console.log('Closing modal');
            const modal = document.getElementById('rewardModal');
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
            currentReward = null;
        }

        function showRewardPillPopup(message, isError = false) {
            const popup = document.getElementById('rewardPillPopup');
            popup.textContent = message;
            popup.classList.remove('error', 'success', 'show');
            popup.style.display = 'block';
            if (isError) {
                popup.classList.add('error');
            } else {
                popup.classList.add('success');
            }
            setTimeout(() => {
                popup.classList.add('show');
            }, 10);
            setTimeout(() => {
                popup.classList.remove('show');
                setTimeout(() => { popup.style.display = 'none'; window.location.reload(); }, 500);
            }, 2500);
        }

        function redeemReward() {
            if (!currentReward) return;
            const redeemButton = document.getElementById('redeemButton');
            redeemButton.disabled = true;
            redeemButton.textContent = 'Processing...';

            fetch('redeem_reward.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'reward_id=' + encodeURIComponent(currentReward.reward_id)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    redeemButton.textContent = 'Redeemed!';
                    redeemButton.style.background = '#4a9c88';
                    showRewardPillPopup('Redeemed successfully!');
                } else {
                    redeemButton.disabled = false;
                    redeemButton.textContent = 'Redeem Reward';
                    showRewardPillPopup('Redemption failed: ' + (data.error || 'Unknown error'), true);
                }
            })
            .catch(err => {
                redeemButton.disabled = false;
                redeemButton.textContent = 'Redeem Reward';
                showRewardPillPopup('Redemption failed: Network or server error', true);
            });
        }

        // Search functionality
        const searchInput = document.getElementById('searchInput');
        const rewardsGrid = document.querySelector('.rewards-grid');

        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rewardCards = rewardsGrid.getElementsByClassName('reward-card');

            Array.from(rewardCards).forEach(card => {
                const name = card.querySelector('.reward-name').textContent.toLowerCase();
                const description = card.querySelector('.reward-description').textContent.toLowerCase();
                
                if (name.includes(searchTerm) || description.includes(searchTerm)) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        });

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('rewardModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Create animated star background
        function createStars() {
            const container = document.querySelector('.star-background');
            const starCount = 100;

            for (let i = 0; i < starCount; i++) {
                const star = document.createElement('div');
                star.className = 'star';
                
                // Random position
                star.style.left = `${Math.random() * 100}%`;
                star.style.top = `${Math.random() * 100}%`;
                
                // Random size
                const size = Math.random() * 3 + 1;
                star.style.width = `${size}px`;
                star.style.height = `${size}px`;
                
                // Random twinkle duration
                star.style.setProperty('--twinkle-duration', `${Math.random() * 3 + 2}s`);
                
                container.appendChild(star);
            }
        }

        // Initialize stars on load
        document.addEventListener('DOMContentLoaded', createStars);

        document.getElementById('inventoryIconBtn').onclick = function() {
            document.getElementById('inventorySidebar').classList.add('open');
        };
        function closeInventorySidebar() {
            document.getElementById('inventorySidebar').classList.remove('open');
        }
        // Update sidebar close logic to not close if modal is open
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('inventorySidebar');
            const iconBtn = document.getElementById('inventoryIconBtn');
            const redeemModal = document.getElementById('redeemKeyModal');
            if (
                sidebar.classList.contains('open') &&
                !sidebar.contains(e.target) &&
                !iconBtn.contains(e.target) &&
                !(redeemModal && redeemModal.classList.contains('show'))
            ) {
                sidebar.classList.remove('open');
            }
        });

        // Handle redemption row click
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.redemption-row').forEach(function(row) {
                row.addEventListener('click', function() {
                    const redeemkeyId = this.getAttribute('data-redeemkey-id');
                    const rewardName = this.getAttribute('data-reward-name');
                    fetch('get_redeem_key.php?id=' + encodeURIComponent(redeemkeyId))
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                openRedeemKeyModal(rewardName + ' Redeem Key', data.redeem_key);
                            } else {
                                openRedeemKeyModal('Error', data.error || 'Could not fetch key.');
                            }
                        });
                });
            });
        });
        function openRedeemKeyModal(title, key) {
            document.getElementById('redeemKeyModalTitle').textContent = title;
            document.getElementById('redeemKeyValue').textContent = key;
            document.getElementById('redeemKeyModal').classList.add('show');
        }
        function closeRedeemKeyModal() {
            document.getElementById('redeemKeyModal').classList.remove('show');
        }
        function copyRedeemKey() {
            const key = document.getElementById('redeemKeyValue').textContent;
            const btn = event.target;
            navigator.clipboard.writeText(key).then(() => {
                const original = btn.textContent;
                btn.textContent = 'Copied';
                btn.classList.add('copied');
                setTimeout(() => {
                    btn.textContent = original;
                    btn.classList.remove('copied');
                }, 1200);
            });
        }

        // Prevent sidebar from closing when clicking inside the redeem key modal content
        document.addEventListener('DOMContentLoaded', function() {
            var modalContent = document.querySelector('.redeemkey-modal-content');
            if (modalContent) {
                modalContent.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            }
        });

        // Sidebar search functionality
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarSearchInput = document.getElementById('sidebarSearchInput');
            if (sidebarSearchInput) {
                sidebarSearchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    document.querySelectorAll('.redemption-row').forEach(function(row) {
                        const name = row.getAttribute('data-reward-name').toLowerCase();
                        const desc = row.getAttribute('data-description').toLowerCase();
                        const date = row.getAttribute('data-date').toLowerCase();
                        if (name.includes(searchTerm) || desc.includes(searchTerm) || date.includes(searchTerm)) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            }
        });
    </script>
</body>
</html> 