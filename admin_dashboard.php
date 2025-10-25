<?php 
session_start();

// ✅ Ensure user is logged in and has correct role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'config.php';

try {
    $client = new MongoDB\Client($mongoUri);
    $db = $client->elodge_db;

    // Fetch statistics
    $totalUsers = $db->users->countDocuments();
    $totalBookings = $db->bookings->countDocuments();
    $totalRooms = $db->rooms->countDocuments();
    $totalParkingSpaces = $db->parking_spaces->countDocuments();
    $activeBookings = $db->bookings->countDocuments(['status' => 'active']);

    // Fetch recent bookings
    $recentBookings = $db->bookings->find([], [
        'limit' => 5,
        'sort' => ['created_at' => -1]
    ]);
} catch (Exception $e) {
    error_log("Dashboard Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard - Adine Hotel</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
:root {
    --urban-espresso: #5B4A3E;
    --pavement-shadow: #8A8077;
    --luxe-oat: #CBBFAF;
    --ivory-silk: #E8DED4;
    --sunlit-veil: #F6F2EB;
    --text-dark: #3C2F28;
}

body {
    background-color: var(--sunlit-veil);
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    overflow-x: hidden;
}

/* Navbar */
.navbar {
    background-color: var(--urban-espresso);
}
.navbar-brand, .navbar-nav .nav-link, .navbar-text {
    color: #fff !important;
}
.navbar-toggler {
    border: none;
}
.navbar-toggler:focus {
    box-shadow: none;
}

/* Offcanvas Sidebar */
.offcanvas {
    background-color: var(--urban-espresso);
    color: white;
}
.offcanvas .nav-link {
    color: #E8DED4;
    font-weight: 500;
    padding: 10px 15px;
    border-radius: 6px;
}
.offcanvas .nav-link.active,
.offcanvas .nav-link:hover {
    background-color: var(--pavement-shadow);
    color: #fff;
}

/* Main Content */
.main-content {
    padding: 30px;
}

/* Cards */
.stat-card {
    background-color: var(--ivory-silk);
    border: 1px solid var(--luxe-oat);
    border-radius: 12px;
    padding: 25px;
    text-align: center;
    box-shadow: 0 3px 8px rgba(0,0,0,0.05);
    transition: transform 0.2s ease;
}
.stat-card:hover {
    transform: translateY(-4px);
}
.stat-label {
    font-size: 0.9rem;
    text-transform: uppercase;
    color: var(--pavement-shadow);
}
.stat-number {
    font-size: 2rem;
    font-weight: 700;
    color: var(--urban-espresso);
}

/* Section Titles */
.section-title {
    font-size: 1.4em;
    font-weight: 700;
    color: var(--text-dark);
    margin: 30px 0 20px;
}

/* Recent Activity */
.recent-activity {
    background: var(--ivory-silk);
    padding: 25px;
    border-radius: 12px;
    border: 1px solid var(--luxe-oat);
    box-shadow: 0 3px 8px rgba(0,0,0,0.05);
}
.activity-item {
    padding: 15px 0;
    border-bottom: 1px solid rgba(91,74,62,0.15);
}
.activity-item:last-child {
    border-bottom: none;
}
.activity-info h4 {
    color: var(--urban-espresso);
    font-weight: 600;
}
.activity-info p {
    color: var(--pavement-shadow);
    font-size: 0.9em;
}
.activity-date {
    color: var(--pavement-shadow);
    font-size: 0.85em;
}

/* User Profile Badge */
.user-profile {
    display: flex;
    align-items: center;
    background: var(--luxe-oat);
    border-radius: 30px;
    padding: 5px 15px;
    gap: 10px;
}
.user-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: var(--urban-espresso);
    color: #fff;
    display: flex;
    justify-content: center;
    align-items: center;
    font-weight: 700;
}
.logout-btn {
    background: transparent;
    color: var(--ivory-silk);
    border: 1px solid var(--luxe-oat);
    border-radius: 20px;
    padding: 6px 16px;
    transition: all 0.3s;
}
.logout-btn:hover {
    background: var(--luxe-oat);
    color: var(--urban-espresso);
}
</style>
</head>

<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark sticky-top">
  <div class="container-fluid">
    <button class="btn btn-outline-light me-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar">☰</button>
    <a class="navbar-brand fw-bold" href="#">Adine Hotel Admin</a>
    <div class="ms-auto user-profile">
        <?php 
        $username = $_SESSION['username'] ?? 'Admin';
        ?>
        <div class="user-avatar"><?= strtoupper(substr($username, 0, 2)); ?></div>
        <div class="text-dark fw-semibold"><?= htmlspecialchars($username); ?></div>
        <a href="logout.php" class="btn logout-btn ms-2">Logout</a>
    </div>
  </div>
</nav>

<!-- Sidebar -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="sidebar">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title fw-bold">Navigation</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body">
    <nav class="nav flex-column">
      <a href="admin_dashboard.php" class="nav-link active">📊 Dashboard</a>
      <a href="manage_users.php" class="nav-link">👥 User Management</a>
      <a href="add_update_rooms.php" class="nav-link">🏨 Rooms & Parking</a>
      <a href="view_all_bookings.php" class="nav-link">📅 Bookings</a>
      <a href="generate_reports.php" class="nav-link">📈 Reports</a>
      <a href="dashboard_analytics.php" class="nav-link">📊 Analytics</a>
      <a href="system_settings.php" class="nav-link">⚙️ Settings</a>
    </nav>
  </div>
</div>

<!-- Main Content -->
<div class="main-content container-fluid">
  <div class="section-title">System Overview</div>

  <div class="row g-3">
    <div class="col-md-4 col-lg-3">
      <div class="stat-card">
        <div class="stat-label">Total Users</div>
        <div class="stat-number"><?= $totalUsers ?? 0; ?></div>
      </div>
    </div>
    <div class="col-md-4 col-lg-3">
      <div class="stat-card">
        <div class="stat-label">Total Bookings</div>
        <div class="stat-number"><?= $totalBookings ?? 0; ?></div>
      </div>
    </div>
    <div class="col-md-4 col-lg-3">
      <div class="stat-card">
        <div class="stat-label">Active Bookings</div>
        <div class="stat-number"><?= $activeBookings ?? 0; ?></div>
      </div>
    </div>
    <div class="col-md-4 col-lg-3">
      <div class="stat-card">
        <div class="stat-label">Total Rooms</div>
        <div class="stat-number"><?= $totalRooms ?? 0; ?></div>
      </div>
    </div>
  </div>

  <div class="section-title">Recent Activity</div>
  <div class="recent-activity">
    <?php 
    $hasBookings = false;
    foreach ($recentBookings as $booking):
        $hasBookings = true; ?>
        <div class="activity-item">
            <div class="activity-info">
                <h4>Booking #<?= substr((string)$booking['_id'], -8); ?></h4>
                <p><?= htmlspecialchars($booking['guest_name'] ?? 'Guest'); ?> - <?= htmlspecialchars($booking['room_type'] ?? 'Room'); ?></p>
            </div>
            <div class="activity-date">
                <?= isset($booking['created_at']) ? $booking['created_at']->toDateTime()->format('M d, Y') : ''; ?>
            </div>
        </div>
    <?php endforeach; 
    if (!$hasBookings): ?>
        <p>No recent bookings found.</p>
    <?php endif; ?>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
