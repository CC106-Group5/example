<?php 
session_start();
require_once 'config.php';

// Check if user is logged in and is receptionist
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'receptionist') {
    header("Location: index.php");
    exit();
}

try {
    $client = new MongoDB\Client($mongoUri);
    $db = $client->elodge_db;
    
    // Handle POST actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // VALIDATE ID
        if (isset($_POST['action']) && $_POST['action'] === 'validate_id') {
            $userId = $_POST['user_id'] ?? '';
            $status = $_POST['status'] ?? '';
            $remarks = $_POST['remarks'] ?? '';
            
            if ($userId && in_array($status, ['verified', 'rejected'])) {
                $usersColl = $db->users;
                $usersColl->updateOne(
                    ['_id' => new MongoDB\BSON\ObjectId($userId)],
                    ['$set' => [
                        'id_status' => $status,
                        'id_verified_by' => $_SESSION['user_id'],
                        'id_verified_at' => new MongoDB\BSON\UTCDateTime(),
                        'id_remarks' => $remarks
                    ]]
                );
                
                // Create notification for guest
                $notificationsColl = $db->notifications;
                $message = $status === 'verified' 
                    ? 'Your ID has been verified! You can now make bookings.' 
                    : 'Your ID verification was rejected. Please upload a valid government-issued ID. Reason: ' . $remarks;
                
                $notificationsColl->insertOne([
                    'user_id' => $userId,
                    'message' => $message,
                    'type' => $status === 'verified' ? 'success' : 'danger',
                    'read' => false,
                    'created_at' => new MongoDB\BSON\UTCDateTime()
                ]);
                
                $_SESSION['flash_success'] = 'ID validation updated successfully.';
            }
            header('Location: receptionist_dashboard.php#id-validation');
            exit();
        }
        
        // WALK-IN CHECK-IN
        if (isset($_POST['action']) && $_POST['action'] === 'walkin_checkin') {
            $guestName = trim($_POST['guest_name'] ?? '');
            $guestEmail = trim($_POST['guest_email'] ?? '');
            $guestPhone = trim($_POST['guest_phone'] ?? '');
            $roomType = $_POST['room_type'] ?? '';
            $checkIn = $_POST['check_in'] ?? date('Y-m-d');
            $checkOut = $_POST['check_out'] ?? '';
            $paymentMethod = $_POST['payment_method'] ?? 'cash';
            
            if ($guestName && $roomType && $checkOut) {
                $reservationsColl = $db->reservations;
                $reservation = [
                    'guest_id' => 'walk-in',
                    'guest_name' => $guestName,
                    'guest_email' => $guestEmail,
                    'guest_phone' => $guestPhone,
                    'type' => 'room',
                    'room_type' => $roomType,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'parking_slot' => null,
                    'status' => 'checked-in',
                    'payment_method' => $paymentMethod,
                    'checked_in_by' => $_SESSION['user_id'],
                    'created_at' => new MongoDB\BSON\UTCDateTime()
                ];
                $result = $reservationsColl->insertOne($reservation);
                
                if ($result->getInsertedId()) {
                    $_SESSION['flash_success'] = 'Walk-in guest checked in successfully.';
                    $_SESSION['last_reservation_id'] = (string)$result->getInsertedId();
                }
            }
            header('Location: receptionist_dashboard.php#walk-in');
            exit();
        }
        
        // CHECK OUT
        if (isset($_POST['action']) && $_POST['action'] === 'checkout_guest') {
            $resId = $_POST['reservation_id'] ?? '';
            if ($resId) {
                $reservationsColl = $db->reservations;
                $reservationsColl->updateOne(
                    ['_id' => new MongoDB\BSON\ObjectId($resId)],
                    ['$set' => [
                        'status' => 'checked-out',
                        'checked_out_at' => new MongoDB\BSON\UTCDateTime(),
                        'checked_out_by' => $_SESSION['user_id']
                    ]]
                );
                
                // If parking slot, free it
                $res = $reservationsColl->findOne(['_id' => new MongoDB\BSON\ObjectId($resId)]);
                if ($res && isset($res['parking_slot']) && $res['parking_slot']) {
                    $parkingColl = $db->parking;
                    $parkingColl->updateOne(
                        ['slot_number' => $res['parking_slot']],
                        ['$set' => ['status' => 'available']]
                    );
                }
                
                $_SESSION['flash_success'] = 'Guest checked out successfully.';
            }
            header('Location: receptionist_dashboard.php#checkout');
            exit();
        }
        
        // ASSIGN PARKING
        if (isset($_POST['action']) && $_POST['action'] === 'assign_parking') {
            $resId = $_POST['reservation_id'] ?? '';
            $parkingSlot = $_POST['parking_slot'] ?? '';
            
            if ($resId && $parkingSlot) {
                // Check if slot is available
                $parkingColl = $db->parking;
                $slot = $parkingColl->findOne(['slot_number' => $parkingSlot, 'status' => 'available']);
                
                if ($slot) {
                    $reservationsColl = $db->reservations;
                    $reservationsColl->updateOne(
                        ['_id' => new MongoDB\BSON\ObjectId($resId)],
                        ['$set' => ['parking_slot' => $parkingSlot]]
                    );
                    
                    $parkingColl->updateOne(
                        ['slot_number' => $parkingSlot],
                        ['$set' => ['status' => 'occupied']]
                    );
                    
                    $_SESSION['flash_success'] = 'Parking slot assigned successfully.';
                } else {
                    $_SESSION['flash_error'] = 'Parking slot not available.';
                }
            }
            header('Location: receptionist_dashboard.php#parking');
            exit();
        }
        
        // UPDATE RESERVATION STATUS
        if (isset($_POST['action']) && $_POST['action'] === 'update_reservation') {
            $resId = $_POST['reservation_id'] ?? '';
            $newStatus = $_POST['new_status'] ?? '';
            
            if ($resId && $newStatus) {
                $reservationsColl = $db->reservations;
                $reservationsColl->updateOne(
                    ['_id' => new MongoDB\BSON\ObjectId($resId)],
                    ['$set' => ['status' => $newStatus]]
                );
                $_SESSION['flash_success'] = 'Reservation updated successfully.';
            }
            header('Location: receptionist_dashboard.php#reservations');
            exit();
        }
    }
    
    // Get statistics
    $todayStart = new MongoDB\BSON\UTCDateTime(strtotime('today') * 1000);
    $todayEnd = new MongoDB\BSON\UTCDateTime(strtotime('tomorrow') * 1000);
    
    $todayCheckIns = $db->reservations->countDocuments([
        'check_in' => date('Y-m-d')
    ]);
    
    $todayCheckOuts = $db->reservations->countDocuments([
        'check_out' => date('Y-m-d')
    ]);
    
    $activeBookings = $db->reservations->countDocuments(['status' => ['$in' => ['booked', 'checked-in']]]);
    $availableRooms = $db->rooms->countDocuments(['status' => 'available']);
    $availableParking = $db->parking->countDocuments(['status' => 'available']);
    
    // Get pending ID validations
    $pendingIds = $db->users->find(['id_status' => 'pending', 'role' => 'guest'])->toArray();
    
    // Get all reservations
    $allReservations = $db->reservations->find(
        ['status' => ['$in' => ['booked', 'checked-in']]],
        ['sort' => ['created_at' => -1], 'limit' => 20]
    )->toArray();
    
    // Get rooms and parking
    $rooms = $db->rooms->find()->toArray();
    $parkingSlots = $db->parking->find()->toArray();
    
} catch (Exception $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    $_SESSION['flash_error'] = 'Database error occurred.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receptionist Dashboard - Adine Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root{--urban-espresso:#5B4A3E;--pavement-shadow:#8A8077;--luxe-oat:#CBBFAF;--ivory-silk:#E8DED4;--sunlit-veil:#F6F2EB;--text-dark:#3C2F28}
        body{font-family:'Segoe UI',sans-serif;background:#f5f7fa;color:var(--text-dark)}
        
        .header{background:linear-gradient(135deg,var(--urban-espresso),var(--pavement-shadow));color:white;padding:20px 40px;display:flex;justify-content:space-between;align-items:center;box-shadow:0 2px 10px rgba(0,0,0,.1)}
        .header-left{display:flex;align-items:center;gap:15px}
        .logo-small{width:50px;height:50px;border-radius:50%;background:white;padding:5px}
        .header-title h1{font-size:1.8em;color:var(--luxe-oat);margin:0}
        .header-title p{font-size:0.9em;color:#ccc;margin:0}
        .user-info{text-align:right}
        .user-role{background:var(--luxe-oat);color:var(--urban-espresso);padding:4px 12px;border-radius:12px;font-size:0.85em;font-weight:600}
        .logout-btn{background:rgba(255,255,255,0.2);color:white;padding:10px 20px;border:2px solid white;border-radius:8px;cursor:pointer;transition:all .3s;text-decoration:none;font-weight:600}
        .logout-btn:hover{background:white;color:var(--urban-espresso)}
        
        .container-custom{max-width:1400px;margin:40px auto;padding:0 40px}
        .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:25px;margin-bottom:40px}
        .stat-card{background:white;padding:25px;border-radius:15px;box-shadow:0 2px 15px rgba(0,0,0,.08);border-left:5px solid var(--urban-espresso);transition:transform .3s}
        .stat-card:hover{transform:translateY(-5px);box-shadow:0 5px 25px rgba(0,0,0,.15)}
        .stat-number{font-size:2.5em;font-weight:bold;color:var(--urban-espresso);margin-bottom:10px}
        .stat-label{color:#666;font-size:0.95em}
        
        .section-title{font-size:1.8em;color:var(--urban-espresso);margin:40px 0 25px;padding-bottom:10px;border-bottom:3px solid var(--pavement-shadow)}
        .card-custom{background:white;padding:30px;border-radius:15px;box-shadow:0 2px 15px rgba(0,0,0,.08);margin-bottom:30px}
        
        .btn-primary-custom{background:var(--urban-espresso);border:none;color:white;padding:10px 20px;border-radius:8px;font-weight:600;transition:all .3s}
        .btn-primary-custom:hover{background:var(--pavement-shadow);transform:translateY(-2px)}
        
        .table-custom{width:100%;border-collapse:collapse}
        .table-custom th{background:var(--urban-espresso);color:white;padding:15px;text-align:left;font-weight:600}
        .table-custom td{padding:15px;border-bottom:1px solid #eee}
        .table-custom tr:hover{background:#f8f9fa}
        
        .badge-custom{padding:6px 12px;border-radius:20px;font-size:0.85em;font-weight:600}
        .badge-pending{background:#fff3cd;color:#856404}
        .badge-verified{background:#d1e7dd;color:#0f5132}
        .badge-rejected{background:#f8d7da;color:#842029}
        .badge-booked{background:#cfe2ff;color:#084298}
        .badge-checked-in{background:#d1e7dd;color:#0f5132}
        .badge-available{background:#d1e7dd;color:#0f5132}
        .badge-occupied{background:#f8d7da;color:#842029}
        
        .modal-custom .modal-content{border-radius:20px;border:none}
        .modal-custom .modal-header{background:var(--urban-espresso);color:white;border-radius:20px 20px 0 0}
        
        .id-preview{max-width:100%;max-height:400px;border-radius:10px;margin:15px 0}
        
        .tab-navigation{display:flex;gap:10px;margin-bottom:30px;flex-wrap:wrap}
        .tab-btn{padding:12px 24px;background:white;border:2px solid var(--urban-espresso);color:var(--urban-espresso);border-radius:8px;cursor:pointer;font-weight:600;transition:all .3s}
        .tab-btn:hover,.tab-btn.active{background:var(--urban-espresso);color:white}
        
        @media(max-width:768px){
            .header{flex-direction:column;gap:20px}
            .container-custom{padding:0 20px}
            .stats-grid{grid-template-columns:1fr}
            .tab-navigation{flex-direction:column}
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <img src="logos.png" alt="Logo" class="logo-small" onerror="this.style.display='none'">
            <div class="header-title">
                <h1>ADINE HOTEL</h1>
                <p>Receptionist Dashboard</p>
            </div>
        </div>
        <div class="header-right d-flex align-items-center gap-3">
            <div class="user-info">
                <div class="user-role">RECEPTIONIST</div>
                <div><?php echo htmlspecialchars($_SESSION['name'] ?? 'Receptionist'); ?></div>
            </div>
            <form method="post" action="index.php" class="m-0">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="logout-btn">Logout</button>
            </form>
        </div>
    </div>

    <div class="container-custom">
        <?php if (isset($_SESSION['flash_success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['flash_error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <h2 class="section-title">Today's Overview</h2>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $todayCheckIns ?? 0; ?></div>
                <div class="stat-label"><i class="bi bi-box-arrow-in-right"></i> Check-ins Today</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $todayCheckOuts ?? 0; ?></div>
                <div class="stat-label"><i class="bi bi-box-arrow-right"></i> Check-outs Today</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $activeBookings ?? 0; ?></div>
                <div class="stat-label"><i class="bi bi-calendar-check"></i> Active Bookings</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $availableRooms ?? 0; ?></div>
                <div class="stat-label"><i class="bi bi-door-open"></i> Available Rooms</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $availableParking ?? 0; ?></div>
                <div class="stat-label"><i class="bi bi-p-square"></i> Parking Available</div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="tab-navigation">
            <button class="tab-btn active" onclick="showTab('id-validation')"><i class="bi bi-shield-check"></i> ID Validation</button>
            <button class="tab-btn" onclick="showTab('walk-in')"><i class="bi bi-person-plus"></i> Walk-in Check-in</button>
            <button class="tab-btn" onclick="showTab('checkout')"><i class="bi bi-box-arrow-right"></i> Check-out</button>
            <button class="tab-btn" onclick="showTab('parking')"><i class="bi bi-p-square"></i> Parking</button>
            <button class="tab-btn" onclick="showTab('reservations')"><i class="bi bi-calendar3"></i> Reservations</button>
            <button class="tab-btn" onclick="showTab('availability')"><i class="bi bi-list-check"></i> Availability</button>
            <button class="tab-btn" onclick="showTab('receipts')"><i class="bi bi-receipt"></i> Receipts</button>
        </div>

        <!-- ID VALIDATION TAB -->
        <div id="id-validation" class="tab-content">
            <h2 class="section-title">ID Validation (<?php echo count($pendingIds); ?> Pending)</h2>
            <div class="card-custom">
                <?php if (empty($pendingIds)): ?>
                    <p class="text-center text-muted">No pending ID validations</p>
                <?php else: ?>
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Guest Name</th>
                                <th>Email</th>
                                <th>Uploaded</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingIds as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo isset($user['id_uploaded_at']) ? $user['id_uploaded_at']->toDateTime()->format('M d, Y h:i A') : 'N/A'; ?></td>
                                    <td><span class="badge-custom badge-pending">Pending</span></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary-custom" onclick="viewId('<?php echo (string)$user['_id']; ?>', '<?php echo htmlspecialchars($user['id_filename']); ?>', '<?php echo htmlspecialchars($user['name']); ?>')">
                                            <i class="bi bi-eye"></i> Review
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- WALK-IN CHECK-IN TAB -->
        <div id="walk-in" class="tab-content" style="display:none">
            <h2 class="section-title">Walk-in Guest Check-in</h2>
            <div class="card-custom">
                <form method="post">
                    <input type="hidden" name="action" value="walkin_checkin">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Guest Name *</label>
                            <input type="text" name="guest_name" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email</label>
                            <input type="email" name="guest_email" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="guest_phone" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Room Type *</label>
                            <select name="room_type" class="form-select" required>
                                <?php foreach ($rooms as $room): ?>
                                    <option value="<?php echo htmlspecialchars($room['type'] ?? $room['name']); ?>">
                                        <?php echo htmlspecialchars($room['name'] ?? $room['type']); ?> - <?php echo htmlspecialchars($room['price'] ?? ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Check-in Date</label>
                            <input type="date" name="check_in" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Check-out Date *</label>
                            <input type="date" name="check_out" class="form-control" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Payment</label>
                            <select name="payment_method" class="form-select">
                                <option value="cash">Cash</option>
                                <option value="card">Card</option>
                                <option value="gcash">GCash</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary-custom btn-lg">
                                <i class="bi bi-check-circle"></i> Check-in Guest
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- CHECK-OUT TAB -->
        <div id="checkout" class="tab-content" style="display:none">
            <h2 class="section-title">Guest Check-out</h2>
            <div class="card-custom">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Guest</th>
                            <th>Room Type</th>
                            <th>Check-in</th>
                            <th>Check-out</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $checkedInGuests = array_filter($allReservations, function($r) {
                            return $r['status'] === 'checked-in' || ($r['status'] === 'booked' && $r['check_out'] === date('Y-m-d'));
                        });
                        if (empty($checkedInGuests)): ?>
                            <tr><td colspan="6" class="text-center text-muted">No guests to check out</td></tr>
                        <?php else: foreach ($checkedInGuests as $res): 
                            $guestName = $res['guest_name'] ?? 'Guest';
                            if ($res['guest_id'] !== 'walk-in') {
                                $guestDoc = $db->users->findOne(['_id' => new MongoDB\BSON\ObjectId($res['guest_id'])]);
                                $guestName = $guestDoc['name'] ?? 'Guest';
                            }
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($guestName); ?></td>
                                <td><?php echo htmlspecialchars($res['room_type'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($res['check_in']); ?></td>
                                <td><?php echo htmlspecialchars($res['check_out']); ?></td>
                                <td><span class="badge-custom badge-checked-in"><?php echo htmlspecialchars($res['status']); ?></span></td>
                                <td>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="action" value="checkout_guest">
                                        <input type="hidden" name="reservation_id" value="<?php echo (string)$res['_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Check out this guest?')">
                                            <i class="bi bi-box-arrow-right"></i> Check-out
                                        </button>
                                    </form>
                                    <button class="btn btn-sm btn-info" onclick="generateReceipt('<?php echo (string)$res['_id']; ?>')">
                                        <i class="bi bi-receipt"></i> Receipt
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- PARKING TAB -->
        <div id="parking" class="tab-content" style="display:none">
            <h2 class="section-title">Assign Parking Space</h2>
            <div class="card-custom">
                <h5>Active Reservations Without Parking</h5>
                <table class="table-custom mb-4">
                    <thead>
                        <tr>
                            <th>Guest</th>
                            <th>Room</th>
                            <th>Current Parking</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $needsParking = array_filter($allReservations, function($r) {
                            return $r['type'] === 'room' && empty($r['parking_slot']);
                        });
                        if (empty($needsParking)): ?>
                            <tr><td colspan="4" class="text-center text-muted">All reservations have parking assigned</td></tr>
                        <?php else: foreach ($needsParking as $res): 
                            $guestName = $res['guest_name'] ?? 'Guest';
                            if ($res['guest_id'] !== 'walk-in') {
                                $guestDoc = $db->users->findOne(['_id' => new MongoDB\BSON\ObjectId($res['guest_id'])]);
                                $guestName = $guestDoc['name'] ?? 'Guest';
                            }
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($guestName); ?></td>
                                <td><?php echo htmlspecialchars($res['room_type']); ?></td>
                                <td><span class="text-muted">Not assigned</span></td>
                                <td>
                                    <button class="btn btn-sm btn-primary-custom" onclick="assignParking('<?php echo (string)$res['_id']; ?>', '<?php echo htmlspecialchars($guestName); ?>')">
                                        <i class="bi bi-p-square"></i> Assign Parking
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>

                <h5>Parking Slots Overview</h5>
                <div class="row g-3">
                    <?php foreach ($parkingSlots as $slot): ?>
                        <div class="col-md-3">
                            <div class="card text-center p-3 <?php echo $slot['status'] === 'available' ? 'border-success' : 'border-danger'; ?>">
                                <h4><i class="bi bi-p-square"></i> Slot <?php echo htmlspecialchars($slot['slot_number']); ?></h4>
                                <span class="badge-custom <?php echo $slot['status'] === 'available' ? 'badge-available' : 'badge-occupied'; ?>">
                                    <?php echo htmlspecialchars($slot['status']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <!-- RESERVATIONS TAB -->
<div id="reservations" class="tab-content" style="display:none">
    <h2 class="section-title">Manage Reservations</h2>
    <div class="card-custom">
        <table class="table-custom">
            <thead>
                <tr>
                    <th>Guest</th>
                    <th>Room Type</th>
                    <th>Check-in</th>
                    <th>Check-out</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allReservations as $res): 
                    $guestName = $res['guest_name'] ?? 'Guest';
                    if ($res['guest_id'] !== 'walk-in') {
                        $guestDoc = $db->users->findOne(['_id' => new MongoDB\BSON\ObjectId($res['guest_id'])]);
                        $guestName = $guestDoc['name'] ?? 'Guest';
                    }
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($guestName); ?></td>
                    <td><?php echo htmlspecialchars($res['room_type']); ?></td>
                    <td><?php echo htmlspecialchars($res['check_in']); ?></td>
                    <td><?php echo htmlspecialchars($res['check_out']); ?></td>
                    <td>
                        <span class="badge-custom <?php 
                            echo $res['status']=='booked'?'badge-booked':'badge-checked-in';
                        ?>">
                        <?php echo htmlspecialchars($res['status']); ?>
                        </span>
                    </td>
                    <td>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="update_reservation">
                            <input type="hidden" name="reservation_id" value="<?php echo (string)$res['_id']; ?>">
                            <select name="new_status" class="form-select form-select-sm d-inline w-auto">
                                <option value="booked" <?php echo $res['status']=='booked'?'selected':''; ?>>Booked</option>
                                <option value="checked-in" <?php echo $res['status']=='checked-in'?'selected':''; ?>>Checked-in</option>
                                <option value="checked-out" <?php echo $res['status']=='checked-out'?'selected':''; ?>>Checked-out</option>
                            </select>
                            <button type="submit" class="btn btn-sm btn-primary">Update</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- AVAILABILITY TAB -->
<div id="availability" class="tab-content" style="display:none">
    <h2 class="section-title">Room & Parking Availability</h2>
    <div class="card-custom">
        <h5>Rooms</h5>
        <div class="row g-3 mb-4">
            <?php foreach ($rooms as $room): ?>
                <div class="col-md-3">
                    <div class="card text-center p-3 <?php echo $room['status']=='available'?'border-success':'border-danger'; ?>">
                        <h4><?php echo htmlspecialchars($room['name']); ?></h4>
                        <span class="badge-custom <?php echo $room['status']=='available'?'badge-available':'badge-occupied'; ?>">
                            <?php echo htmlspecialchars($room['status']); ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <h5>Parking Slots</h5>
        <div class="row g-3">
            <?php foreach ($parkingSlots as $slot): ?>
                <div class="col-md-3">
                    <div class="card text-center p-3 <?php echo $slot['status']=='available'?'border-success':'border-danger'; ?>">
                        <h4><i class="bi bi-p-square"></i> Slot <?php echo htmlspecialchars($slot['slot_number']); ?></h4>
                        <span class="badge-custom <?php echo $slot['status']=='available'?'badge-available':'badge-occupied'; ?>">
                            <?php echo htmlspecialchars($slot['status']); ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- RECEIPTS TAB -->
<div id="receipts" class="tab-content" style="display:none">
    <h2 class="section-title">Generate Receipts</h2>
    <div class="card-custom">
        <table class="table-custom">
            <thead>
                <tr>
                    <th>Guest</th>
                    <th>Room Type</th>
                    <th>Check-in</th>
                    <th>Check-out</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allReservations as $res): 
                    $guestName = $res['guest_name'] ?? 'Guest';
                    if ($res['guest_id'] !== 'walk-in') {
                        $guestDoc = $db->users->findOne(['_id' => new MongoDB\BSON\ObjectId($res['guest_id'])]);
                        $guestName = $guestDoc['name'] ?? 'Guest';
                    }
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($guestName); ?></td>
                    <td><?php echo htmlspecialchars($res['room_type']); ?></td>
                    <td><?php echo htmlspecialchars($res['check_in']); ?></td>
                    <td><?php echo htmlspecialchars($res['check_out']); ?></td>
                    <td><?php echo htmlspecialchars($res['status']); ?></td>
                    <td>
                        <button class="btn btn-sm btn-info" onclick="generateReceipt('<?php echo (string)$res['_id']; ?>')">
                            <i class="bi bi-receipt"></i> Generate
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function showTab(tabId){
    document.querySelectorAll('.tab-content').forEach(c=>c.style.display='none');
    document.getElementById(tabId).style.display='block';
    document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
    event.currentTarget.classList.add('active');
}

// ID preview
function viewId(userId, filename, name){
    const modalHtml = `
        <div class="modal fade" id="idModal" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content modal-custom">
                    <div class="modal-header">
                        <h5 class="modal-title">ID Verification - ${name}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center">
                        <img src="uploads/ids/${filename}" alt="Guest ID" class="id-preview">
                        <form method="post" class="mt-3">
                            <input type="hidden" name="action" value="validate_id">
                            <input type="hidden" name="user_id" value="${userId}">
                            <div class="mb-3">
                                <label class="form-label">Remarks (if rejected)</label>
                                <input type="text" name="remarks" class="form-control">
                            </div>
                            <button type="submit" name="status" value="verified" class="btn btn-success">Verify</button>
                            <button type="submit" name="status" value="rejected" class="btn btn-danger">Reject</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    new bootstrap.Modal(document.getElementById('idModal')).show();
}

// Assign parking
function assignParking(resId, guestName){
    const parkingSlot = prompt(`Enter parking slot for ${guestName}:`);
    if(parkingSlot){
        const form = document.createElement('form');
        form.method = 'post';
        form.style.display='none';
        form.innerHTML = `
            <input type="hidden" name="action" value="assign_parking">
            <input type="hidden" name="reservation_id" value="${resId}">
            <input type="hidden" name="parking_slot" value="${parkingSlot}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Generate receipt
function generateReceipt(resId){
    window.open('generate_receipt.php?reservation_id='+resId, '_blank');
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
