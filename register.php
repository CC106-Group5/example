<?php
session_start();
require_once 'config.php';

$error_message = '';
$success_message = '';

try {
    $client = new MongoDB\Client($mongoUri);
    $db = $client->elodge_db;
    $usersCollection = $db->users;

    // ✅ Auto-remove expired pending accounts (older than 24 hrs)
    $currentTime = new MongoDB\BSON\UTCDateTime();
    $usersCollection->deleteMany([
        'status' => 'pending',
        'approval_deadline' => ['$lt' => $currentTime]
    ]);

    // ✅ Check if at least one admin exists
    $adminExists = $usersCollection->findOne(['role' => 'admin']);

} catch (Exception $e) {
    $error_message = "Database connection error: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    if (empty($email) || empty($password) || empty($confirm_password)) {
        $error_message = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } else {
        try {
            // ✅ Check if already registered or pending
            $existingUser = $usersCollection->findOne(['email' => $email]);
            if ($existingUser) {
                if ($existingUser['status'] === 'pending') {
                    $error_message = "Your registration is awaiting admin approval. Please check back later.";
                } else {
                    $error_message = "This email is already registered.";
                }
            } else {
                // ✅ Assign guest role and mark as pending
                $role = 'guest';
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $status = 'pending';
                $approvalDeadline = new MongoDB\BSON\UTCDateTime((time() + 86400) * 1000); // 24 hrs

                $usersCollection->insertOne([
                    'email' => $email,
                    'password' => $hashedPassword,
                    'role' => $role,
                    'status' => $status,
                    'created_at' => new MongoDB\BSON\UTCDateTime(),
                    'approval_deadline' => $approvalDeadline,
                    'notified' => false // For future admin notification
                ]);

                $success_message = "✅ Registration successful! Please wait for admin approval (within 24 hours).";
            }
        } catch (Exception $e) {
            $error_message = "Registration error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Adine Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        :root {
            --dark-brown: #3E1F1E;
            --coffee: #5E3023;
            --warm-cream: #F3E5D0;
            --light-ivory: #FFF8F0;
            --accent-gold: #C9A66B;
        }

        body {
            background: linear-gradient(145deg, var(--coffee), var(--dark-brown));
            font-family: 'Segoe UI', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .register-container {
            width: 100%;
            max-width: 420px;
            background-color: var(--light-ivory);
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.25);
            padding: 35px;
            border-top: 6px solid var(--accent-gold);
        }

        h2 {
            color: var(--coffee);
            font-weight: 700;
            text-align: center;
            margin-bottom: 25px;
        }

        .form-label {
            color: var(--dark-brown);
            font-weight: 600;
        }

        .form-control {
            border-radius: 10px;
            border: 1.5px solid #d9cbb3;
            padding: 10px 12px;
        }

        .form-control:focus {
            border-color: var(--accent-gold);
            box-shadow: 0 0 6px var(--accent-gold);
        }

        .btn-custom {
            background-color: var(--coffee);
            color: var(--warm-cream);
            border: none;
            border-radius: 10px;
            font-weight: 600;
            padding: 10px;
            transition: 0.3s;
        }

        .btn-custom:hover {
            background-color: var(--dark-brown);
        }

        .password-wrapper {
            position: relative;
        }

        .password-field {
            padding-right: 42px;
        }

        .toggle-password {
            position: absolute;
            top: 70%;
            right: 12px;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--coffee);
            font-size: 1.1rem;
        }

        .toggle-password:hover {
            color: var(--accent-gold);
        }

        .alert {
            font-size: 0.9rem;
            border-radius: 10px;
        }

        .link-login {
            text-align: center;
            margin-top: 20px;
        }

        .link-login a {
            color: var(--coffee);
            font-weight: 600;
            text-decoration: none;
        }

        .link-login a:hover {
            color: var(--accent-gold);
        }
    </style>
</head>

<body>
    <div class="register-container">
        <h2>Create Account</h2>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required>
            </div>

            <div class="mb-3 password-wrapper">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control password-field" required>
                <i class="bi bi-eye-slash toggle-password"></i>
            </div>

            <div class="mb-3 password-wrapper">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control password-field" required>
                <i class="bi bi-eye-slash toggle-password"></i>
            </div>

            <button type="submit" class="btn btn-custom w-100">Register</button>
        </form>

        <div class="link-login">
            <p>Already have an account? <a href="login.php">Login here</a></p>
        </div>
    </div>

    <script>
        document.querySelectorAll('.toggle-password').forEach(icon => {
            icon.addEventListener('click', () => {
                const input = icon.previousElementSibling;
                input.type = input.type === 'password' ? 'text' : 'password';
                icon.classList.toggle('bi-eye');
                icon.classList.toggle('bi-eye-slash');
            });
        });
    </script>
</body>
</html>
