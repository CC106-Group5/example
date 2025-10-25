<?php 
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'config.php';

if (!isset($_SESSION['username'])) {
    try {
        $tempClient = new MongoDB\Client($mongoUri);
        $tempDb = $tempClient->elodge_db;
        $currentUser = $tempDb->users->findOne(['_id' => new MongoDB\BSON\ObjectId($_SESSION['user_id'])]);
        if ($currentUser) {
            $_SESSION['username'] = $currentUser['username'] ?? $currentUser['email'] ?? 'Admin';
        } else {
            $_SESSION['username'] = 'Admin';
        }
    } catch (Exception $e) {
        $_SESSION['username'] = 'Admin';
    }
}
$message = '';
$messageType = '';

try {
    $client = new MongoDB\Client($mongoUri);
    $db = $client->elodge_db;
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_room':
                    $roomData = [
                        'room_number' => $_POST['room_number'],
                        'room_type' => $_POST['room_type'],
                        'floor' => intval($_POST['floor']),
                        'capacity' => intval($_POST['capacity']),
                        'price_per_night' => floatval($_POST['price_per_night']),
                        'status' => $_POST['status'],
                        'amenities' => isset($_POST['amenities']) ? $_POST['amenities'] : [],
                        'description' => $_POST['description'],
                        'created_at' => new MongoDB\BSON\UTCDateTime(),
                        'updated_at' => new MongoDB\BSON\UTCDateTime()
                    ];
                    
                    $db->rooms->insertOne($roomData);
                    $message = 'Room added successfully!';
                    $messageType = 'success';
                    break;
                    
                case 'update_room':
                    $roomData = [
                        'room_number' => $_POST['room_number'],
                        'room_type' => $_POST['room_type'],
                        'floor' => intval($_POST['floor']),
                        'capacity' => intval($_POST['capacity']),
                        'price_per_night' => floatval($_POST['price_per_night']),
                        'status' => $_POST['status'],
                        'amenities' => isset($_POST['amenities']) ? $_POST['amenities'] : [],
                        'description' => $_POST['description'],
                        'updated_at' => new MongoDB\BSON\UTCDateTime()
                    ];
                    
                    $db->rooms->updateOne(
                        ['_id' => new MongoDB\BSON\ObjectId($_POST['room_id'])],
                        ['$set' => $roomData]
                    );
                    $message = 'Room updated successfully!';
                    $messageType = 'success';
                    break;
                    
                case 'delete_room':
                    // Check if room has any active bookings
                    $activeBooking = $db->bookings->findOne([
                        'room_id' => $_POST['room_id'],
                        'status' => ['$in' => ['pending', 'confirmed']]
                    ]);
                    
                    if ($activeBooking) {
                        $message = 'Cannot delete room with active bookings!';
                        $messageType = 'danger';
                    } else {
                        $db->rooms->deleteOne(['_id' => new MongoDB\BSON\ObjectId($_POST['room_id'])]);
                        $message = 'Room deleted successfully!';
                        $messageType = 'success';
                    }
                    break;
                    
                case 'add_parking':
                    $parkingData = [
                        'space_number' => $_POST['space_number'],
                        'parking_type' => $_POST['parking_type'],
                        'floor' => intval($_POST['parking_floor']),
                        'status' => $_POST['parking_status'],
                        'price_per_day' => floatval($_POST['parking_price']),
                        'vehicle_type' => $_POST['vehicle_type'],
                        'created_at' => new MongoDB\BSON\UTCDateTime(),
                        'updated_at' => new MongoDB\BSON\UTCDateTime()
                    ];
                    
                    $db->parking_spaces->insertOne($parkingData);
                    $message = 'Parking space added successfully!';
                    $messageType = 'success';
                    break;
                    
                case 'update_parking':
                    $parkingData = [
                        'space_number' => $_POST['space_number'],
                        'parking_type' => $_POST['parking_type'],
                        'floor' => intval($_POST['parking_floor']),
                        'status' => $_POST['parking_status'],
                        'price_per_day' => floatval($_POST['parking_price']),
                        'vehicle_type' => $_POST['vehicle_type'],
                        'updated_at' => new MongoDB\BSON\UTCDateTime()
                    ];
                    
                    $db->parking_spaces->updateOne(
                        ['_id' => new MongoDB\BSON\ObjectId($_POST['parking_id'])],
                        ['$set' => $parkingData]
                    );
                    $message = 'Parking space updated successfully!';
                    $messageType = 'success';
                    break;
                    
                case 'delete_parking':
                    // Check if parking has any active assignments
                    $activeAssignment = $db->bookings->findOne([
                        'parking_id' => $_POST['parking_id'],
                        'status' => ['$in' => ['pending', 'confirmed']]
                    ]);
                    
                    if ($activeAssignment) {
                        $message = 'Cannot delete parking space with active assignments!';
                        $messageType = 'danger';
                    } else {
                        $db->parking_spaces->deleteOne(['_id' => new MongoDB\BSON\ObjectId($_POST['parking_id'])]);
                        $message = 'Parking space deleted successfully!';
                        $messageType = 'success';
                    }
                    break;
            }
        }
    }
    
    // Fetch all rooms
    $rooms = $db->rooms->find([], ['sort' => ['room_number' => 1]]);
    
    // Fetch all parking spaces
    $parkingSpaces = $db->parking_spaces->find([], ['sort' => ['space_number' => 1]]);
    
    // Get statistics
    $totalRooms = $db->rooms->countDocuments();
    $availableRooms = $db->rooms->countDocuments(['status' => 'available']);
    $occupiedRooms = $db->rooms->countDocuments(['status' => 'occupied']);
    $maintenanceRooms = $db->rooms->countDocuments(['status' => 'maintenance']);
    
    $totalParking = $db->parking_spaces->countDocuments();
    $availableParking = $db->parking_spaces->countDocuments(['status' => 'available']);
    $occupiedParking = $db->parking_spaces->countDocuments(['status' => 'occupied']);
    
} catch (Exception $e) {
    $message = 'Error: ' . $e->getMessage();
    $messageType = 'danger';
    error_log("Rooms & Parking Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rooms & Parking Management - Adine Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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
            transition: all 0.3s ease;
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

        /* Page Header */
        .page-header {
            margin-bottom: 30px;
        }
        .page-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 5px;
        }
        .page-subtitle {
            color: var(--pavement-shadow);
            font-size: 0.95rem;
        }

        /* Stats Cards */
        .stat-card {
            background-color: var(--ivory-silk);
            border: 1px solid var(--luxe-oat);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 3px 8px rgba(0,0,0,0.05);
            transition: transform 0.2s ease;
            height: 100%;
        }
        .stat-card:hover {
            transform: translateY(-4px);
        }
        .stat-label {
            font-size: 0.85rem;
            text-transform: uppercase;
            color: var(--pavement-shadow);
            margin-bottom: 8px;
            font-weight: 600;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--urban-espresso);
        }

        /* Tabs */
        .nav-tabs {
            border-bottom: 2px solid var(--luxe-oat);
            margin-bottom: 25px;
        }
        .nav-tabs .nav-link {
            color: var(--pavement-shadow);
            border: none;
            padding: 12px 24px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .nav-tabs .nav-link:hover {
            color: var(--urban-espresso);
            background-color: rgba(203, 191, 175, 0.2);
        }
        .nav-tabs .nav-link.active {
            color: var(--urban-espresso);
            background-color: transparent;
            border-bottom: 3px solid var(--urban-espresso);
        }

        /* Action Bar */
        .action-bar {
            background-color: var(--ivory-silk);
            border: 1px solid var(--luxe-oat);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        /* Buttons */
        .btn-primary {
            background-color: var(--urban-espresso);
            border-color: var(--urban-espresso);
            color: white;
            font-weight: 600;
            padding: 10px 24px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background-color: var(--pavement-shadow);
            border-color: var(--pavement-shadow);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(91, 74, 62, 0.3);
        }

        .btn-outline-secondary {
            color: var(--urban-espresso);
            border-color: var(--luxe-oat);
            font-weight: 600;
        }
        .btn-outline-secondary:hover {
            background-color: var(--luxe-oat);
            border-color: var(--luxe-oat);
            color: var(--urban-espresso);
        }

        /* Search Box */
        .search-box {
            border: 2px solid var(--luxe-oat);
            border-radius: 8px;
            padding: 10px 15px;
            width: 300px;
            transition: all 0.3s ease;
        }
        .search-box:focus {
            outline: none;
            border-color: var(--urban-espresso);
            box-shadow: 0 0 0 3px rgba(91, 74, 62, 0.1);
        }

        /* Table Container */
        .table-container {
            background-color: var(--ivory-silk);
            border: 1px solid var(--luxe-oat);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 3px 8px rgba(0,0,0,0.05);
        }

        .table {
            margin-bottom: 0;
        }
        .table thead {
            background-color: var(--urban-espresso);
            color: white;
        }
        .table thead th {
            border: none;
            padding: 15px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }
        .table tbody tr {
            border-bottom: 1px solid rgba(203, 191, 175, 0.3);
            transition: background-color 0.2s ease;
        }
        .table tbody tr:hover {
            background-color: rgba(203, 191, 175, 0.15);
        }
        .table tbody td {
            padding: 15px;
            vertical-align: middle;
            color: var(--text-dark);
        }

        /* Status Badges */
        .badge {
            padding: 6px 12px;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-available {
            background-color: #d4edda;
            color: #155724;
        }
        .badge-occupied {
            background-color: #f8d7da;
            color: #721c24;
        }
        .badge-maintenance {
            background-color: #fff3cd;
            color: #856404;
        }

        /* Action Buttons */
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85rem;
            border-radius: 6px;
        }
        .btn-edit {
            background-color: rgba(91, 74, 62, 0.1);
            color: var(--urban-espresso);
            border: 1px solid var(--luxe-oat);
        }
        .btn-edit:hover {
            background-color: var(--urban-espresso);
            color: white;
        }
        .btn-delete {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }
        .btn-delete:hover {
            background-color: #dc3545;
            color: white;
        }

        /* Modal Styling */
        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        }
        .modal-header {
            background-color: var(--urban-espresso);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 20px 25px;
        }
        .modal-title {
            font-weight: 700;
        }
        .modal-body {
            padding: 25px;
            background-color: var(--sunlit-veil);
        }
        .modal-footer {
            border-top: 1px solid var(--luxe-oat);
            padding: 20px 25px;
            background-color: var(--sunlit-veil);
            border-radius: 0 0 15px 15px;
        }

        /* Form Elements */
        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 8px;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-control, .form-select {
            border: 2px solid var(--luxe-oat);
            border-radius: 8px;
            padding: 10px 15px;
            transition: all 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--urban-espresso);
            box-shadow: 0 0 0 3px rgba(91, 74, 62, 0.1);
        }

        /* Checkbox Group */
        .checkbox-group {
            background-color: var(--ivory-silk);
            border: 1px solid var(--luxe-oat);
            border-radius: 8px;
            padding: 15px;
        }
        .form-check-label {
            color: var(--text-dark);
            font-weight: 500;
            margin-left: 8px;
        }
        .form-check-input:checked {
            background-color: var(--urban-espresso);
            border-color: var(--urban-espresso);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--pavement-shadow);
        }
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        .empty-state h5 {
            font-weight: 600;
            color: var(--text-dark);
        }

        /* User Profile */
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

        /* Responsive */
        @media (max-width: 768px) {
            .action-bar {
                flex-direction: column;
                align-items: stretch;
            }
            .search-box {
                width: 100%;
            }
            .table-container {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark sticky-top">
    <div class="container-fluid">
        <button class="btn btn-outline-light me-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar">
            <i class="bi bi-list"></i>
        </button>
        <a class="navbar-brand fw-bold" href="#">Adine Hotel Admin</a>
        <div class="ms-auto user-profile">
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION['username'], 0, 2)); ?>
            </div>
            <div class="text-dark fw-semibold">
                <?php echo htmlspecialchars($_SESSION['username']); ?>
            </div>
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
            <a href="admin_dashboard.php" class="nav-link">📊 Dashboard</a>
            <a href="manage_users.php" class="nav-link">👥 User Management</a>
            <a href="add_update_rooms.php" class="nav-link active">🏨 Rooms & Parking</a>
            <a href="view_all_bookings.php" class="nav-link">📅 Bookings</a>
            <a href="generate_reports.php" class="nav-link">📈 Reports</a>
            <a href="dashboard_analytics.php" class="nav-link">📊 Analytics</a>
            <a href="system_settings.php" class="nav-link">⚙️ Settings</a>
        </nav>
    </div>
</div>

<!-- Main Content -->
<div class="main-content container-fluid">
    <!-- Page Header -->
    <div class="page-header">
        <h1 class="page-title">Rooms & Parking Management</h1>
        <p class="page-subtitle">Manage your hotel rooms and parking spaces</p>
    </div>

    <!-- Alert Messages -->
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>-fill me-2"></i>
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Statistics Row -->
    <div class="row g-3 mb-4">
        <div class="col-md-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-label">Total Rooms</div>
                <div class="stat-number"><?php echo $totalRooms; ?></div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-label">Available Rooms</div>
                <div class="stat-number text-success"><?php echo $availableRooms; ?></div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-label">Occupied Rooms</div>
                <div class="stat-number text-danger"><?php echo $occupiedRooms; ?></div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-label">Total Parking</div>
                <div class="stat-number"><?php echo $totalParking; ?></div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#rooms-tab" type="button">
                <i class="bi bi-door-open me-2"></i>Rooms
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#parking-tab" type="button">
                <i class="bi bi-car-front me-2"></i>Parking Spaces
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- Rooms Tab -->
        <div class="tab-pane fade show active" id="rooms-tab">
            <!-- Action Bar -->
            <div class="action-bar">
                <input type="text" class="search-box" id="roomSearch" placeholder="🔍 Search rooms..." onkeyup="searchRooms()">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#roomModal" onclick="openAddRoomModal()">
                    <i class="bi bi-plus-circle me-2"></i>Add New Room
                </button>
            </div>

            <!-- Rooms Table -->
            <div class="table-container">
                <?php if ($totalRooms > 0): ?>
                <div class="table-responsive">
                    <table class="table" id="roomsTable">
                        <thead>
                            <tr>
                                <th>Room Number</th>
                                <th>Type</th>
                                <th>Floor</th>
                                <th>Capacity</th>
                                <th>Price/Night</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rooms as $room): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($room['room_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($room['room_type']); ?></td>
                                    <td><?php echo $room['floor']; ?></td>
                                    <td><i class="bi bi-people-fill me-1"></i><?php echo $room['capacity']; ?></td>
                                    <td><strong>₱<?php echo number_format($room['price_per_night'], 2); ?></strong></td>
                                    <td>
                                        <span class="badge badge-<?php echo $room['status']; ?>">
                                            <?php echo ucfirst($room['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-edit me-1" onclick='editRoom(<?php echo json_encode($room); ?>)' data-bs-toggle="modal" data-bs-target="#roomModal">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </button>
                                        <button class="btn btn-sm btn-delete" onclick="confirmDeleteRoom('<?php echo $room['_id']; ?>', '<?php echo htmlspecialchars($room['room_number']); ?>')">
                                            <i class="bi bi-trash3"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-door-open"></i>
                    <h5>No rooms available</h5>
                    <p>Click "Add New Room" to get started.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Parking Tab -->
        <div class="tab-pane fade" id="parking-tab">
            <!-- Action Bar -->
            <div class="action-bar">
                <input type="text" class="search-box" id="parkingSearch" placeholder="🔍 Search parking spaces..." onkeyup="searchParking()">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#parkingModal" onclick="openAddParkingModal()">
                    <i class="bi bi-plus-circle me-2"></i>Add New Parking Space
                </button>
            </div>

            <!-- Parking Table -->
            <div class="table-container">
                <?php if ($totalParking > 0): ?>
                <div class="table-responsive">
                    <table class="table" id="parkingTable">
                        <thead>
                            <tr>
                                <th>Space Number</th>
                                <th>Type</th>
                                <th>Floor</th>
                                <th>Vehicle Type</th>
                                <th>Price/Day</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($parkingSpaces as $parking): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($parking['space_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($parking['parking_type']); ?></td>
                                    <td><?php echo $parking['floor']; ?></td>
                                    <td><i class="bi bi-car-front me-1"></i><?php echo ucfirst($parking['vehicle_type']); ?></td>
                                    <td><strong>₱<?php echo number_format($parking['price_per_day'], 2); ?></strong></td>
                                    <td>
                                        <span class="badge badge-<?php echo $parking['status']; ?>">
                                            <?php echo ucfirst($parking['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-edit me-1" onclick='editParking(<?php echo json_encode($parking); ?>)' data-bs-toggle="modal" data-bs-target="#parkingModal">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </button>
                                        <button class="btn btn-sm btn-delete" onclick="confirmDeleteParking('<?php echo $parking['_id']; ?>', '<?php echo htmlspecialchars($parking['space_number']); ?>')">
                                            <i class="bi bi-trash3"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-car-front"></i>
                    <h5>No parking spaces available</h5>
                    <p>Click "Add New Parking Space" to get started.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Room Modal -->
<div class="modal fade" id="roomModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="roomModalTitle">Add New Room</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="roomForm">
                <div class="modal-body">
                    <input type="hidden" name="action" id="roomAction" value="add_room">
                    <input type="hidden" name="room_id" id="roomId">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Room Number</label>
                            <input type="text" name="room_number" id="roomNumber" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Room Type</label>
                            <select name="room_type" id="roomType" class="form-select" required>
                                <option value="">Select Type</option>
                                <option value="Standard">Standard</option>
                                <option value="Deluxe">Deluxe</option>
                                <option value="Suite">Suite</option>
                                <option value="Presidential">Presidential</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mt-2">
                        <div class="col-md-6">
                            <label class="form-label">Floor</label>
                            <input type="number" name="floor" id="roomFloor" class="form-control" min="1" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Capacity</label>
                            <input type="number" name="capacity" id="roomCapacity" class="form-control" min="1" required>
                        </div>
                    </div>

                    <div class="row g-3 mt-2">
                        <div class="col-md-6">
                            <label class="form-label">Price per Night (₱)</label>
                            <input type="number" name="price_per_night" id="roomPrice" class="form-control" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" id="roomStatus" class="form-select" required>
                                <option value="available">Available</option>
                                <option value="occupied">Occupied</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label">Amenities</label>
                        <div class="checkbox-group">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="amenities[]" value="WiFi" id="amenity_wifi">
                                        <label class="form-check-label" for="amenity_wifi">WiFi</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="amenities[]" value="TV" id="amenity_tv">
                                        <label class="form-check-label" for="amenity_tv">TV</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="amenities[]" value="Air Conditioning" id="amenity_ac">
                                        <label class="form-check-label" for="amenity_ac">Air Conditioning</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="amenities[]" value="Mini Bar" id="amenity_minibar">
                                        <label class="form-check-label" for="amenity_minibar">Mini Bar</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="amenities[]" value="Safe" id="amenity_safe">
                                        <label class="form-check-label" for="amenity_safe">Safe</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="amenities[]" value="Balcony" id="amenity_balcony">
                                        <label class="form-check-label" for="amenity_balcony">Balcony</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="roomDescription" class="form-control" rows="3" placeholder="Enter room description..."></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i>Save Room
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add/Edit Parking Modal -->
<div class="modal fade" id="parkingModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="parkingModalTitle">Add New Parking Space</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="parkingForm">
                <div class="modal-body">
                    <input type="hidden" name="action" id="parkingAction" value="add_parking">
                    <input type="hidden" name="parking_id" id="parkingId">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Space Number</label>
                            <input type="text" name="space_number" id="spaceNumber" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Parking Type</label>
                            <select name="parking_type" id="parkingType" class="form-select" required>
                                <option value="">Select Type</option>
                                <option value="Indoor">Indoor</option>
                                <option value="Outdoor">Outdoor</option>
                                <option value="Covered">Covered</option>
                                <option value="Open">Open</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mt-2">
                        <div class="col-md-6">
                            <label class="form-label">Floor</label>
                            <input type="number" name="parking_floor" id="parkingFloor" class="form-control" min="-3" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Vehicle Type</label>
                            <select name="vehicle_type" id="vehicleType" class="form-select" required>
                                <option value="">Select Vehicle Type</option>
                                <option value="sedan">Sedan</option>
                                <option value="suv">SUV</option>
                                <option value="van">Van</option>
                                <option value="motorcycle">Motorcycle</option>
                                <option value="any">Any</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mt-2">
                        <div class="col-md-6">
                            <label class="form-label">Price per Day (₱)</label>
                            <input type="number" name="parking_price" id="parkingPrice" class="form-control" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="parking_status" id="parkingStatus" class="form-select" required>
                                <option value="available">Available</option>
                                <option value="occupied">Occupied</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i>Save Parking Space
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Room Modal Functions
    function openAddRoomModal() {
        document.getElementById('roomModalTitle').textContent = 'Add New Room';
        document.getElementById('roomAction').value = 'add_room';
        document.getElementById('roomForm').reset();
        document.getElementById('roomId').value = '';
    }

    function editRoom(room) {
        document.getElementById('roomModalTitle').textContent = 'Edit Room';
        document.getElementById('roomAction').value = 'update_room';
        document.getElementById('roomId').value = room._id.$oid;
        document.getElementById('roomNumber').value = room.room_number;
        document.getElementById('roomType').value = room.room_type;
        document.getElementById('roomFloor').value = room.floor;
        document.getElementById('roomCapacity').value = room.capacity;
        document.getElementById('roomPrice').value = room.price_per_night;
        document.getElementById('roomStatus').value = room.status;
        document.getElementById('roomDescription').value = room.description || '';
        
        // Uncheck all amenities first
        document.querySelectorAll('input[name="amenities[]"]').forEach(checkbox => {
            checkbox.checked = false;
        });
        
        // Check selected amenities
        if (room.amenities) {
            room.amenities.forEach(amenity => {
                const checkbox = document.querySelector(`input[value="${amenity}"]`);
                if (checkbox) checkbox.checked = true;
            });
        }
    }

    function confirmDeleteRoom(roomId, roomNumber) {
        if (confirm(`Are you sure you want to delete Room ${roomNumber}?\n\nThis action cannot be undone. The room will only be deleted if it has no active bookings.`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete_room">
                <input type="hidden" name="room_id" value="${roomId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    // Parking Modal Functions
    function openAddParkingModal() {
        document.getElementById('parkingModalTitle').textContent = 'Add New Parking Space';
        document.getElementById('parkingAction').value = 'add_parking';
        document.getElementById('parkingForm').reset();
        document.getElementById('parkingId').value = '';
    }

    function editParking(parking) {
        document.getElementById('parkingModalTitle').textContent = 'Edit Parking Space';
        document.getElementById('parkingAction').value = 'update_parking';
        document.getElementById('parkingId').value = parking._id.$oid;
        document.getElementById('spaceNumber').value = parking.space_number;
        document.getElementById('parkingType').value = parking.parking_type;
        document.getElementById('parkingFloor').value = parking.floor;
        document.getElementById('vehicleType').value = parking.vehicle_type;
        document.getElementById('parkingPrice').value = parking.price_per_day;
        document.getElementById('parkingStatus').value = parking.status;
    }

    function confirmDeleteParking(parkingId, spaceNumber) {
        if (confirm(`Are you sure you want to delete Parking Space ${spaceNumber}?\n\nThis action cannot be undone. The space will only be deleted if it has no active assignments.`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete_parking">
                <input type="hidden" name="parking_id" value="${parkingId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    // Search Functions
    function searchRooms() {
        const input = document.getElementById('roomSearch');
        const filter = input.value.toLowerCase();
        const table = document.getElementById('roomsTable');
        
        if (!table) return;
        
        const tr = table.getElementsByTagName('tr');

        for (let i = 1; i < tr.length; i++) {
            const td = tr[i].getElementsByTagName('td');
            let found = false;
            
            for (let j = 0; j < td.length; j++) {
                if (td[j]) {
                    const txtValue = td[j].textContent || td[j].innerText;
                    if (txtValue.toLowerCase().indexOf(filter) > -1) {
                        found = true;
                        break;
                    }
                }
            }
            
            tr[i].style.display = found ? '' : 'none';
        }
    }

    function searchParking() {
        const input = document.getElementById('parkingSearch');
        const filter = input.value.toLowerCase();
        const table = document.getElementById('parkingTable');
        
        if (!table) return;
        
        const tr = table.getElementsByTagName('tr');

        for (let i = 1; i < tr.length; i++) {
            const td = tr[i].getElementsByTagName('td');
            let found = false;
            
            for (let j = 0; j < td.length; j++) {
                if (td[j]) {
                    const txtValue = td[j].textContent || td[j].innerText;
                    if (txtValue.toLowerCase().indexOf(filter) > -1) {
                        found = true;
                        break;
                    }
                }
            }
            
            tr[i].style.display = found ? '' : 'none';
        }
    }

    // Auto-hide success alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert-success');
        alerts.forEach(alert => {
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });
    });
</script>

</body>
</html>