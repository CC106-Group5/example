<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guest') {
    header("Location: login.php");
    exit();
}

require_once 'config.php';

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

    // Get all bookings with sorting
    $allBookings = $db->bookings->find(
        ['user_id' => $userId],
        ['sort' => ['created_at' => -1]]
    );

} catch (Exception $e) {
    echo "Database error: " . htmlspecialchars($e->getMessage());
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Booking History - E-LODGE</title>
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
            max-width: 1100px;
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

        .booking-item {
            border: 2px solid var(--light);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }

        .booking-item:hover {
            border-color: var(--accent);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .status-active {
            background-color: #28a745;
            color: white;
        }

        .status-completed {
            background-color: var(--accent);
            color: var(--primary);
        }

        .status-cancelled {
            background-color: #dc3545;
            color: white;
        }

        .booking-detail {
            margin-bottom: 8px;
            color: #555;
        }

        .booking-detail strong {
            color: var(--primary);
        }

        .filter-tabs {
            margin-bottom: 30px;
        }

        .filter-tabs .btn {
            margin-right: 10px;
            margin-bottom: 10px;
        }

        .btn-filter {
            background-color: var(--light);
            border: 2px solid var(--accent);
            color: var(--primary);
            font-weight: 600;
        }

        .btn-filter:hover, .btn-filter.active {
            background-color: var(--primary);
            border-color: var(--primary);
            color: var(--light);
        }

        .no-bookings {
            text-align: center;
            padding: 40px;
            display: none;
        }

        .booking-summary {
            background: linear-gradient(135deg, var(--light), var(--accent));
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .summary-item {
            text-align: center;
        }

        .summary-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
        }

        .summary-label {
            color: var(--secondary);
            font-weight: 600;
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
    <h2 class="page-title"><i class="bi bi-clock-history"></i> Booking History</h2>

    <?php
    // Calculate statistics
    $totalCount = 0;
    $activeCount = 0;
    $completedCount = 0;
    $cancelledCount = 0;

    $bookingsArray = iterator_to_array($allBookings);
    $totalCount = count($bookingsArray);

    foreach ($bookingsArray as $booking) {
        $status = $booking['status'] ?? 'active';
        if ($status === 'active') $activeCount++;
        elseif ($status === 'completed') $completedCount++;
        elseif ($status === 'cancelled') $cancelledCount++;
    }
    ?>

    <!-- Summary Statistics -->
    <div class="booking-summary">
        <div class="row">
            <div class="col-md-3 summary-item">
                <div class="summary-number"><?= $totalCount ?></div>
                <div class="summary-label">Total Bookings</div>
            </div>
            <div class="col-md-3 summary-item">
                <div class="summary-number"><?= $activeCount ?></div>
                <div class="summary-label">Active</div>
            </div>
            <div class="col-md-3 summary-item">
                <div class="summary-number"><?= $completedCount ?></div>
                <div class="summary-label">Completed</div>
            </div>
            <div class="col-md-3 summary-item">
                <div class="summary-number"><?= $cancelledCount ?></div>
                <div class="summary-label">Cancelled</div>
            </div>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="filter-tabs">
        <button class="btn btn-filter active" onclick="filterBookings('all')">
            <i class="bi bi-list"></i> All Bookings
        </button>
        <button class="btn btn-filter" onclick="filterBookings('active')">
            <i class="bi bi-check-circle"></i> Active
        </button>
        <button class="btn btn-filter" onclick="filterBookings('completed')">
            <i class="bi bi-flag"></i> Completed
        </button>
        <button class="btn btn-filter" onclick="filterBookings('cancelled')">
            <i class="bi bi-x-circle"></i> Cancelled
        </button>
    </div>

    <div class="card">
        <div class="card-body p-4">
            <div id="bookings-container">
                <?php
                $bookingCount = 0;
                foreach ($bookingsArray as $booking):
                    $bookingCount++;
                    $status = $booking['status'] ?? 'active';
                    $statusClass = 'status-' . $status;
                    $statusIcon = $status === 'active' ? 'check-circle' : ($status === 'completed' ? 'flag' : 'x-circle');
                ?>
                    <div class="booking-item" data-status="<?= htmlspecialchars($status) ?>">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h5 class="mb-3">
                                    <i class="bi bi-door-open"></i> <?= htmlspecialchars($booking['room_number']) ?>
                                </h5>
                                <div class="booking-detail">
                                    <i class="bi bi-calendar-check"></i> 
                                    <strong>Check-in:</strong> <?= htmlspecialchars($booking['check_in']) ?>
                                </div>
                                <div class="booking-detail">
                                    <i class="bi bi-calendar-x"></i> 
                                    <strong>Check-out:</strong> <?= htmlspecialchars($booking['check_out']) ?>
                                </div>
                                <?php if (isset($booking['parking_id'])): ?>
                                    <div class="booking-detail">
                                        <i class="bi bi-car-front"></i> 
                                        <strong>Parking:</strong> Included
                                    </div>
                                <?php endif; ?>
                                <div class="booking-detail">
                                    <i class="bi bi-calendar-plus"></i> 
                                    <strong>Booked on:</strong> 
                                    <?php 
                                        if (isset($booking['created_at'])) {
                                            $timestamp = $booking['created_at']->toDateTime();
                                            echo $timestamp->format('F j, Y g:i A');
                                        } else {
                                            echo 'N/A';
                                        }
                                    ?>
                                </div>
                                <?php if ($status === 'cancelled' && isset($booking['cancellation_reason'])): ?>
                                    <div class="booking-detail text-danger mt-2">
                                        <i class="bi bi-info-circle"></i> 
                                        <strong>Cancellation Reason:</strong> <?= htmlspecialchars($booking['cancellation_reason']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4 text-end">
                                <span class="status-badge <?= $statusClass ?>">
                                    <i class="bi bi-<?= $statusIcon ?>"></i> 
                                    <?= ucfirst($status) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- No Bookings Message -->
            <div class="no-bookings" id="no-bookings-message">
                <i class="bi bi-inbox" style="font-size: 4rem; color: var(--accent);"></i>
                <h5 class="mt-3" style="color: var(--primary);">No bookings found</h5>
                <p class="text-muted">Try selecting a different filter</p>
            </div>

            <?php if ($bookingCount === 0): ?>
                <div class="alert alert-info text-center">
                    <i class="bi bi-info-circle"></i> You haven't made any bookings yet.
                    <br><br>
                    <a href="book_room.php" class="btn btn-filter">
                        <i class="bi bi-plus-circle"></i> Make Your First Booking
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function filterBookings(status) {
    const bookings = document.querySelectorAll('.booking-item');
    const buttons = document.querySelectorAll('.btn-filter');
    const noBookingsMsg = document.getElementById('no-bookings-message');
    
    // Update active button
    buttons.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    let visibleCount = 0;
    
    // Filter bookings
    bookings.forEach(booking => {
        if (status === 'all' || booking.dataset.status === status) {
            booking.style.display = 'block';
            visibleCount++;
        } else {
            booking.style.display = 'none';
        }
    });
    
    // Show/hide no bookings message
    if (visibleCount === 0 && bookings.length > 0) {
        noBookingsMsg.style.display = 'block';
    } else {
        noBookingsMsg.style.display = 'none';
    }
}
</script>
</body>
</html>