<?php
session_start();

// Log the logout activity if user was logged in
if (isset($_SESSION['user_id'])) {
    try {
        require_once 'config.php';
        
        $client = new MongoDB\Client($mongoUri);
        $logs = $client->elodge_db->login_logs;
        
        // Log logout activity
        $logs->insertOne([
            'user_id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'] ?? 'Unknown',
            'action' => 'logout',
            'logout_time' => new MongoDB\BSON\UTCDateTime(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
        ]);
    } catch (Exception $e) {
        error_log("Logout logging error: " . $e->getMessage());
    }
}

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: login.php");
exit();
?>