<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guest') {
    header("Location: login.php");
    exit();
}

require_once 'config.php';

$successMessage = '';
$errorMessage = '';

try {
    $client = new MongoDB\Client($mongoUri);
    $db = $client->elodge_db;

    $userIdString = $_SESSION['user_id'];
    try {
        $userId = new MongoDB\BSON\ObjectId($userIdString);
    } catch (Exception $e) {
        $userId = $userIdString;
    }

    $user = $db->users->findOne(['_id' => $userId]);

    // Handle cancellation
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'])) {
        $bookingId = $_POST['booking_id'];
        $reason = $_POST['reason'] ?? '';

        try {
            $bookingObjectId = new MongoDB\BSON\ObjectId($bookingId);
            $booking = $db->bookings->findOne(['_id' => $bookingObjectId, 'user_id' => $userId]);

            if ($booking) {
                // Update booking status
                $db->bookings->updateOne(
                    ['_id' => $bookingObjectId],
                    ['$set' => [
                        'status' => 'cancelled',
                        'cancellation_reason' => $reason,
                        'cancelled_at' => new MongoDB\BSON\UTCDateTime()
                    ]]
                );

                // Free up the room
                $db->rooms->updateOne(
                    ['_id' => $booking['room_id']],
                    ['$set' => ['status' => 'available']]
                );

                // Free up parking if exists
                if (isset($booking['parking_id'])) {
                    $db->parking->updateOne(
                        ['_id' => $booking['parking_id']],
                        ['$set' => ['status' => 'available']]
                    );
                }

                $successMessage = 'Reservation cancelled successfully. Your room and parking (if any) have been released.';
            } else {
                $errorMessage = 'Booking not found or you do not have permission to cancel it.';
            }
        } catch (Exception $e) {
            $errorMessage = 'Error cancelling reservation: ' . $e->getMessage();
        }
    }

    // Get active bookings
    $activeBookings = $db->bookings->find([
        'user_id' => $userId,
        'status' => 'active'
    ]);

} catch (Exception $e) {
    $errorMessage = 'Database error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cancel Reservation - E-LODGE</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        :root {
            --primary: #561C24;
            --secondary: #6D2932;
            --accent: #C7B7A3;
            --light: #E8D8C4;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', sans-serif;
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            color: var(--light) !important;
            font-weight: 600;
        }

        .container-custom {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .card {
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: none;
        }

        .page-title {
            color: var(--primary);
            font-weight: 700;
            margin-bottom: 30px;
        }

        .btn-danger-custom {
            background-color: var(--primary);
            border: none;
            color: var(--light);
            font-weight: 600;
        }

        .btn-danger-custom:hover {
            background-color: var(--secondary);
            color: white;
        }

        .btn-primary-custom {
            background-color: var(--primary);
            border: none;
            color: var(--light);
            font-weight: 600;
        }

        .btn-primary-custom:hover {
            background-color: var(--secondary);
            color: white;
        }

        .booking-card {
            border: 2px solid var(--light);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s;
            background-color: white;
        }

        .booking-card:hover {
            border-color: var(--accent);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .form-label {
            color: var(--primary);
            font-weight: 600;
        }

        .warning-box {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
        }

        .warning-box h6 {
            color: #856404;
            font-weight: 700;
        }

        .warning-box ul {
            color: #856404;
        }

        .modal-header {
            background-color: var(--light);
            border-bottom: 2px solid var(--accent);
        }

        .modal-title {
            color: var(--primary);
        }

        .booking-info {
            margin-bottom: 10px;
            color: #555;
        }

        .booking-info strong {
            color: var(--primary);
        }

        .booking-info i {
            color: var(--accent);
            margin-right: 8px;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--accent);
            margin-bottom: 20px;
        }

        .empty-state h5 {
            color: var(--primary);
            margin-bottom: 15px;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark py-3">
    <div class="container">
        <a class="navbar-brand" href="guest_dashboard.php">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
    </div>
</nav>

<div class="container container-custom">
    <h2 class="page-title"><i class="bi bi-x-circle"></i> Cancel Reservation</h2>

    <?php if ($successMessage): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle"></i> <?= htmlspecialchars($successMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($errorMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="warning-box">
        <h6><i class="bi bi-exclamation-triangle"></i> Cancellation Policy</h6>
        <ul class="mb-0">
            <li>Cancellations made <strong>24 hours before check-in</strong> are eligible for full refund</li>
            <li>Cancellations made <strong>within 24 hours</strong> may incur a cancellation fee</li>
            <li><strong>No-shows</strong> will be charged the full amount</li>
            <li>Refunds will be processed within 5-7 business days</li>
        </ul>
    </div>

    <div class="card">
        <div class="card-body p-4">
            <h5 class="mb-4"><i class="bi bi-list-ul"></i> Your Active Reservations</h5>

            <?php
            $bookingCount = 0;
            foreach ($activeBookings as $booking):
                $bookingCount++;
                
                // Calculate days until check-in
                $checkInDate = new DateTime($booking['check_in']);
                $today = new DateTime();
                $daysUntil = $today->diff($checkInDate)->days;
                $isPastCheckIn = $checkInDate < $today;
            ?>
                <div class="booking-card">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5 class="mb-3"><i class="bi bi-door-open"></i> <?= htmlspecialchars($booking['room_number']) ?></h5>
                            
                            <div class="booking-info">
                                <i class="bi bi-calendar-check"></i>
                                <strong>Check-in:</strong> <?= htmlspecialchars($booking['check_in']) ?>
                            </div>
                            
                            <div class="booking-info">
                                <i class="bi bi-calendar-x"></i>
                                <strong>Check-out:</strong> <?= htmlspecialchars($booking['check_out']) ?>
                            </div>
                            
                            <?php if (isset($booking['parking_id'])): ?>
                                <div class="booking-info">
                                    <i class="bi bi-car-front"></i>
                                    <strong>Parking:</strong> Included
                                </div>
                            <?php endif; ?>
                            
                            <div class="booking-info">
                                <i class="bi bi-clock"></i>
                                <strong>Status:</strong> 
                                <?php if ($isPastCheckIn): ?>
                                    <span class="badge bg-success">Currently Checked In</span>
                                <?php else: ?>
                                    <span class="badge bg-info">Upcoming (<?= $daysUntil ?> days)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="col-md-4 text-end">
                            <button type="button" 
                                    class="btn btn-danger-custom" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#cancelModal<?= $booking['_id'] ?>">
                                <i class="bi bi-trash"></i> Cancel Booking
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Cancel Modal -->
                <div class="modal fade" id="cancelModal<?= $booking['_id'] ?>" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="bi bi-exclamation-triangle"></i> Confirm Cancellation
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST" action="">
                                <div class="modal-body">
                                    <input type="hidden" name="booking_id" value="<?= $booking['_id'] ?>">
                                    
                                    <div class="alert alert-warning">
                                        <i class="bi bi-info-circle"></i> Are you sure you want to cancel this reservation?
                                    </div>
                                    
                                    <p><strong>Booking Details:</strong></p>
                                    <ul>
                                        <li><strong>Room:</strong> <?= htmlspecialchars($booking['room_number']) ?></li>
                                        <li><strong>Check-in:</strong> <?= htmlspecialchars($booking['check_in']) ?></li>
                                        <li><strong>Check-out:</strong> <?= htmlspecialchars($booking['check_out']) ?></li>
                                    </ul>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">
                                            <i class="bi bi-chat-left-text"></i> Reason for Cancellation (Optional)
                                        </label>
                                        <textarea name="reason" 
                                                  class="form-control" 
                                                  rows="3" 
                                                  placeholder="Please tell us why you're cancelling (helps us improve our service)..."></textarea>
                                        <small class="text-muted">This information helps us serve you better</small>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                        <i class="bi bi-arrow-left"></i> Keep Booking
                                    </button>
                                    <button type="submit" class="btn btn-danger-custom">
                                        <i class="bi bi-check-circle"></i> Yes, Cancel Reservation
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if ($bookingCount === 0): ?>
                <div class="empty-state">
                    <i class="bi bi-calendar-x"></i>
                    <h5>No Active Reservations</h5>
                    <p class="text-muted mb-4">You don't have any active reservations to cancel at this time.</p>
                    <a href="book_room.php" class="btn btn-primary-custom">
                        <i class="bi bi-plus-circle"></i> Make a New Booking
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>