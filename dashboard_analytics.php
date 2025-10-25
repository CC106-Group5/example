<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'config.php';

try {
    $client = new MongoDB\Client($mongoUri);
    $db = $client->elodge_db;
    
    // Get analytics data
    $totalRevenue = 0;
    $monthlyRevenue = 0;
    $todayBookings = 0;
    
    // Calculate total revenue
    $allBookings = $db->bookings->find();
    foreach ($allBookings as $booking) {
        $totalRevenue += $booking['total_price'] ?? 0;
    }
    
    // Calculate monthly revenue
    $startOfMonth = new MongoDB\BSON\UTCDateTime(strtotime(date('Y-m-01')) * 1000);
    $monthlyBookings = $db->bookings->find(['created_at' => ['$gte' => $startOfMonth]]);
    foreach ($monthlyBookings as $booking) {
        $monthlyRevenue += $booking['total_price'] ?? 0;
    }
    
    // Today's bookings
    $startOfDay = new MongoDB\BSON\UTCDateTime(strtotime(date('Y-m-d 00:00:00')) * 1000);
    $todayBookings = $db->bookings->countDocuments(['created_at' => ['$gte' => $startOfDay]]);
    
    // Room statistics
    $totalRooms = $db->rooms->countDocuments();
    $occupiedRooms = $db->rooms->countDocuments(['status' => 'occupied']);
    $availableRooms = $db->rooms->countDocuments(['status' => 'available']);
    $occupancyRate = $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100, 1) : 0;
    
} catch (Exception $e) {
    error_log("Analytics Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - E-LODGE</title>
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

        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }

        .analytics-card {
            background: #FFFFFF;
            padding: 35px;
            border-radius: 20px;
            box-shadow: 0 2px 12px rgba(86, 28, 36, 0.08);
            position: relative;
            overflow: hidden;
        }

        .analytics-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #561C24, #6D2932);
        }

        .analytics-label {
            color: #6D2932;
            font-size: 0.85em;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 15px;
        }

        .analytics-value {
            font-size: 2.5em;
            font-weight: 300;
            color: #561C24;
            margin-bottom: 10px;
        }

        .analytics-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #561C24, #6D2932);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5em;
            position: absolute;
            top: 30px;
            right: 30px;
        }

        .chart-card {
            background: #FFFFFF;
            padding: 35px;
            border-radius: 20px;
            box-shadow: 0 2px 12px rgba(86, 28, 36, 0.08);
            margin-bottom: 30px;
        }

        .chart-title {
            font-size: 1.3em;
            font-weight: 600;
            color: #561C24;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #C7B7A3;
        }

        .progress-bar-container {
            margin-bottom: 25px;
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            color: #561C24;
            font-weight: 600;
        }

        .progress-bar {
            width: 100%;
            height: 30px;
            background: #E8D8C4;
            border-radius: 15px;
            overflow: hidden;
            position: relative;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #561C24, #6D2932);
            transition: width 1s ease;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 15px;
            color: #E8D8C4;
            font-weight: 600;
            font-size: 0.9em;
        }

        .metric-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .metric-box {
            background: linear-gradient(135deg, #561C24, #6D2932);
            padding: 25px;
            border-radius: 15px;
            color: #E8D8C4;
        }

        .metric-label {
            font-size: 0.85em;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
            opacity: 0.9;
        }

        .metric-value {
            font-size: 2em;
            font-weight: 300;
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
                <a href="view_all_bookings.php" class="nav-item">
                    <span>📅</span><span>Bookings</span>
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Analytics</div>
                <a href="generate_reports.php" class="nav-item">
                    <span>📈</span><span>Reports</span>
                </a>
                <a href="dashboard_analytics.php" class="nav-item active">
                    <span>📉</span><span>Analytics</span>
                </a>
            </div>
        </nav>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <div class="page-title">Analytics Dashboard</div>
            <div class="user-section">
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>

        <div class="content">
            <div class="analytics-grid">
                <div class="analytics-card">
                    <div class="analytics-icon">💰</div>
                    <div class="analytics-label">Total Revenue</div>
                    <div class="analytics-value">₱<?php echo number_format($totalRevenue ?? 0, 2); ?></div>
                </div>

                <div class="analytics-card">
                    <div class="analytics-icon">📊</div>
                    <div class="analytics-label">Monthly Revenue</div>
                    <div class="analytics-value">₱<?php echo number_format($monthlyRevenue ?? 0, 2); ?></div>
                </div>

                <div class="analytics-card">
                    <div class="analytics-icon">📅</div>
                    <div class="analytics-label">Today's Bookings</div>
                    <div class="analytics-value"><?php echo $todayBookings ?? 0; ?></div>
                </div>

                <div class="analytics-card">
                    <div class="analytics-icon">📈</div>
                    <div class="analytics-label">Occupancy Rate</div>
                    <div class="analytics-value"><?php echo $occupancyRate ?? 0; ?>%</div>
                </div>
            </div>

            <div class="chart-card">
                <h2 class="chart-title">Room Occupancy Overview</h2>
                
                <div class="progress-bar-container">
                    <div class="progress-label">
                        <span>Occupied Rooms</span>
                        <span><?php echo $occupiedRooms ?? 0; ?> / <?php echo $totalRooms ?? 0; ?></span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $occupancyRate ?? 0; ?>%;">
                            <?php echo $occupancyRate ?? 0; ?>%
                        </div>
                    </div>
                </div>

                <div class="metric-grid">
                    <div class="metric-box">
                        <div class="metric-label">Total Rooms</div>
                        <div class="metric-value"><?php echo $totalRooms ?? 0; ?></div>
                    </div>

                    <div class="metric-box">
                        <div class="metric-label">Occupied Rooms</div>
                        <div class="metric-value"><?php echo $occupiedRooms ?? 0; ?></div>
                    </div>

                    <div class="metric-box">
                        <div class="metric-label">Available Rooms</div>
                        <div class="metric-value"><?php echo $availableRooms ?? 0; ?></div>
                    </div>

                    <div class="metric-box">
                        <div class="metric-label">Maintenance</div>
                        <div class="metric-value">0</div>
                    </div>
                </div>
            </div>

            <div class="chart-card">
                <h2 class="chart-title">Performance Metrics</h2>
                
                <div class="progress-bar-container">
                    <div class="progress-label">
                        <span>Average Daily Bookings</span>
                        <span><?php echo round($todayBookings ?? 0, 1); ?></span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo min(($todayBookings ?? 0) * 10, 100); ?>%;">
                            <?php echo $todayBookings ?? 0; ?>
                        </div>
                    </div>
                </div>

                <div class="progress-bar-container">
                    <div class="progress-label">
                        <span>Customer Satisfaction</span>
                        <span>98%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 98%;">
                            98%
                        </div>
                    </div>
                </div>

                <div class="progress-bar-container">
                    <div class="progress-label">
                        <span>Service Quality</span>
                        <span>95%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 95%;">
                            95%
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>