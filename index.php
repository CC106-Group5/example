<?php
session_start();
require_once 'config.php'; // must define $mongoUri and other settings

// Connect to MongoDB
try {
    $client = new MongoDB\Client($mongoUri);
    $db = $client->elodge_db;
} catch (Exception $e) {
    die('Database connection error: ' . $e->getMessage());
}

// Helper
function redirect_back($anchor = '') {
    header('Location: index.php' . ($anchor ? "#" . $anchor : ''));
    exit();
}

// Determine login state
$isLoggedIn = isset($_SESSION['user_id']) && isset($_SESSION['role']);
$isGuest = $isLoggedIn && $_SESSION['role'] === 'guest';

// Ensure uploads folder exists
$uploadDir = __DIR__ . '/uploads/guest_ids/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// ----------------------
// HANDLE FORMS (POST)
// ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // REGISTER
    if (isset($_POST['action']) && $_POST['action'] === 'register') {
        $name = trim($_POST['name'] ?? '');
        $email = trim(strtolower($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';

        if (!$name || !$email || !$password) {
            $_SESSION['flash_error'] = 'Please fill all registration fields.';
            redirect_back('');
        }

        // check existing
        $usersColl = $db->users;
        $existing = $usersColl->findOne(['email' => $email]);
        if ($existing) {
            $_SESSION['flash_error'] = 'Email is already registered.';
            redirect_back('');
        }

        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $newUser = [
            'name' => $name,
            'email' => $email,
            'password' => $hashed,
            'role' => 'guest',
            'id_status' => 'not_uploaded',
            'id_filename' => null,
            'created_at' => new MongoDB\BSON\UTCDateTime()
        ];
        $result = $usersColl->insertOne($newUser);
        if ($result->getInsertedId()) {
            $_SESSION['flash_success'] = 'Registration successful. Please login.';
        } else {
            $_SESSION['flash_error'] = 'Registration failed. Try again.';
        }
        redirect_back('');
    }

    // LOGIN
    if (isset($_POST['action']) && $_POST['action'] === 'login') {
        $email = trim(strtolower($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';

        $usersColl = $db->users;
        $user = $usersColl->findOne(['email' => $email, 'role' => $role]);
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = (string)$user['_id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            // redirect guests to index (same page), others to their dashboards
            if ($user['role'] === 'guest') redirect_back('guest-dashboard');
            if ($user['role'] === 'admin') header('Location: admin_dashboard.php');
            if ($user['role'] === 'receptionist') header('Location: receptionist_dashboard.php');
            exit();
        } else {
            $_SESSION['flash_error'] = 'Invalid credentials.';
            redirect_back('');
        }
    }

    // LOGOUT
    if (isset($_POST['action']) && $_POST['action'] === 'logout') {
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['flash_success'] = 'Logged out successfully.';
        redirect_back('');
    }

    // UPLOAD ID
    if (isset($_POST['action']) && $_POST['action'] === 'upload_id' && $isGuest) {
        if (!isset($_FILES['id_file']) || $_FILES['id_file']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['flash_error'] = 'Please choose a file to upload.';
            redirect_back('guest-dashboard');
        }

        $file = $_FILES['id_file'];
        $allowedExt = ['jpg','jpeg','png','pdf'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        $allowedMime = ['image/jpeg','image/png','application/pdf'];

        if (!in_array($ext, $allowedExt) || !in_array($mime, $allowedMime)) {
            $_SESSION['flash_error'] = 'Invalid file type. Only JPG/PNG/PDF files accepted (valid government ID).';
            redirect_back('guest-dashboard');
        }

        // limit size (5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            $_SESSION['flash_error'] = 'File too large. Max 5MB.';
            redirect_back('guest-dashboard');
        }

        // create safe filename
        $userId = $_SESSION['user_id'];
        $safeName = 'id_' . preg_replace('/[^a-z0-9_\-]/i','', $userId) . '_' . time() . '.' . $ext;
        $destination = $uploadDir . $safeName;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $_SESSION['flash_error'] = 'Upload failed.';
            redirect_back('guest-dashboard');
        }

        // Update user doc with pending status
        $usersColl = $db->users;
        $usersColl->updateOne(['_id' => new MongoDB\BSON\ObjectId($userId)], [
            '$set' => [
                'id_filename' => $safeName, 
                'id_status' => 'pending',
                'id_uploaded_at' => new MongoDB\BSON\UTCDateTime()
            ]
        ]);

        // Create notification for user
        $notificationsColl = $db->notifications;
        $notificationsColl->insertOne([
            'user_id' => $userId,
            'message' => 'Your ID has been uploaded and is pending verification. This may take up to 24 hours.',
            'type' => 'info',
            'read' => false,
            'created_at' => new MongoDB\BSON\UTCDateTime()
        ]);

        $_SESSION['flash_success'] = 'ID uploaded successfully. Verification may take up to 24 hours.';
        redirect_back('guest-dashboard');
    }

    // BOOK ROOM or BOOK PARKING
    if (isset($_POST['action']) && in_array($_POST['action'], ['book_room','book_parking']) && $isGuest) {
        $action = $_POST['action'];
        $guestId = $_SESSION['user_id'];
        
        // Check ID verification status
        $usersColl = $db->users;
        $user = $usersColl->findOne(['_id' => new MongoDB\BSON\ObjectId($guestId)]);
        
        if (!$user || $user['id_status'] !== 'verified') {
            $_SESSION['flash_error'] = 'Please upload and verify your ID before booking.';
            $_SESSION['show_id_overlay'] = true;
            redirect_back('guest-dashboard');
        }

        $reservationsColl = $db->reservations;

        if ($action === 'book_room') {
            $room_type = $_POST['room_type'] ?? '';
            $check_in = $_POST['check_in'] ?? '';
            $check_out = $_POST['check_out'] ?? '';
            if (!$room_type || !$check_in || !$check_out) {
                $_SESSION['flash_error'] = 'Please complete booking fields.';
                redirect_back('guest-dashboard');
            }
            // create reservation
            $res = [
                'guest_id' => $guestId,
                'type' => 'room',
                'room_type' => $room_type,
                'check_in' => $check_in,
                'check_out' => $check_out,
                'parking_slot' => null,
                'status' => 'booked',
                'created_at' => new MongoDB\BSON\UTCDateTime()
            ];
            $reservationsColl->insertOne($res);
            
            // Create notification
            $notificationsColl = $db->notifications;
            $notificationsColl->insertOne([
                'user_id' => $guestId,
                'message' => 'Room booking confirmed! ' . $room_type . ' from ' . $check_in . ' to ' . $check_out,
                'type' => 'success',
                'read' => false,
                'created_at' => new MongoDB\BSON\UTCDateTime()
            ]);
            
            $_SESSION['flash_success'] = 'Room booked successfully.';
            redirect_back('guest-dashboard');
        }

        if ($action === 'book_parking') {
            $slot = $_POST['parking_slot'] ?? '';
            if (!$slot) {
                $_SESSION['flash_error'] = 'Please pick a parking slot.';
                redirect_back('guest-dashboard');
            }
            // mark parking slot as reserved in parking collection if needed
            $parkingColl = $db->parking;
            $slotDoc = $parkingColl->findOne(['slot_number' => $slot, 'status' => 'available']);
            if (!$slotDoc) {
                $_SESSION['flash_error'] = 'Parking slot not available.';
                redirect_back('guest-dashboard');
            }
            // create reservation
            $res = [
                'guest_id' => $guestId,
                'type' => 'parking',
                'room_type' => null,
                'check_in' => null,
                'check_out' => null,
                'parking_slot' => $slot,
                'status' => 'booked',
                'created_at' => new MongoDB\BSON\UTCDateTime()
            ];
            $reservationsColl->insertOne($res);
            // update parking status
            $parkingColl->updateOne(['_id' => $slotDoc['_id']], ['$set' => ['status' => 'booked']]);
            
            // Create notification
            $notificationsColl = $db->notifications;
            $notificationsColl->insertOne([
                'user_id' => $guestId,
                'message' => 'Parking slot ' . $slot . ' has been reserved successfully!',
                'type' => 'success',
                'read' => false,
                'created_at' => new MongoDB\BSON\UTCDateTime()
            ]);
            
            $_SESSION['flash_success'] = 'Parking slot booked successfully.';
            redirect_back('guest-dashboard');
        }
    }

    // CANCEL RESERVATION
    if (isset($_POST['action']) && $_POST['action'] === 'cancel_reservation' && $isGuest) {
        $resId = $_POST['reservation_id'] ?? '';
        if (!$resId) { $_SESSION['flash_error'] = 'Reservation not found.'; redirect_back('guest-dashboard'); }
        $reservationsColl = $db->reservations;
        $res = $reservationsColl->findOne(['_id' => new MongoDB\BSON\ObjectId($resId), 'guest_id' => $_SESSION['user_id']]);
        if (!$res) { $_SESSION['flash_error'] = 'Reservation not found.'; redirect_back('guest-dashboard'); }

        // Mark cancelled
        $reservationsColl->updateOne(['_id' => $res['_id']], ['$set' => ['status' => 'cancelled', 'cancelled_at' => new MongoDB\BSON\UTCDateTime()]]);
        // if parking, free the slot
        if (isset($res['parking_slot']) && $res['parking_slot']) {
            $parkingColl = $db->parking;
            $parkingColl->updateOne(['slot_number' => $res['parking_slot']], ['$set' => ['status' => 'available']]);
        }
        
        // Create notification
        $notificationsColl = $db->notifications;
        $notificationsColl->insertOne([
            'user_id' => $_SESSION['user_id'],
            'message' => 'Your reservation has been cancelled successfully.',
            'type' => 'warning',
            'read' => false,
            'created_at' => new MongoDB\BSON\UTCDateTime()
        ]);
        
        $_SESSION['flash_success'] = 'Reservation cancelled.';
        redirect_back('guest-dashboard');
    }

    // MARK NOTIFICATION AS READ
    if (isset($_POST['action']) && $_POST['action'] === 'mark_notification_read' && $isGuest) {
        $notifId = $_POST['notification_id'] ?? '';
        if ($notifId) {
            $notificationsColl = $db->notifications;
            $notificationsColl->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($notifId), 'user_id' => $_SESSION['user_id']], 
                ['$set' => ['read' => true]]
            );
        }
        echo json_encode(['success' => true]);
        exit();
    }

    // MARK ALL NOTIFICATIONS AS READ
    if (isset($_POST['action']) && $_POST['action'] === 'mark_all_read' && $isGuest) {
        $notificationsColl = $db->notifications;
        $notificationsColl->updateMany(
            ['user_id' => $_SESSION['user_id'], 'read' => false], 
            ['$set' => ['read' => true]]
        );
        echo json_encode(['success' => true]);
        exit();
    }
}

// ----------------------
// READ DATA FOR DISPLAY
// ----------------------
$rooms = $db->rooms->find()->toArray();
$parkingSlots = $db->parking->find()->toArray();
$userReservations = [];
$userDoc = null;
$notifications = [];
$unreadCount = 0;

if ($isGuest) {
    $userDoc = $db->users->findOne(['_id' => new MongoDB\BSON\ObjectId($_SESSION['user_id'])]);
    $userReservations = $db->reservations->find(['guest_id' => $_SESSION['user_id']])->toArray();
    
    // Get notifications
    $notificationsColl = $db->notifications;
    $notifications = $notificationsColl->find(
        ['user_id' => $_SESSION['user_id']], 
        ['sort' => ['created_at' => -1], 'limit' => 20]
    )->toArray();
    $unreadCount = $notificationsColl->count(['user_id' => $_SESSION['user_id'], 'read' => false]);
}

$showIdOverlay = isset($_SESSION['show_id_overlay']) && $_SESSION['show_id_overlay'];
unset($_SESSION['show_id_overlay']);

// ----------------------
// HTML OUTPUT START
// ----------------------
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Adine Hotel | Experience Timeless Luxury</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root{--urban-espresso:#5B4A3E;--pavement-shadow:#8A8077;--luxe-oat:#CBBFAF;--ivory-silk:#E8DED4;--sunlit-veil:#F6F2EB;--text-dark:#3C2F28}
body{font-family: 'Segoe UI',sans-serif;background-color:var(--sunlit-veil);color:var(--text-dark);overflow-x:hidden}
.navbar{position:fixed;width:100%;top:0;z-index:10;background:transparent;transition:all .4s ease}
.navbar.scrolled{background-color:var(--urban-espresso);box-shadow:0 2px 10px rgba(0,0,0,.1)}
.navbar-brand{color:var(--ivory-silk)!important;font-weight:700}
.navbar-nav .nav-link{color:var(--ivory-silk)!important;font-weight:500;margin:0 12px;font-size:1.05rem}
.navbar-nav .nav-link:hover{color:var(--luxe-oat)!important}
.btn-login{background-color:var(--luxe-oat);color:var(--urban-espresso);border-radius:8px;padding:8px 18px;border:none;font-weight:600}
.btn-login:hover{background-color:var(--pavement-shadow);color:var(--ivory-silk)}
.btn-book{background-color:var(--urban-espresso);color:var(--ivory-silk);border:none;border-radius:8px;padding:10px 20px;font-weight:600}
.btn-book:hover{background-color:var(--pavement-shadow)}
.hero{background:url('ADINE_bg.jpg') center/cover no-repeat;height:100vh;display:flex;align-items:center;justify-content:center;text-align:center;color:white;position:relative}
.hero::before{content:'';position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.45)}
.hero-content{position:relative;z-index:2}
.hero h1{font-size:3.5rem;font-weight:700}
.booking-form{background-color:var(--ivory-silk);padding:35px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.08);margin-top:-80px;position:relative;z-index:3}
.section-title{text-align:center;margin:80px 0 40px;font-size:2rem;font-weight:700;color:var(--urban-espresso)}
.room-card,.parking-card{border:none;border-radius:15px;overflow:hidden;box-shadow:0 5px 15px rgba(0,0,0,.1)}
.room-card img,.parking-card img{height:250px;object-fit:cover}
.card-body h5{font-weight:600;color:var(--urban-espresso)}
.modal-content{border-radius:20px;border:none;background:var(--ivory-silk);box-shadow:0 12px 30px rgba(0,0,0,.25)}
.modal-header{background:var(--urban-espresso);color:var(--ivory-silk);border-top-left-radius:20px;border-top-right-radius:20px}
footer{background:var(--urban-espresso);color:var(--ivory-silk);padding:30px 0;text-align:center;margin-top:60px}

/* Notification Bell */
.notification-bell{position:relative;cursor:pointer;color:var(--ivory-silk);font-size:1.5rem;margin-left:15px}
.notification-badge{position:absolute;top:-8px;right:-8px;background:#dc3545;color:white;border-radius:50%;padding:2px 6px;font-size:0.7rem;font-weight:bold;min-width:20px;text-align:center}
.notification-dropdown{position:absolute;top:50px;right:20px;width:380px;max-height:500px;overflow-y:auto;background:white;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.2);display:none;z-index:1000}
.notification-dropdown.show{display:block}
.notification-header{background:var(--urban-espresso);color:white;padding:15px;border-radius:12px 12px 0 0;display:flex;justify-content:space-between;align-items:center}
.notification-item{padding:15px;border-bottom:1px solid #eee;cursor:pointer;transition:background .2s}
.notification-item:hover{background:#f8f9fa}
.notification-item.unread{background:#fff9e6}
.notification-item .time{font-size:0.8rem;color:#888;margin-top:5px}
.notification-empty{padding:30px;text-align:center;color:#888}
.notification-type-success{border-left:4px solid #28a745}
.notification-type-warning{border-left:4px solid #ffc107}
.notification-type-info{border-left:4px solid #17a2b8}
.notification-type-danger{border-left:4px solid #dc3545}

/* ID Overlay */
.id-overlay{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.85);z-index:9999;display:flex;align-items:center;justify-content:center}
.id-overlay-content{background:white;padding:40px;border-radius:20px;max-width:600px;text-align:center;box-shadow:0 12px 40px rgba(0,0,0,.3)}
.id-overlay-content h3{color:var(--urban-espresso);margin-bottom:20px}
.id-overlay-content .icon{font-size:4rem;color:var(--luxe-oat);margin-bottom:20px}
.id-status-badge{display:inline-block;padding:8px 16px;border-radius:20px;font-weight:600;margin:15px 0}
.id-status-pending{background:#fff3cd;color:#856404}
.id-status-verified{background:#d1e7dd;color:#0f5132}
.id-status-rejected{background:#f8d7da;color:#842029}
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark py-3">
  <div class="container">
    <a class="navbar-brand" href="#">Adine Hotel</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navmenu">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse justify-content-end" id="navmenu">
      <ul class="navbar-nav">
        <li class="nav-item"><a href="#home" class="nav-link active">Home</a></li>
        <li class="nav-item"><a href="#rooms" class="nav-link">Rooms</a></li>
        <li class="nav-item"><a href="#parking" class="nav-link">Parking</a></li>
        <li class="nav-item"><a href="#contact" class="nav-link">Contact</a></li>
        <?php if (!$isGuest): ?>
        <li class="nav-item">
          <button class="btn btn-login ms-3 open-login"><i class="bi bi-person"></i> Login</button>
        </li>
        <?php else: ?>
        <li class="nav-item d-flex align-items-center">
          <div class="notification-bell" id="notificationBell">
            <i class="bi bi-bell-fill"></i>
            <?php if ($unreadCount > 0): ?>
            <span class="notification-badge"><?= $unreadCount ?></span>
            <?php endif; ?>
          </div>
          <div class="dropdown">
            <a class="btn btn-login dropdown-toggle ms-3" href="#" role="button" data-bs-toggle="dropdown">
              <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['name'] ?? 'Guest') ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="#guest-dashboard">My Dashboard</a></li>
              <li>
                <form method="post" class="m-0">
                  <input type="hidden" name="action" value="logout">
                  <button type="submit" class="dropdown-item">Logout</button>
                </form>
              </li>
            </ul>
          </div>
        </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<!-- NOTIFICATION DROPDOWN -->
<?php if ($isGuest): ?>
<div class="notification-dropdown" id="notificationDropdown">
  <div class="notification-header">
    <h6 class="m-0">Notifications</h6>
    <?php if ($unreadCount > 0): ?>
    <button class="btn btn-sm btn-light" onclick="markAllRead()">Mark all read</button>
    <?php endif; ?>
  </div>
  <div id="notificationList">
    <?php if (empty($notifications)): ?>
    <div class="notification-empty">
      <i class="bi bi-bell-slash" style="font-size:2rem;color:#ccc;"></i>
      <p class="mt-2">No notifications yet</p>
    </div>
    <?php else: ?>
      <?php foreach ($notifications as $notif): ?>
      <div class="notification-item notification-type-<?= htmlspecialchars($notif['type'] ?? 'info') ?> <?= !$notif['read'] ? 'unread' : '' ?>" 
           onclick="markAsRead('<?= (string)$notif['_id'] ?>')">
        <div><?= htmlspecialchars($notif['message']) ?></div>
        <div class="time"><?= $notif['created_at']->toDateTime()->format('M d, Y h:i A') ?></div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- ID VERIFICATION OVERLAY -->
<?php if ($showIdOverlay && $isGuest): ?>
<div class="id-overlay" id="idOverlay">
  <div class="id-overlay-content">
    <div class="icon"><i class="bi bi-shield-check"></i></div>
    <h3>ID Verification Required</h3>
    <p>To proceed with your booking, please upload a valid government-issued ID.</p>
    
    <?php if ($userDoc['id_status'] === 'not_uploaded'): ?>
      <div class="id-status-badge" style="background:#f8d7da;color:#842029">
        <i class="bi bi-exclamation-circle"></i> No ID uploaded
      </div>
      <form method="post" enctype="multipart/form-data" class="mt-3">
        <input type="hidden" name="action" value="upload_id">
        <div class="mb-3">
          <label class="form-label">Upload Government-Issued ID (JPG, PNG, or PDF)</label>
          <input class="form-control" type="file" name="id_file" accept="image/*,.pdf" required>
        </div>
        <button class="btn btn-book w-100 mb-2" type="submit">Upload ID</button>
      </form>
    <?php elseif ($userDoc['id_status'] === 'pending'): ?>
      <div class="id-status-badge id-status-pending">
        <i class="bi bi-clock-history"></i> Verification Pending
      </div>
      <p class="mt-3">Your ID is currently being reviewed. This may take up to 24 hours.</p>
    <?php elseif ($userDoc['id_status'] === 'rejected'): ?>
      <div class="id-status-badge id-status-rejected">
        <i class="bi bi-x-circle"></i> ID Rejected
      </div>
      <p class="mt-3">Your ID was rejected. Please upload a clear, valid government-issued ID.</p>
      <form method="post" enctype="multipart/form-data" class="mt-3">
        <input type="hidden" name="action" value="upload_id">
        <div class="mb-3">
          <input class="form-control" type="file" name="id_file" accept="image/*,.pdf" required>
        </div>
        <button class="btn btn-book w-100 mb-2" type="submit">Re-upload ID</button>
      </form>
    <?php endif; ?>
    
    <button class="btn btn-secondary w-100 mt-2" onclick="closeOverlay()">Close</button>
  </div>
</div>
<?php endif; ?>

<!-- HERO -->
<section class="hero" id="home">
  <div class="hero-content">
    <h1>Welcome to Adine Hotel</h1>
    <p>Discover a sanctuary of modern luxury and timeless comfort.</p>
    <button class="btn btn-book btn-lg open-login">Book Your Stay</button>
  </div>
</section>

<!-- AVAILABILITY CHECK -->
<div class="container booking-form mt-5">
  <form>
    <div class="row g-3">
      <div class="col-md-3">
        <label>Check-In</label>
        <input type="date" class="form-control">
      </div>
      <div class="col-md-3">
        <label>Check-Out</label>
        <input type="date" class="form-control">
      </div>
      <div class="col-md-3">
        <label>Guests</label>
        <select class="form-select">
          <option>1 Guest</option>
          <option>2 Guests</option>
          <option>3 Guests</option>
          <option>4 Guests</option>
        </select>
      </div>
      <div class="col-md-3 d-flex align-items-end">
        <button type="button" class="btn btn-book w-100 open-login">Check Availability</button>
      </div>
    </div>
  </form>
</div>

<!-- FEATURED ROOMS -->
<section id="rooms">
  <h2 class="section-title">Featured Rooms</h2>
  <div class="container">
    <div class="row g-4">
      <?php foreach ($rooms as $r): ?>
      <div class="col-md-4">
        <div class="card room-card">
          <img src="<?= htmlspecialchars($r['image'] ?? 'room1.jpg') ?>" class="card-img-top" alt="">
          <div class="card-body">
            <h5><?= htmlspecialchars($r['name'] ?? $r['type'] ?? 'Room') ?></h5>
            <p><?= htmlspecialchars($r['description'] ?? '') ?></p>
            <button class="btn btn-book open-login w-100">Book Now</button>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- PARKING -->
<section id="parking">
  <h2 class="section-title">Secure Parking</h2>
  <div class="container text-center">
    <div class="card parking-card mx-auto" style="max-width:700px;">
      <img src="parking.jpg" alt="">
      <div class="card-body">
        <h5>24/7 Parking Access</h5>
        <p>Safe and monitored parking area for all guests and visitors.</p>
      </div>
    </div>
  </div>
</section>

<!-- CONTACT -->
<section id="contact">
  <h2 class="section-title">Contact Us</h2>
  <div class="container text-center">
    <p><i class="bi bi-geo-alt-fill"></i> Roxas, Oriental Mindoro</p>
    <p><i class="bi bi-envelope-fill"></i> adinehotel@email.com</p>
    <p><i class="bi bi-phone-fill"></i> +63 999 999 9999</p>
  </div>
</section>

<!-- GUEST DASHBOARD -->
<?php if ($isGuest): ?>
<section id="guest-dashboard">
  <h2 class="section-title">Welcome, <?= htmlspecialchars($_SESSION['name'] ?? 'Guest') ?>!</h2>
  <div class="container">
    
    <!-- Flash Messages -->
    <?php if (isset($_SESSION['flash_error'])): ?>
      <div class="alert alert-danger alert-dismissible fade show">
        <?php echo $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['flash_success'])): ?>
      <div class="alert alert-success alert-dismissible fade show">
        <?php echo $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <div class="row">
      <div class="col-lg-4">
        <div class="card p-3 mb-4">
          <h5>Profile</h5>
          <p><strong>Name:</strong> <?= htmlspecialchars($userDoc['name'] ?? '') ?></p>
          <p><strong>Email:</strong> <?= htmlspecialchars($userDoc['email'] ?? '') ?></p>
          <p><strong>ID Status:</strong> 
            <?php 
            $status = $userDoc['id_status'] ?? 'not_uploaded';
            $badgeClass = 'secondary';
            $icon = 'exclamation-circle';
            if ($status === 'verified') { $badgeClass = 'success'; $icon = 'check-circle'; }
            elseif ($status === 'pending') { $badgeClass = 'warning'; $icon = 'clock-history'; }
            elseif ($status === 'rejected') { $badgeClass = 'danger'; $icon = 'x-circle'; }
            ?>
            <span class="badge bg-<?= $badgeClass ?>"><i class="bi bi-<?= $icon ?>"></i> <?= ucfirst($status) ?></span>
          </p>

          <?php if ($status !== 'verified'): ?>
          <hr>
          <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> ID verification required for bookings
          </div>
          <?php endif; ?>

          <?php if ($status === 'not_uploaded' || $status === 'rejected'): ?>
          <h6>Upload Valid ID</h6>
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload_id">
            <div class="mb-3">
              <input class="form-control" type="file" name="id_file" accept="image/*,.pdf" required>
              <small class="text-muted">Accepted: JPG, PNG, PDF (Max 5MB)</small>
            </div>
            <button class="btn btn-primary w-100" type="submit">Upload ID</button>
          </form>
          <?php elseif ($status === 'pending'): ?>
          <div class="alert alert-warning">
            <i class="bi bi-clock-history"></i> Your ID is being verified. This may take up to 24 hours.
          </div>
          <?php endif; ?>
        </div>

        <div class="card p-3">
          <h5>Available Parking Slots</h5>
          <ul class="list-group mt-2">
            <?php foreach ($parkingSlots as $p): ?>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                Slot <?= htmlspecialchars($p['slot_number']) ?>
                <span class="badge bg-<?= ($p['status'] === 'available') ? 'success' : 'secondary' ?> rounded-pill"><?php echo htmlspecialchars($p['status']); ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>

      <div class="col-lg-8">
        <div class="card p-3 mb-4">
          <h5>Book a Room</h5>
          <form method="post">
            <input type="hidden" name="action" value="book_room">
            <div class="row g-2">
              <div class="col-md-6">
                <label>Room Type</label>
                <select name="room_type" class="form-select" required>
                  <?php foreach ($rooms as $r): ?>
                    <option value="<?= htmlspecialchars($r['type'] ?? $r['name']) ?>"><?= htmlspecialchars($r['name'] ?? $r['type']) ?> - <?= htmlspecialchars($r['price'] ?? '') ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-3"><label>Check-In</label><input type="date" name="check_in" class="form-control" required></div>
              <div class="col-md-3"><label>Check-Out</label><input type="date" name="check_out" class="form-control" required></div>
              <div class="col-12 mt-2">
                <button class="btn btn-book w-100" type="submit">Confirm Booking</button>
              </div>
            </div>
          </form>
        </div>

        <div class="card p-3 mb-4">
          <h5>Book Parking Slot</h5>
          <form method="post">
            <input type="hidden" name="action" value="book_parking">
            <div class="row g-2">
              <div class="col-md-8">
                <label>Pick Slot</label>
                <select name="parking_slot" class="form-select" required>
                  <option value="">-- Select Slot --</option>
                  <?php foreach ($parkingSlots as $p): if ($p['status'] === 'available'): ?>
                    <option value="<?= htmlspecialchars($p['slot_number']) ?>">Slot <?= htmlspecialchars($p['slot_number']) ?></option>
                  <?php endif; endforeach; ?>
                </select>
              </div>
              <div class="col-md-4 d-flex align-items-end">
                <button class="btn btn-book w-100" type="submit">Reserve Parking</button>
              </div>
            </div>
          </form>
        </div>

        <div class="card p-3">
          <h5>Your Reservations</h5>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr><th>Type</th><th>Details</th><th>Status</th><th>Action</th></tr>
              </thead>
              <tbody>
                <?php if (empty($userReservations)): ?>
                  <tr><td colspan="4" class="text-center text-muted">No reservations yet</td></tr>
                <?php else: ?>
                  <?php foreach ($userReservations as $ur): ?>
                    <tr>
                      <td><?= htmlspecialchars($ur['type']) ?></td>
                      <td>
                        <?php if ($ur['type'] === 'room'): ?>
                          <?= htmlspecialchars($ur['room_type']) ?><br>
                          <small><?= htmlspecialchars($ur['check_in']) ?> to <?= htmlspecialchars($ur['check_out']) ?></small>
                        <?php else: ?>
                          Parking Slot <?= htmlspecialchars($ur['parking_slot']) ?>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php 
                        $statusBadge = 'secondary';
                        if ($ur['status'] === 'booked') $statusBadge = 'success';
                        elseif ($ur['status'] === 'cancelled') $statusBadge = 'danger';
                        ?>
                        <span class="badge bg-<?= $statusBadge ?>"><?= htmlspecialchars($ur['status']) ?></span>
                      </td>
                      <td>
                        <?php if ($ur['status'] !== 'cancelled'): ?>
                          <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="cancel_reservation">
                            <input type="hidden" name="reservation_id" value="<?= htmlspecialchars((string)$ur['_id']) ?>">
                            <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Cancel this reservation?')">Cancel</button>
                          </form>
                        <?php else: ?>
                          <span class="text-muted">-</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<footer>
  <p>&copy; <?= date('Y') ?> Adine Hotel. All Rights Reserved.</p>
</footer>

<!-- LOGIN / REGISTER MODAL -->
<div class="modal fade" id="loginModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">ACCESS YOUR ACCOUNT</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <?php if (isset($_SESSION['flash_error']) && !$isGuest): ?>
          <div class="alert alert-danger"><?php echo $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['flash_success']) && !$isGuest): ?>
          <div class="alert alert-success"><?php echo $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?></div>
        <?php endif; ?>

        <ul class="nav nav-tabs mb-3" id="authTabs" role="tablist">
          <li class="nav-item" role="presentation"><button class="nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#loginPane" type="button">Login</button></li>
          <li class="nav-item" role="presentation"><button class="nav-link" id="register-tab" data-bs-toggle="tab" data-bs-target="#registerPane" type="button">Register</button></li>
        </ul>
        <div class="tab-content">
          <div class="tab-pane fade show active" id="loginPane">
            <form method="post">
              <input type="hidden" name="action" value="login">
              <div class="mb-3">
                <label class="form-label">Login As</label>
                <select class="form-select" name="role" required>
                  <option value="guest">Guest</option>
                  <option value="receptionist">Receptionist</option>
                  <option value="admin">Admin</option>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Password</label>
                <div class="input-group">
                  <input type="password" name="password" class="form-control" required>
                  <span class="input-group-text" id="togglePassword" style="cursor:pointer;"><i class="bi bi-eye"></i></span>
                </div>
              </div>
              <button class="btn btn-login w-100 mb-2" type="submit">Login</button>
            </form>
          </div>

          <div class="tab-pane fade" id="registerPane">
            <form method="post">
              <input type="hidden" name="action" value="register">
              <div class="mb-3">
                <label class="form-label">Full Name</label>
                <input type="text" name="name" class="form-control" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
              </div>
              <button class="btn btn-login w-100" type="submit">Register</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// open modal buttons
document.querySelectorAll('.open-login').forEach(btn => {
  btn.addEventListener('click', () => new bootstrap.Modal(document.getElementById('loginModal')).show());
});

// toggle password
const toggle = document.getElementById('togglePassword');
if (toggle) {
  toggle.addEventListener('click', function(){
    const input = this.parentElement.querySelector('input');
    const icon = this.querySelector('i');
    if (input.type === 'password') { input.type = 'text'; icon.classList.replace('bi-eye','bi-eye-slash'); }
    else { input.type = 'password'; icon.classList.replace('bi-eye-slash','bi-eye'); }
  });
}

// navbar scroll
window.addEventListener('scroll', function(){ 
  const navbar = document.querySelector('.navbar'); 
  if (window.scrollY > 50) navbar.classList.add('scrolled'); 
  else navbar.classList.remove('scrolled'); 
});

// Notification bell toggle
const notifBell = document.getElementById('notificationBell');
const notifDropdown = document.getElementById('notificationDropdown');
if (notifBell && notifDropdown) {
  notifBell.addEventListener('click', function(e) {
    e.stopPropagation();
    notifDropdown.classList.toggle('show');
  });

  // Close dropdown when clicking outside
  document.addEventListener('click', function(e) {
    if (!notifDropdown.contains(e.target) && !notifBell.contains(e.target)) {
      notifDropdown.classList.remove('show');
    }
  });
}

// Mark notification as read
function markAsRead(notifId) {
  fetch('', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'action=mark_notification_read&notification_id=' + notifId
  }).then(() => {
    location.reload();
  });
}

// Mark all notifications as read
function markAllRead() {
  fetch('', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'action=mark_all_read'
  }).then(() => {
    location.reload();
  });
}

// Close ID overlay
function closeOverlay() {
  const overlay = document.getElementById('idOverlay');
  if (overlay) overlay.style.display = 'none';
}

// Auto-scroll to dashboard if hash present
if (window.location.hash === '#guest-dashboard') {
  setTimeout(() => {
    document.getElementById('guest-dashboard')?.scrollIntoView({behavior: 'smooth'});
  }, 300);
}
</script>
</body>
</html>