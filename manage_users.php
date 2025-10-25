<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'config.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

$message = '';
$error = '';

try {
    $client = new MongoDB\Client($mongoUri);
    $db = $client->elodge_db;
    $usersCollection = $db->users;

    // ✅ Approve user
    if (isset($_GET['approve'])) {
        $userId = new ObjectId($_GET['approve']);
        $usersCollection->updateOne(['_id' => $userId], ['$set' => ['status' => 'approved', 'updated_at' => new UTCDateTime()]]);
        $message = "User approved successfully.";
    }

    // ✅ Delete user
    if (isset($_GET['delete'])) {
        $userId = new ObjectId($_GET['delete']);
        $usersCollection->deleteOne(['_id' => $userId]);
        $message = "User deleted successfully.";
    }

    // ✅ Add new user manually
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $role = $_POST['role'];

        if ($username && $email && $password) {
            $usersCollection->insertOne([
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'role' => $role,
                'status' => 'approved',
                'created_at' => new UTCDateTime()
            ]);
            $message = "User added successfully.";
        } else {
            $error = "Please fill out all required fields.";
        }
    }

    // ✅ Update user role
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
        $userId = new ObjectId($_POST['user_id']);
        $newRole = $_POST['role'];
        $usersCollection->updateOne(['_id' => $userId], ['$set' => ['role' => $newRole, 'updated_at' => new UTCDateTime()]]);
        $message = "User role updated successfully.";
    }

    // ✅ Fetch all users
    $users = $usersCollection->find([], ['sort' => ['created_at' => -1]]);
    $pendingCount = $usersCollection->countDocuments(['status' => 'pending']);
} catch (Exception $e) {
    $error = "Database Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Users - Adine Hotel</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
:root {
    --urban-espresso: #5B4A3E;
    --pavement-shadow: #8A8077;
    --luxe-oat: #CBBFAF;
    --ivory-silk: #E8DED4;
    --sunlit-veil: #F6F2EB;
}
body {
    background-color: var(--sunlit-veil);
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    overflow-x: hidden;
}
.navbar {
    background-color: var(--urban-espresso);
}
.navbar-brand, .navbar-nav .nav-link, .navbar-text {
    color: #fff !important;
}
.offcanvas {
    background-color: var(--urban-espresso);
    color: white;
}
.offcanvas .nav-link {
    color: var(--ivory-silk);
    padding: 10px 15px;
    border-radius: 6px;
}
.offcanvas .nav-link.active,
.offcanvas .nav-link:hover {
    background-color: var(--pavement-shadow);
    color: #fff;
}
.table-container {
    background: #fff;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 3px 8px rgba(0,0,0,0.05);
}
.btn-primary {
    background-color: var(--urban-espresso);
    border: none;
}
.btn-primary:hover {
    background-color: var(--pavement-shadow);
}
.badge-approved {
    background: #4CAF50;
}
.badge-pending {
    background: #FFC107;
}
</style>
</head>

<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark sticky-top">
  <div class="container-fluid">
    <button class="btn btn-outline-light me-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar">☰</button>
    <a class="navbar-brand fw-bold" href="#">User Management</a>
    <div class="ms-auto text-light">
        <a href="logout.php" class="btn btn-outline-light">Logout</a>
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
      <a href="manage_users.php" class="nav-link active">
        👥 Users
        <?php if ($pendingCount > 0): ?>
          <span class="badge bg-warning ms-2"><?= $pendingCount ?></span>
        <?php endif; ?>
      </a>
      <a href="add_update_rooms.php" class="nav-link">🏨 Rooms & Parking</a>
      <a href="view_all_bookings.php" class="nav-link">📅 Bookings</a>
      <a href="generate_reports.php" class="nav-link">📈 Reports</a>
    </nav>
  </div>
</div>

<!-- Main Content -->
<div class="container py-5">
    <h2 class="fw-bold mb-4 text-center">Manage Users</h2>

    <?php if ($message): ?>
      <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Add User Button -->
    <div class="text-end mb-3">
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">+ Add User</button>
    </div>

    <!-- User Table -->
    <div class="table-container table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= htmlspecialchars($user['username']) ?></td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td><?= htmlspecialchars($user['role']) ?></td>
                    <td>
                        <span class="badge <?= $user['status'] === 'approved' ? 'badge-approved' : 'badge-pending' ?>">
                            <?= ucfirst($user['status'] ?? 'pending') ?>
                        </span>
                    </td>
                    <td>
                        <div class="d-flex gap-2">
                            <?php if (($user['status'] ?? 'pending') === 'pending'): ?>
                                <a href="?approve=<?= $user['_id'] ?>" class="btn btn-success btn-sm">Approve</a>
                            <?php endif; ?>
                            <?php if ((string)$user['_id'] !== $_SESSION['user_id']): ?>
                                <a href="?delete=<?= $user['_id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?')">Delete</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add New User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label>Username</label>
          <input type="text" name="username" class="form-control" required>
        </div>
        <div class="mb-3">
          <label>Email</label>
          <input type="email" name="email" class="form-control" required>
        </div>
        <div class="mb-3">
          <label>Password</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <div class="mb-3">
          <label>Role</label>
          <select name="role" class="form-select">
            <option value="guest">Guest</option>
            <option value="staff">Staff</option>
            <option value="receptionist">Receptionist</option>
            <option value="admin">Admin</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
