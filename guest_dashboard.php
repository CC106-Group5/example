<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guest') {
    header("Location: login.php");
    exit();
}

require_once 'config.php';
require 'vendor/autoload.php';

try {
    $client = new MongoDB\Client($mongoUri);
    $db = $client->elodge_db;
    $userId = $_SESSION['user_id'];

    $user = $db->users->findOne(['_id' => new MongoDB\BSON\ObjectId($userId)]);
    $userName = $user['fullname'] ?? 'Guest';
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Guest Dashboard | eLodge</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    body {
        font-family: 'Poppins', sans-serif;
        background-color: #f8f9fa;
    }
    /* Header */
    .navbar {
        background-color: #003580;
    }
    .navbar-brand, .nav-link, .navbar-text {
        color: #fff !important;
        font-weight: 500;
    }
    .nav-link:hover {
        text-decoration: underline;
    }
    /* Hero Banner */
    .hero {
        background: url('https://cf.bstatic.com/xdata/images/hotel/max1024x768/274636644.jpg?k=9c6b6d9b36b7d66c41b6f6746c3aaf50477500e7c33228eb57b9c4a5ad760b7c&o=&hp=1') center/cover no-repeat;
        color: white;
        padding: 120px 20px;
        text-align: center;
        position: relative;
    }
    .hero::after {
        content: "";
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0, 0, 0, 0.5);
    }
    .hero h1, .hero p {
        position: relative;
        z-index: 2;
    }
    /* Search Bar */
    .search-bar {
        background: white;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        border-radius: 10px;
        padding: 20px;
        margin-top: -50px;
        position: relative;
        z-index: 3;
    }
    .search-bar input, .search-bar select {
        border-radius: 8px;
    }
    .btn-primary {
        background-color: #0071c2;
        border: none;
    }
    .btn-primary:hover {
        background-color: #005fa3;
    }
</style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark px-4 py-3">
  <a class="navbar-brand" href="#">eLodge</a>
  <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
    <span class="navbar-toggler-icon"></span>
  </button>
  <div class="collapse navbar-collapse" id="navbarNav">
    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
      <li class="nav-item"><a class="nav-link" href="#">Book Room</a></li>
      <li class="nav-item"><a class="nav-link" href="#">My Reservations</a></li>
      <li class="nav-item"><a class="nav-link" href="#">Attractions</a></li>
    </ul>
    <span class="navbar-text me-3">Welcome, <?= htmlspecialchars($userName) ?></span>
    <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
  </div>
</nav>

<!-- Hero Banner -->
<section class="hero">
  <div class="container">
    <h1 class="fw-bold">Up to 15% off on your next stay!</h1>
    <p>Explore amazing destinations and book your perfect getaway.</p>
  </div>
</section>

<!-- Search Bar -->
<div class="container search-bar mt-4">
  <form class="row g-3">
    <div class="col-md-4">
      <label class="form-label"><i class="fa fa-map-marker-alt me-2"></i>Destination</label>
      <input type="text" class="form-control" placeholder="Where are you going?">
    </div>
    <div class="col-md-3">
      <label class="form-label"><i class="fa fa-calendar me-2"></i>Check-in</label>
      <input type="date" class="form-control">
    </div>
    <div class="col-md-3">
      <label class="form-label"><i class="fa fa-calendar me-2"></i>Check-out</label>
      <input type="date" class="form-control">
    </div>
    <div class="col-md-2">
      <label class="form-label"><i class="fa fa-user me-2"></i>Guests</label>
      <select class="form-select">
        <option>1 Adult</option>
        <option>2 Adults</option>
        <option>3 Adults</option>
        <option>4+ Adults</option>
      </select>
    </div>
    <div class="col-12 text-center mt-3">
      <button type="submit" class="btn btn-primary px-5 py-2"><i class="fa fa-search me-2"></i>Search</button>
    </div>
  </form>
</div>

<!-- Footer -->
<footer class="text-center mt-5 py-4 bg-light">
  <p class="mb-0 text-muted">© <?= date("Y") ?> eLodge. All rights reserved.</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
