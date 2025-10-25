<?php
session_start();
require_once 'config.php';

use MongoDB\Client;

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    if (empty($username) || empty($new_password) || empty($confirm_password)) {
        $error_message = "All fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } else {
        try {
            $client = new Client($mongoUri);
            $users = $client->elodge_db->users;

            // Find the user
            $user = $users->findOne(['username' => $username]);
            if (!$user) {
                $error_message = "User not found.";
            } elseif ($user['role'] === 'admin') {
                // Restrict Admin password reset through this page
                $error_message = "Admin password resets are not allowed here.";
            } elseif (in_array($user['role'], ['guest', 'receptionist'])) {
                // Update password for guest/receptionist
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $users->updateOne(
                    ['_id' => $user['_id']],
                    ['$set' => ['password' => $hashed]]
                );
                $success_message = "Password reset successful! You can now log in.";
            } else {
                $error_message = "This role cannot reset passwords here.";
            }
        } catch (Exception $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password - Adine Hotel</title>
  <link rel="stylesheet" 
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
      body {
          font-family: 'Segoe UI', sans-serif;
          background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)),
                      url('hey.png') center/cover no-repeat;
          height: 100vh;
          display: flex;
          align-items: center;
          justify-content: center;
      }
      .forgot-box {
          background: #E8D8C4;
          border: 2px solid #C7B7A3;
          border-radius: 16px;
          padding: 40px;
          width: 380px;
          box-shadow: 0 8px 20px rgba(0,0,0,0.3);
      }
      h2 {
          text-align: center;
          color: #561C24;
          margin-bottom: 25px;
      }
      label {
          color: #561C24;
          font-weight: 600;
      }
      input {
          width: 100%;
          padding: 12px;
          margin-bottom: 18px;
          border: 2px solid #C7B7A3;
          border-radius: 8px;
          font-size: 1em;
      }
      input:focus {
          border-color: #6D2932;
          outline: none;
      }
      .btn {
          width: 100%;
          background: linear-gradient(135deg, #6D2932, #561C24);
          color: #E8D8C4;
          padding: 12px;
          border: none;
          border-radius: 8px;
          font-weight: 600;
          cursor: pointer;
      }
      .btn:hover {
          background: linear-gradient(135deg, #561C24, #6D2932);
      }
      .message {
          text-align: center;
          margin-bottom: 15px;
          font-weight: 500;
      }
      .error { color: #6D2932; background: #F8D7DA; padding: 10px; border-radius: 6px; }
      .success { color: #561C24; background: #D4EDDA; padding: 10px; border-radius: 6px; }
      .back {
          text-align: center;
          margin-top: 10px;
      }
      .back a {
          color: #6D2932;
          text-decoration: none;
          font-weight: 600;
      }
      .back a:hover {
          color: #561C24;
      }
  </style>
</head>
<body>
  <div class="forgot-box">
      <h2>Forgot Password</h2>
      <?php if ($error_message): ?>
          <div class="message error"><?= htmlspecialchars($error_message) ?></div>
      <?php endif; ?>
      <?php if ($success_message): ?>
          <div class="message success"><?= htmlspecialchars($success_message) ?></div>
      <?php endif; ?>
      <form method="POST">
          <label>Username</label>
          <input type="text" name="username" required placeholder="Enter your username">

          <label>New Password</label>
          <input type="password" name="new_password" required placeholder="Enter new password">

          <label>Confirm Password</label>
          <input type="password" name="confirm_password" required placeholder="Confirm new password">

          <button type="submit" class="btn">Reset Password</button>
      </form>
      <div class="back">
          <a href="login.php">Back to Login</a>
      </div>
  </div>
</body>
</html>
