<?php 
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'config.php';

$message = '';
$messageType = '';

try {
    $client = new MongoDB\Client($mongoUri);
    $db = $client->elodge_db;
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'update_rates':
                    // Update room rates
                    $rateUpdates = [
                        'standard_rate' => floatval($_POST['standard_rate']),
                        'deluxe_rate' => floatval($_POST['deluxe_rate']),
                        'suite_rate' => floatval($_POST['suite_rate']),
                        'parking_rate' => floatval($_POST['parking_rate']),
                        'updated_at' => new MongoDB\BSON\UTCDateTime()
                    ];
                    
                    $db->system_settings->updateOne(
                        ['type' => 'rates'],
                        ['$set' => $rateUpdates],
                        ['upsert' => true]
                    );
                    
                    $message = 'Room rates updated successfully!';
                    $messageType = 'success';
                    break;
                    
                case 'update_system':
                    // Update system parameters
                    $systemUpdates = [
                        'hotel_name' => $_POST['hotel_name'],
                        'hotel_email' => $_POST['hotel_email'],
                        'hotel_phone' => $_POST['hotel_phone'],
                        'hotel_address' => $_POST['hotel_address'],
                        'check_in_time' => $_POST['check_in_time'],
                        'check_out_time' => $_POST['check_out_time'],
                        'cancellation_hours' => intval($_POST['cancellation_hours']),
                        'advance_booking_days' => intval($_POST['advance_booking_days']),
                        'updated_at' => new MongoDB\BSON\UTCDateTime()
                    ];
                    
                    $db->system_settings->updateOne(
                        ['type' => 'system'],
                        ['$set' => $systemUpdates],
                        ['upsert' => true]
                    );
                    
                    $message = 'System settings updated successfully!';
                    $messageType = 'success';
                    break;
                    
                case 'update_payment':
                    // Update payment settings
                    $paymentUpdates = [
                        'tax_rate' => floatval($_POST['tax_rate']),
                        'service_charge' => floatval($_POST['service_charge']),
                        'deposit_percentage' => floatval($_POST['deposit_percentage']),
                        'currency' => $_POST['currency'],
                        'payment_methods' => isset($_POST['payment_methods']) ? $_POST['payment_methods'] : [],
                        'updated_at' => new MongoDB\BSON\UTCDateTime()
                    ];
                    
                    $db->system_settings->updateOne(
                        ['type' => 'payment'],
                        ['$set' => $paymentUpdates],
                        ['upsert' => true]
                    );
                    
                    $message = 'Payment settings updated successfully!';
                    $messageType = 'success';
                    break;
            }
        }
    }
    
    // Fetch current settings
    $rateSettings = $db->system_settings->findOne(['type' => 'rates']) ?? [
        'standard_rate' => 1500.00,
        'deluxe_rate' => 2500.00,
        'suite_rate' => 4000.00,
        'parking_rate' => 100.00
    ];
    
    $systemSettings = $db->system_settings->findOne(['type' => 'system']) ?? [
        'hotel_name' => 'Adine Hotel',
        'hotel_email' => 'info@adinehotel.com',
        'hotel_phone' => '+63 123 456 7890',
        'hotel_address' => 'Quezon City, Metro Manila, Philippines',
        'check_in_time' => '14:00',
        'check_out_time' => '12:00',
        'cancellation_hours' => 24,
        'advance_booking_days' => 90
    ];
    
    $paymentSettings = $db->system_settings->findOne(['type' => 'payment']) ?? [
        'tax_rate' => 12.00,
        'service_charge' => 10.00,
        'deposit_percentage' => 50.00,
        'currency' => 'PHP',
        'payment_methods' => ['cash', 'credit_card', 'debit_card']
    ];
    
} catch (Exception $e) {
    $message = 'Error: ' . $e->getMessage();
    $messageType = 'error';
    error_log("Settings Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - E-LODGE</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #E8D8C4;
            color: #561C24;
            min-height: 100vh;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            background: #561C24;
            padding: 0;
            z-index: 100;
            box-shadow: 4px 0 24px rgba(86, 28, 36, 0.1);
        }

        .sidebar-header {
            padding: 40px 30px;
            border-bottom: 1px solid rgba(199, 183, 163, 0.1);
        }

        .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .logo-small {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            border: 3px solid #C7B7A3;
            box-shadow: 0 4px 12px rgba(199, 183, 163, 0.3);
        }

        .brand-name {
            font-size: 1.1em;
            font-weight: 400;
            color: #E8D8C4;
            text-align: center;
            letter-spacing: 3px;
            text-transform: uppercase;
        }

        .nav-menu {
            padding: 30px 20px;
        }

        .nav-section {
            margin-bottom: 35px;
        }

        .nav-section-title {
            font-size: 0.65em;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #C7B7A3;
            padding: 0 15px;
            margin-bottom: 12px;
            font-weight: 600;
            opacity: 0.7;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 14px 15px;
            color: #C7B7A3;
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            margin-bottom: 6px;
            font-size: 0.95em;
            font-weight: 500;
        }

        .nav-item:hover {
            background: rgba(199, 183, 163, 0.1);
            color: #E8D8C4;
            transform: translateX(5px);
        }

        .nav-item.active {
            background: #6D2932;
            color: #E8D8C4;
            box-shadow: 0 4px 12px rgba(109, 41, 50, 0.3);
        }

        .nav-icon {
            font-size: 1.2em;
            width: 24px;
            text-align: center;
        }

        .main-content {
            margin-left: 280px;
            min-height: 100vh;
        }

        .top-bar {
            background: #FFFFFF;
            border-bottom: 1px solid rgba(86, 28, 36, 0.08);
            padding: 25px 50px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 50;
            box-shadow: 0 2px 12px rgba(86, 28, 36, 0.03);
        }

        .page-title {
            font-size: 1.8em;
            font-weight: 300;
            color: #561C24;
            letter-spacing: 0.5px;
        }

        .user-section {
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 18px;
            background: #E8D8C4;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .user-profile:hover {
            background: #C7B7A3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(86, 28, 36, 0.15);
        }

        .user-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg, #561C24, #6D2932);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.85em;
            color: #E8D8C4;
        }

        .user-details {
            text-align: left;
        }

        .user-name {
            font-size: 0.9em;
            font-weight: 600;
            color: #561C24;
        }

        .user-role {
            font-size: 0.7em;
            color: #6D2932;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .logout-btn {
            background: transparent;
            color: #561C24;
            padding: 10px 24px;
            border: 1.5px solid #C7B7A3;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9em;
        }

        .logout-btn:hover {
            background: #561C24;
            border-color: #561C24;
            color: #E8D8C4;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(86, 28, 36, 0.2);
        }

        .settings-content {
            padding: 50px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .alert {
            padding: 18px 24px;
            border-radius: 12px;
            margin-bottom: 30px;
            font-weight: 500;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: rgba(109, 41, 50, 0.1);
            color: #561C24;
            border-left: 4px solid #6D2932;
        }

        .alert-error {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border-left: 4px solid #dc3545;
        }

        .settings-grid {
            display: grid;
            gap: 30px;
        }

        .settings-card {
            background: #FFFFFF;
            border-radius: 20px;
            padding: 40px;
            border: 1px solid rgba(86, 28, 36, 0.08);
            box-shadow: 0 2px 12px rgba(86, 28, 36, 0.04);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #E8D8C4;
        }

        .card-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #561C24, #6D2932);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5em;
        }

        .card-title {
            font-size: 1.5em;
            font-weight: 600;
            color: #561C24;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 25px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-label {
            font-weight: 600;
            color: #561C24;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-input,
        .form-select,
        .form-textarea {
            padding: 14px 18px;
            border: 2px solid #E8D8C4;
            border-radius: 10px;
            font-size: 1em;
            font-family: inherit;
            transition: all 0.3s ease;
            background: #FFFFFF;
            color: #561C24;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: #6D2932;
            box-shadow: 0 0 0 3px rgba(109, 41, 50, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            padding: 15px;
            background: #E8D8C4;
            border-radius: 10px;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .checkbox-item input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: #561C24;
        }

        .checkbox-item label {
            font-size: 0.95em;
            color: #561C24;
            cursor: pointer;
            font-weight: 500;
        }

        .btn-primary {
            background: linear-gradient(135deg, #561C24, #6D2932);
            color: #E8D8C4;
            padding: 16px 40px;
            border: none;
            border-radius: 12px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(86, 28, 36, 0.3);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .info-box {
            background: #E8D8C4;
            padding: 20px;
            border-radius: 12px;
            border-left: 4px solid #6D2932;
            margin-bottom: 25px;
        }

        .info-box p {
            color: #561C24;
            font-size: 0.9em;
            line-height: 1.6;
        }

        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
            }
        }

        @media (max-width: 768px) {
            .settings-content {
                padding: 30px 25px;
            }

            .settings-card {
                padding: 25px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .user-details {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo-container">
                <img src="logos.png" alt="Logo" class="logo-small">
            </div>
            <div class="brand-name">Adine Hotel</div>
        </div>

        <nav class="nav-menu">
            <div class="nav-section">
                <div class="nav-section-title">Main</div>
                <a href="admin_dashboard.php" class="nav-item">
                    <span class="nav-icon">📊</span>
                    <span>Dashboard</span>
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Management</div>
                <a href="manage_users.php" class="nav-item">
                    <span class="nav-icon">👥</span>
                    <span>Users</span>
                </a>
                <a href="add_update_rooms.php" class="nav-item">
                    <span class="nav-icon">🏨</span>
                    <span>Rooms & Parking</span>
                </a>
                <a href="view_all_bookings.php" class="nav-item">
                    <span class="nav-icon">📅</span>
                    <span>Bookings</span>
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Analytics</div>
                <a href="generate_reports.php" class="nav-item">
                    <span class="nav-icon">📈</span>
                    <span>Reports</span>
                </a>
                <a href="dashboard_analytics.php" class="nav-item">
                    <span class="nav-icon">📉</span>
                    <span>Analytics</span>
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">System</div>
                <a href="system_settings.php" class="nav-item active">
                    <span class="nav-icon">⚙️</span>
                    <span>Settings</span>
                </a>
            </div>
        </nav>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <div class="page-title">System Settings</div>
            <div class="user-section">
                <div class="user-profile">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['username'], 0, 2)); ?>
                    </div>
                    <div class="user-details">
                        <div class="user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                        <div class="user-role">Administrator</div>
                    </div>
                </div>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>

        <div class="settings-content">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="settings-grid">
                <!-- Room Rates Settings -->
                <div class="settings-card">
                    <div class="card-header">
                        <div class="card-icon">💰</div>
                        <div class="card-title">Room Rates & Pricing</div>
                    </div>

                    <div class="info-box">
                        <p>Configure the base rates for different room types and parking facilities. These rates will be used for all new bookings.</p>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="action" value="update_rates">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Standard Room Rate (₱/night)</label>
                                <input type="number" name="standard_rate" class="form-input" 
                                       value="<?php echo $rateSettings['standard_rate']; ?>" 
                                       step="0.01" min="0" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Deluxe Room Rate (₱/night)</label>
                                <input type="number" name="deluxe_rate" class="form-input" 
                                       value="<?php echo $rateSettings['deluxe_rate']; ?>" 
                                       step="0.01" min="0" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Suite Rate (₱/night)</label>
                                <input type="number" name="suite_rate" class="form-input" 
                                       value="<?php echo $rateSettings['suite_rate']; ?>" 
                                       step="0.01" min="0" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Parking Rate (₱/day)</label>
                                <input type="number" name="parking_rate" class="form-input" 
                                       value="<?php echo $rateSettings['parking_rate']; ?>" 
                                       step="0.01" min="0" required>
                            </div>
                        </div>

                        <button type="submit" class="btn-primary">Update Rates</button>
                    </form>
                </div>

                <!-- System Parameters -->
                <div class="settings-card">
                    <div class="card-header">
                        <div class="card-icon">⚙️</div>
                        <div class="card-title">Hotel Information & Policies</div>
                    </div>

                    <div class="info-box">
                        <p>Manage hotel details, check-in/out times, and booking policies.</p>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="action" value="update_system">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Hotel Name</label>
                                <input type="text" name="hotel_name" class="form-input" 
                                       value="<?php echo htmlspecialchars($systemSettings['hotel_name']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Hotel Email</label>
                                <input type="email" name="hotel_email" class="form-input" 
                                       value="<?php echo htmlspecialchars($systemSettings['hotel_email']); ?>" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Hotel Phone</label>
                                <input type="tel" name="hotel_phone" class="form-input" 
                                       value="<?php echo htmlspecialchars($systemSettings['hotel_phone']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Check-in Time</label>
                                <input type="time" name="check_in_time" class="form-input" 
                                       value="<?php echo $systemSettings['check_in_time']; ?>" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Check-out Time</label>
                                <input type="time" name="check_out_time" class="form-input" 
                                       value="<?php echo $systemSettings['check_out_time']; ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Cancellation Policy (Hours)</label>
                                <input type="number" name="cancellation_hours" class="form-input" 
                                       value="<?php echo $systemSettings['cancellation_hours']; ?>" 
                                       min="0" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Advance Booking Days</label>
                                <input type="number" name="advance_booking_days" class="form-input" 
                                       value="<?php echo $systemSettings['advance_booking_days']; ?>" 
                                       min="1" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Hotel Address</label>
                            <textarea name="hotel_address" class="form-textarea" required><?php echo htmlspecialchars($systemSettings['hotel_address']); ?></textarea>
                        </div>

                        <button type="submit" class="btn-primary">Update System Settings</button>
                    </form>
                </div>

                <!-- Payment Settings -->
                <div class="settings-card">
                    <div class="card-header">
                        <div class="card-icon">💳</div>
                        <div class="card-title">Payment & Billing Settings</div>
                    </div>

                    <div class="info-box">
                        <p>Configure tax rates, service charges, and accepted payment methods.</p>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="action" value="update_payment">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Tax Rate (%)</label>
                                <input type="number" name="tax_rate" class="form-input" 
                                       value="<?php echo $paymentSettings['tax_rate']; ?>" 
                                       step="0.01" min="0" max="100" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Service Charge (%)</label>
                                <input type="number" name="service_charge" class="form-input" 
                                       value="<?php echo $paymentSettings['service_charge']; ?>" 
                                       step="0.01" min="0" max="100" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Deposit Percentage (%)</label>
                                <input type="number" name="deposit_percentage" class="form-input" 
                                       value="<?php echo $paymentSettings['deposit_percentage']; ?>" 
                                       step="0.01" min="0" max="100" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Currency</label>
                                <select name="currency" class="form-select" required>
                                    <option value="PHP" <?php echo $paymentSettings['currency'] === 'PHP' ? 'selected' : ''; ?>>PHP - Philippine Peso</option>
                                    <option value="USD" <?php echo $paymentSettings['currency'] === 'USD' ? 'selected' : ''; ?>>USD - US Dollar</option>
                                    <option value="EUR" <?php echo $paymentSettings['currency'] === 'EUR' ? 'selected' : ''; ?>>EUR - Euro</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Accepted Payment Methods</label>
                            <div class="checkbox-group">
                                <div class="checkbox-item">
                                    <input type="checkbox" name="payment_methods[]" value="cash" id="cash"
                                           <?php echo in_array('cash', $paymentSettings['payment_methods']) ? 'checked' : ''; ?>>
                                    <label for="cash">Cash</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" name="payment_methods[]" value="credit_card" id="credit"
                                           <?php echo in_array('credit_card', $paymentSettings['payment_methods']) ? 'checked' : ''; ?>>
                                    <label for="credit">Credit Card</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" name="payment_methods[]" value="debit_card" id="debit"
                                           <?php echo in_array('debit_card', $paymentSettings['payment_methods']) ? 'checked' : ''; ?>>
                                    <label for="debit">Debit Card</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" name="payment_methods[]" value="bank_transfer" id="bank"
                                           <?php echo in_array('bank_transfer', $paymentSettings['payment_methods']) ? 'checked' : ''; ?>>
                                    <label for="bank">Bank Transfer</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" name="payment_methods[]" value="gcash" id="gcash"
                                           <?php echo in_array('gcash', $paymentSettings['payment_methods']) ? 'checked' : ''; ?>>
                                    <label for="gcash">GCash</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" name="payment_methods[]" value="paymaya" id="paymaya"
                                           <?php echo in_array('paymaya', $paymentSettings['payment_methods']) ? 'checked' : ''; ?>>
                                    <label for="paymaya">PayMaya</label>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn-primary">Update Payment Settings</button>
                    </form>
                </div>

                <!-- Database Maintenance -->
                <div class="settings-card">
                    <div class="card-header">
                        <div class="card-icon">🗄️</div>
                        <div class="card-title">System Maintenance</div>
                    </div>

                    <div class="info-box">
                        <p>Perform database maintenance tasks and view system information.</p>
                    </div>

                    <div style="display: grid; gap: 20px;">
                        <div style="padding: 20px; background: #E8D8C4; border-radius: 12px; border-left: 4px solid #6D2932;">
                            <h4 style="color: #561C24; margin-bottom: 10px; font-size: 1.1em;">Database Status</h4>
                            <p style="color: #6D2932; font-size: 0.95em; margin-bottom: 8px;">
                                <strong>Total Users:</strong> <?php echo $db->users->countDocuments(); ?>
                            </p>
                            <p style="color: #6D2932; font-size: 0.95em; margin-bottom: 8px;">
                                <strong>Total Bookings:</strong> <?php echo $db->bookings->countDocuments(); ?>
                            </p>
                            <p style="color: #6D2932; font-size: 0.95em; margin-bottom: 8px;">
                                <strong>Total Rooms:</strong> <?php echo $db->rooms->countDocuments(); ?>
                            </p>
                            <p style="color: #6D2932; font-size: 0.95em;">
                                <strong>Total Parking Spaces:</strong> <?php echo $db->parking_spaces->countDocuments(); ?>
                            </p>
                        </div>

                        <div style="padding: 20px; background: #E8D8C4; border-radius: 12px; border-left: 4px solid #561C24;">
                            <h4 style="color: #561C24; margin-bottom: 10px; font-size: 1.1em;">System Information</h4>
                            <p style="color: #6D2932; font-size: 0.95em; margin-bottom: 8px;">
                                <strong>PHP Version:</strong> <?php echo phpversion(); ?>
                            </p>
                            <p style="color: #6D2932; font-size: 0.95em; margin-bottom: 8px;">
                                <strong>MongoDB Driver:</strong> <?php echo phpversion('mongodb'); ?>
                            </p>
                            <p style="color: #6D2932; font-size: 0.95em;">
                                <strong>Server Time:</strong> <?php echo date('Y-m-d H:i:s'); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Security Settings -->
                <div class="settings-card">
                    <div class="card-header">
                        <div class="card-icon">🔒</div>
                        <div class="card-title">Security & Access Control</div>
                    </div>

                    <div class="info-box">
                        <p>Configure security settings and access control policies.</p>
                    </div>

                    <div style="display: grid; gap: 20px;">
                        <div style="padding: 20px; background: #E8D8C4; border-radius: 12px;">
                            <h4 style="color: #561C24; margin-bottom: 12px; font-size: 1.1em;">Session Settings</h4>
                            <p style="color: #6D2932; font-size: 0.9em; line-height: 1.6; margin-bottom: 12px;">
                                Session timeout is currently set to 30 minutes of inactivity. Users will be automatically logged out after this period.
                            </p>
                        </div>

                        <div style="padding: 20px; background: #E8D8C4; border-radius: 12px;">
                            <h4 style="color: #561C24; margin-bottom: 12px; font-size: 1.1em;">Password Policy</h4>
                            <p style="color: #6D2932; font-size: 0.9em; line-height: 1.6;">
                                • Minimum 8 characters required<br>
                                • Must contain uppercase and lowercase letters<br>
                                • Must contain at least one number<br>
                                • Password reset available via email
                            </p>
                        </div>

                        <div style="padding: 20px; background: #E8D8C4; border-radius: 12px;">
                            <h4 style="color: #561C24; margin-bottom: 12px; font-size: 1.1em;">Backup Recommendations</h4>
                            <p style="color: #6D2932; font-size: 0.9em; line-height: 1.6;">
                                Regular database backups are recommended. Consider using MongoDB's built-in backup tools or cloud backup services for data protection.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Email Notifications -->
                <div class="settings-card">
                    <div class="card-header">
                        <div class="card-icon">📧</div>
                        <div class="card-title">Email Notifications</div>
                    </div>

                    <div class="info-box">
                        <p>Configure email notification settings for bookings, cancellations, and system alerts.</p>
                    </div>

                    <div style="display: grid; gap: 15px;">
                        <div style="padding: 18px; background: #E8D8C4; border-radius: 10px; display: flex; align-items: center; justify-content: space-between;">
                            <div>
                                <h4 style="color: #561C24; margin-bottom: 5px; font-size: 1em;">Booking Confirmations</h4>
                                <p style="color: #6D2932; font-size: 0.85em;">Send email confirmation when booking is created</p>
                            </div>
                            <label style="position: relative; display: inline-block; width: 60px; height: 30px;">
                                <input type="checkbox" checked style="opacity: 0; width: 0; height: 0;">
                                <span style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: #6D2932; border-radius: 30px; transition: 0.3s;"></span>
                            </label>
                        </div>

                        <div style="padding: 18px; background: #E8D8C4; border-radius: 10px; display: flex; align-items: center; justify-content: space-between;">
                            <div>
                                <h4 style="color: #561C24; margin-bottom: 5px; font-size: 1em;">Cancellation Notices</h4>
                                <p style="color: #6D2932; font-size: 0.85em;">Notify guests when booking is cancelled</p>
                            </div>
                            <label style="position: relative; display: inline-block; width: 60px; height: 30px;">
                                <input type="checkbox" checked style="opacity: 0; width: 0; height: 0;">
                                <span style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: #6D2932; border-radius: 30px; transition: 0.3s;"></span>
                            </label>
                        </div>

                        <div style="padding: 18px; background: #E8D8C4; border-radius: 10px; display: flex; align-items: center; justify-content: space-between;">
                            <div>
                                <h4 style="color: #561C24; margin-bottom: 5px; font-size: 1em;">Check-in Reminders</h4>
                                <p style="color: #6D2932; font-size: 0.85em;">Send reminder 24 hours before check-in</p>
                            </div>
                            <label style="position: relative; display: inline-block; width: 60px; height: 30px;">
                                <input type="checkbox" checked style="opacity: 0; width: 0; height: 0;">
                                <span style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: #6D2932; border-radius: 30px; transition: 0.3s;"></span>
                            </label>
                        </div>

                        <div style="padding: 18px; background: #E8D8C4; border-radius: 10px; display: flex; align-items: center; justify-content: space-between;">
                            <div>
                                <h4 style="color: #561C24; margin-bottom: 5px; font-size: 1em;">Admin Alerts</h4>
                                <p style="color: #6D2932; font-size: 0.85em;">Receive alerts for new bookings and cancellations</p>
                            </div>
                            <label style="position: relative; display: inline-block; width: 60px; height: 30px;">
                                <input type="checkbox" checked style="opacity: 0; width: 0; height: 0;">
                                <span style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: #6D2932; border-radius: 30px; transition: 0.3s;"></span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-hide success messages after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert-success');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }, 5000);
            });
        });
    </script>
</body>
</html>