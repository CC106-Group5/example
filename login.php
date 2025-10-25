<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $role = $_POST['role'] ?? '';

    try {
        $client = new MongoDB\Client($mongoUri);
        $db = $client->elodge_db;
        $users = $db->users;

        $user = $users->findOne(['email' => $email, 'role' => $role]);
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = (string)$user['_id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['username'] = $user['username'] ?? $user['email']; // Store username in session
            $_SESSION['email'] = $user['email']; // Store email in session

            switch ($role) {
                case 'admin': header("Location: admin_dashboard.php"); break;
                case 'receptionist': header("Location: receptionist_dashboard.php"); break;
                case 'guest': header("Location: guest_dashboard.php"); break;
                default: header("Location: index.php");
            }
            exit();
        } else {
            echo "<script>alert('Invalid login credentials'); window.history.back();</script>";
        }
    } catch (Exception $e) {
        echo "<script>alert('Database connection failed'); window.history.back();</script>";
    }
} else {
    header("Location: index.php");
    exit();
}
?>