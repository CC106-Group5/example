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

    // Handle file upload
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['id_file'])) {
        $file = $_FILES['id_file'];
        $idType = $_POST['id_type'];

        if ($file['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
            $maxSize = 5 * 1024 * 1024; // 5MB

            if (!in_array($file['type'], $allowedTypes)) {
                $errorMessage = 'Invalid file type. Only JPG, PNG, and PDF files are allowed.';
            } elseif ($file['size'] > $maxSize) {
                $errorMessage = 'File size exceeds 5MB limit.';
            } else {
                $uploadDir = 'uploads/ids/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $fileName = $userId . '_' . time() . '.' . $fileExtension;
                $filePath = $uploadDir . $fileName;

                if (move_uploaded_file($file['tmp_name'], $filePath)) {
                    $db->users->updateOne(
                        ['_id' => $userId],
                        ['$set' => [
                            'id_type' => $idType,
                            'id_file' => $filePath,
                            'id_verified' => false,
                            'id_uploaded_at' => new MongoDB\BSON\UTCDateTime()
                        ]]
                    );

                    $successMessage = 'ID uploaded successfully! Awaiting verification.';
                } else {
                    $errorMessage = 'Failed to upload file.';
                }
            }
        } else {
            $errorMessage = 'Error uploading file. Please try again.';
        }
    }

} catch (Exception $e) {
    $errorMessage = 'Database error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload Valid ID - E-LODGE</title>
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
            max-width: 700px;
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

        .upload-area {
            border: 3px dashed var(--accent);
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            background-color: var(--light);
            transition: all 0.3s;
        }

        .upload-area:hover {
            border-color: var(--primary);
            background-color: #fff;
        }

        .upload-icon {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 20px;
        }

        .form-label {
            color: var(--primary);
            font-weight: 600;
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
    <h2 class="page-title"><i class="bi bi-file-earmark-arrow-up"></i> Upload Valid ID</h2>

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

    <div class="card mb-4">
        <div class="card-body">
            <div class="info-box mb-4">
                <h6><i class="bi bi-info-circle"></i> Important Information</h6>
                <ul class="mb-0">
                    <li>Upload a clear photo or scan of your valid ID</li>
                    <li>Accepted formats: JPG, PNG, PDF</li>
                    <li>Maximum file size: 5MB</li>
                    <li>Your ID will be verified within 24-48 hours</li>
                </ul>
            </div>

            <?php if (isset($user['id_file']) && $user['id_file']): ?>
                <div class="alert alert-info">
                    <i class="bi bi-clock-history"></i> You have already uploaded an ID.
                    Status: <?= $user['id_verified'] ? '<span class="badge bg-success">Verified</span>' : '<span class="badge bg-warning">Pending Verification</span>' ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" enctype="multipart/form-data">
                <div class="mb-4">
                    <label class="form-label"><i class="bi bi-card-list"></i> ID Type</label>
                    <select name="id_type" class="form-select" required>
                        <option value="">Select ID Type</option>
                        <option value="passport">Passport</option>
                        <option value="drivers_license">Driver's License</option>
                        <option value="national_id">National ID / PhilSys</option>
                        <option value="voters_id">Voter's ID</option>
                        <option value="sss_id">SSS ID</option>
                        <option value="postal_id">Postal ID</option>
                        <option value="prc_id">PRC ID</option>
                        <option value="company_id">Company ID</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label"><i class="bi bi-file-image"></i> Upload ID File</label>
                    <div class="upload-area">
                        <div class="upload-icon"><i class="bi bi-cloud-arrow-up"></i></div>
                        <h5>Drop your file here or click to browse</h5>
                        <p class="text-muted">JPG, PNG or PDF (Max 5MB)</p>
                        <input type="file" name="id_file" class="form-control mt-3" accept=".jpg,.jpeg,.png,.pdf" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary-custom btn-lg w-100">
                    <i class="bi bi-upload"></i> Upload ID
                </button>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>