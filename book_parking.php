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
    $errorMessage = 'Database error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Book Parking - E-LODGE</title>
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
            max-width: 800px;
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

        .parking-option {
            border: 2px solid var(--light);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .parking-option:hover {
            border-color: var(--accent);
            background-color: var(--light);
        }

        .parking-option input[type="radio"] {
            margin-right: 10px;
        }

        .form-label {
            color: var(--primary);
            font-weight: 600;
        }

        .parking-icon {
            font-size: 2rem;
            color: var(--primary);
        }

        .info-box {
            background-color: var(--light);
            border-left: 4px solid var(--primary);
            padding: 15px;
            border-radius: 8px;
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
    <h2 class="page-title"><i class="bi bi-car-front"></i> Reserve Parking Slot</h2>

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

    <div class="info-box mb-4">
        <h6><i class="bi bi-info-circle"></i> Parking Information</h6>
        <ul class="mb-0">
            <li>24/7 secured parking area with CCTV surveillance</li>
            <li>Covered parking available</li>
            <li>Easy access to hotel entrance</li>
            <li>Valid plate number required</li>
        </ul>
    </div>

    <div class="card">
        <div class="card-body p-4">
            <form method="POST" action="">
                <div class="mb-4">
                    <label class="form-label"><i class="bi bi-p-square"></i> Select Parking Slot</label>
                    <?php foreach ($availableParking as $slot): ?>
                        <div class="parking-option">
                            <input type="radio" 
                                   name="parking_id" 
                                   value="<?= $slot['_id'] ?>" 
                                   id="slot_<?= $slot['_id'] ?>" 
                                   <?= ($selectedParking && $selectedParking['_id'] == $slot['_id']) ? 'checked' : '' ?>
                                   required>
                            <label for="slot_<?= $slot['_id'] ?>" style="cursor: pointer; width: 100%;">
                                <span class="parking-icon"><i class="bi bi-p-square-fill"></i></span>
                                <strong>Slot #<?= htmlspecialchars($slot['slot_number']) ?></strong>
                                <span class="badge bg-success ms-2">Available</span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="mb-4">
                    <label class="form-label"><i class="bi bi-car-front-fill"></i> Vehicle Type</label>
                    <select name="vehicle_type" class="form-select" required>
                        <option value="">Select Vehicle Type</option>
                        <option value="sedan">Sedan</option>
                        <option value="suv">SUV</option>
                        <option value="van">Van</option>
                        <option value="pickup">Pickup Truck</option>
                        <option value="motorcycle">Motorcycle</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label"><i class="bi bi-card-text"></i> Plate Number</label>
                    <input type="text" 
                           name="plate_number" 
                           class="form-control" 
                           placeholder="ABC-1234" 
                           pattern="[A-Za-z0-9\-]+" 
                           required>
                    <small class="text-muted">Enter your vehicle's license plate number</small>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label"><i class="bi bi-calendar-check"></i> Start Date</label>
                        <input type="date" 
                               name="start_date" 
                               class="form-control" 
                               required 
                               min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="bi bi-calendar-x"></i> End Date</label>
                        <input type="date" 
                               name="end_date" 
                               class="form-control" 
                               required 
                               min="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary-custom">
                        <i class="bi bi-bookmark-check"></i> Confirm Reservation
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
<script>
// Ensure end date is after start date
document.querySelector('input[name="start_date"]').addEventListener('change', function() {
    document.querySelector('input[name="end_date"]').min = this.value;
});
</script>
</body>
</html>
        $userId = $userIdString;
    }

    $user = $db->users->findOne(['_id' => $userId]);

    // Get parking slot ID from URL
    $parkingId = $_GET['id'] ?? null;
    $selectedParking = null;

    if ($parkingId) {
        try {
            $parkingObjectId = new MongoDB\BSON\ObjectId($parkingId);
            $selectedParking = $db->parking->findOne(['_id' => $parkingObjectId]);
        } catch (Exception $e) {
            $errorMessage = 'Invalid parking slot ID.';
        }
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $parkingSlotId = $_POST['parking_id'];
        $vehicleType = $_POST['vehicle_type'];
        $plateNumber = $_POST['plate_number'];
        $startDate = $_POST['start_date'];
        $endDate = $_POST['end_date'];

        try {
            $parkingObjectId = new MongoDB\BSON\ObjectId($parkingSlotId);
            $parking = $db->parking->findOne(['_id' => $parkingObjectId]);

            if ($parking && $parking['status'] === 'available') {
                // Create parking reservation
                $reservationData = [
                    'user_id' => $userId,
                    'parking_id' => $parkingObjectId,
                    'slot_number' => $parking['slot_number'],
                    'vehicle_type' => $vehicleType,
                    'plate_number' => strtoupper($plateNumber),
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'status' => 'active',
                    'created_at' => new MongoDB\BSON\UTCDateTime()
                ];

                $db->parking_reservations->insertOne($reservationData);
                $db->parking->updateOne(
                    ['_id' => $parkingObjectId],
                    ['$set' => ['status' => 'occupied']]
                );

                $successMessage = 'Parking slot reserved successfully!';
            } else {
                $errorMessage = 'Selected parking slot is not available.';
            }
        } catch (Exception $e) {
            $errorMessage = 'Error reserving parking: ' . $e->getMessage();
        }
    }

    // Get available parking slots
    $availableParking = $db->parking->find(['status' => 'available']);

} catch (Exception $e) {