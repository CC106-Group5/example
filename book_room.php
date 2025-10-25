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

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $roomId = $_POST['room_id'];
        $checkIn = $_POST['check_in'];
        $checkOut = $_POST['check_out'];
        $parkingId = $_POST['parking_id'] ?? null;

        try {
            $roomObjectId = new MongoDB\BSON\ObjectId($roomId);
            $room = $db->rooms->findOne(['_id' => $roomObjectId]);

            if ($room && $room['status'] === 'available') {
                // Create booking
                $bookingData = [
                    'user_id' => $userId,
                    'room_id' => $roomObjectId,
                    'room_number' => $room['name'],
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'status' => 'active',
                    'created_at' => new MongoDB\BSON\UTCDateTime()
                ];

                if ($parkingId) {
                    $parkingObjectId = new MongoDB\BSON\ObjectId($parkingId);
                    $bookingData['parking_id'] = $parkingObjectId;
                }

                $db->bookings->insertOne($bookingData);
                $db->rooms->updateOne(['_id' => $roomObjectId], ['$set' => ['status' => 'occupied']]);

                if ($parkingId) {
                    $db->parking->updateOne(['_id' => $parkingObjectId], ['$set' => ['status' => 'occupied']]);
                }

                $successMessage = 'Booking successfully created!';
            } else {
                $errorMessage = 'Selected room is not available.';
            }
        } catch (Exception $e) {
            $errorMessage = 'Error creating booking: ' . $e->getMessage();
        }
    }

    // Get available rooms and parking
    $availableRooms = $db->rooms->find(['status' => 'available']);
    $availableParking = $db->parking->find(['status' => 'available']);

} catch (Exception $e) {
    $errorMessage = 'Database error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Book Room - E-LODGE</title>
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

        .navbar-brand, .nav-link {
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

        .btn-secondary-custom {
            background-color: var(--accent);
            border: none;
            color: var(--primary);
            font-weight: 600;
        }

        .room-option {
            border: 2px solid var(--light);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .room-option:hover {
            border-color: var(--accent);
            background-color: var(--light);
        }

        .room-option input[type="radio"] {
            margin-right: 10px;
        }

        .form-label {
            color: var(--primary);
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
    <h2 class="page-title"><i class="bi bi-calendar-plus"></i> Book Room & Parking</h2>

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

    <div class="card">
        <div class="card-body p-4">
            <form method="POST" action="">
                <div class="mb-4">
                    <label class="form-label"><i class="bi bi-door-open"></i> Select Room</label>
                    <?php foreach ($availableRooms as $room): ?>
                        <div class="room-option">
                            <input type="radio" name="room_id" value="<?= $room['_id'] ?>" id="room_<?= $room['_id'] ?>" required>
                            <label for="room_<?= $room['_id'] ?>" style="cursor: pointer; width: 100%;">
                                <strong><?= htmlspecialchars($room['name']) ?></strong> - 
                                ₱<?= number_format($room['price'], 2) ?>/night
                                <br>
                                <small class="text-muted"><?= htmlspecialchars($room['description'] ?? 'Comfortable room') ?></small>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label"><i class="bi bi-calendar-check"></i> Check-in Date</label>
                        <input type="date" name="check_in" class="form-control" required min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="bi bi-calendar-x"></i> Check-out Date</label>
                        <input type="date" name="check_out" class="form-control" required min="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label"><i class="bi bi-car-front"></i> Select Parking (Optional)</label>
                    <select name="parking_id" class="form-select">
                        <option value="">No parking needed</option>
                        <?php foreach ($availableParking as $slot): ?>
                            <option value="<?= $slot['_id'] ?>">
                                Slot #<?= htmlspecialchars($slot['slot_number']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary-custom">
                        <i class="bi bi-check-circle"></i> Confirm Booking
                    </button>
                    <a href="guest_dashboard.php" class="btn btn-secondary-custom">
                        <i class="bi bi-x-circle"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>