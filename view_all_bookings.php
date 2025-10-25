<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'config.php';

$message = '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    try {
        $client = new MongoDB\Client($mongoUri);
        $db = $client->elodge_db;
        
        $result = $db->bookings->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($_POST['booking_id'])],
            ['$set' => [
                'status' => $_POST['status'],
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ]]
        );
        
        if ($result->getModifiedCount() > 0) {
            $message = 'Booking status updated successfully';
        }
    } catch (Exception $e) {
        $message = 'Error updating booking';
    }
}

// Get all bookings
try {
    $client = new MongoDB\Client($mongoUri);
    $db = $client->elodge_db;
    
    $bookings = $db->bookings->find([], ['sort' => ['created_at' => -1]]);
} catch (Exception $e) {
    error_log("Error fetching bookings: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Bookings - E-LODGE</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #E8D8C4;
            color: #561C24;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            background: #561C24;
            z-index: 100;
        }

        .sidebar-header {
            padding: 40px 30px;
            border-bottom: 1px solid rgba(199, 183, 163, 0.1);
        }

        .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .logo-small {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            border: 3px solid #C7B7A3;
        }

        .brand-name {
            font-size: 1.1em;
            color: #E8D8C4;
            text-align: center;
            letter-spacing: 3px;
            text-transform: uppercase;
        }

        .nav-menu {
            padding: 30px 20px;
        }

        .nav-section {
            margin-bottom: 35px;
        }

        .nav-section-title {
            font-size: 0.65em;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #C7B7A3;
            padding: 0 15px;
            margin-bottom: 12px;
            opacity: 0.7;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 14px 15px;
            color: #C7B7A3;
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            margin-bottom: 6px;
        }

        .nav-item:hover {
            background: rgba(199, 183, 163, 0.1);
            color: #E8D8C4;
        }

        .nav-item.active {
            background: #6D2932;
            color: #E8D8C4;
        }

        .main-content {
            margin-left: 280px;
            min-height: 100vh;
        }

        .top-bar {
            background: #FFFFFF;
            padding: 25px 50px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 50;
            box-shadow: 0 2px 12px rgba(86, 28, 36, 0.05);
        }

        .page-title {
            font-size: 1.8em;
            font-weight: 300;
            color: #561C24;
        }

        .logout-btn {
            background: transparent;
            color: #561C24;
            padding: 10px 24px;
            border: 1.5px solid #C7B7A3;
            border-radius: 25px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: #561C24;
            color: #E8D8C4;
        }

        .content {
            padding: 50px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            background: #6D2932;
            color: #E8D8C4;
        }

        .table-container {
            background: #FFFFFF;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 2px 12px rgba(86, 28, 36, 0.08);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }

        th {
            background: #561C24;
            color: #E8D8C4;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85em;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #E8D8C4;
            color: #561C24;
        }

        tr:hover {
            background: #E8D8C4;
        }

        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-active {
            background: #6D2932;
            color: #E8D8C4;
        }

        .badge-completed {
            background: #C7B7A3;
            color: #561C24;
        }

        .badge-cancelled {
            background: #561C24;
            color: #E8D8C4;
        }

        select {
            padding: 8px 12px;
            border: 2px solid #C7B7A3;
            border-radius: 8px;
            background: #FFFFFF;
            color: #561C24;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo-container">
                <img src="logos.png" alt="Logo" class="logo-small">
            </div>
            <div class="brand-name">Adine Hotel</div>
        </div>

        <nav class="nav-menu">
            <div class="nav-section">
                <div class="nav-section-title">Main</div>
                <a href="admin_dashboard.php" class="nav-item">
                    <span>📊</span><span>Dashboard</span>
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Management</div>
                <a href="manage_users.php" class="nav-item">
                    <span>👥</span><span>Users</span>
                </a>
                <a href="add_update_rooms.php" class="nav-item">
                    <span>🏨</span><span>Rooms & Parking</span>
                </a>
                <a href="view_all_bookings.php" class="nav-item active">
                    <span>📅</span><span>Bookings</span>
                </a>
            </div>
        </nav>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <div class="page-title">All Bookings</div>
            <div class="user-section">
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>

        <div class="content">
            <?php if ($message): ?>
                <div class="alert"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Guest Name</th>
                            <th>Room</th>
                            <th>Check-in</th>
                            <th>Check-out</th>
                            <th>Total Price</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (isset($bookings)): ?>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td>#<?php echo substr((string)$booking['_id'], -8); ?></td>
                                    <td><?php echo htmlspecialchars($booking['guest_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($booking['room_type'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php 
                                        if (isset($booking['check_in'])) {
                                            echo $booking['check_in']->toDateTime()->format('M d, Y');
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if (isset($booking['check_out'])) {
                                            echo $booking['check_out']->toDateTime()->format('M d, Y');
                                        }
                                        ?>
                                    </td>
                                    <td>₱<?php echo number_format($booking['total_price'] ?? 0, 2); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $booking['status'] ?? 'active'; ?>">
                                            <?php echo htmlspecialchars($booking['status'] ?? 'active'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="booking_id" value="<?php echo (string)$booking['_id']; ?>">
                                            <select name="status" onchange="this.form.submit()">
                                                <option value="active" <?php echo ($booking['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                                                <option value="completed" <?php echo ($booking['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                <option value="cancelled" <?php echo ($booking['status'] ?? '') === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                            <input type="hidden" name="update_status" value="1">
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center;">No bookings found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>