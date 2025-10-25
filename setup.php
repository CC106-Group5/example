<?php
/**
 * Database Setup Script for E-LODGE System
 * Run this file once to initialize the database with test data
 * Access: http://localhost/your-project/setup.php
 */

require_once 'config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>E-LODGE Database Setup</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: green; padding: 10px; background: #e7f4e7; margin: 10px 0; }
        .error { color: red; padding: 10px; background: #ffe7e7; margin: 10px 0; }
        .info { color: blue; padding: 10px; background: #e7f0ff; margin: 10px 0; }
        h1 { color: #1e3a5f; }
        .credentials { background: #f5f5f5; padding: 15px; margin: 20px 0; border-left: 4px solid #d4af37; }
        .credentials h3 { margin-top: 0; color: #1e3a5f; }
    </style>
</head>
<body>
    <h1>E-LODGE Database Setup</h1>";

try {
    // Connect to MongoDB
    $client = new MongoDB\Client($mongoUri);
    echo "<div class='success'>✓ Successfully connected to MongoDB</div>";
    
    $db = $client->elodge_db;
    
    // Create Users Collection
    echo "<h2>Setting up Users Collection...</h2>";
    $users = $db->users;
    
    // Check if users already exist
    $existingUsers = $users->countDocuments();
    if ($existingUsers > 0) {
        echo "<div class='info'>ℹ Users already exist ($existingUsers users found). Skipping user creation.</div>";
    } else {
        // Create test users
        $testUsers = [
            [
                'username' => 'admin',
                'password' => password_hash('admin123', PASSWORD_DEFAULT),
                'email' => 'admin@elodge.com',
                'full_name' => 'System Administrator',
                'role' => 'admin',
                'status' => 'active',
                'created_at' => new MongoDB\BSON\UTCDateTime()
            ],
            [
                'username' => 'receptionist',
                'password' => password_hash('recep123', PASSWORD_DEFAULT),
                'email' => 'reception@elodge.com',
                'full_name' => 'Front Desk Receptionist',
                'role' => 'receptionist',
                'status' => 'active',
                'created_at' => new MongoDB\BSON\UTCDateTime()
            ],
            [
                'username' => 'guest1',
                'password' => password_hash('guest123', PASSWORD_DEFAULT),
                'email' => 'guest1@email.com',
                'full_name' => 'John Doe',
                'role' => 'guest',
                'status' => 'active',
                'created_at' => new MongoDB\BSON\UTCDateTime()
            ]
        ];
        
        $users->insertMany($testUsers);
        echo "<div class='success'>✓ Created " . count($testUsers) . " test users</div>";
    }
    
    // Create Rooms Collection
    echo "<h2>Setting up Rooms Collection...</h2>";
    $rooms = $db->rooms;
    
    $existingRooms = $rooms->countDocuments();
    if ($existingRooms > 0) {
        echo "<div class='info'>ℹ Rooms already exist ($existingRooms rooms found). Skipping room creation.</div>";
    } else {
        $testRooms = [
            [
                'room_number' => '101',
                'room_type' => 'Standard Room',
                'description' => 'Comfortable room with single bed, AC, and WiFi',
                'price' => 1500.00,
                'capacity' => 2,
                'status' => 'available',
                'amenities' => ['AC', 'WiFi', 'TV', 'Private Bathroom'],
                'created_at' => new MongoDB\BSON\UTCDateTime()
            ],
            [
                'room_number' => '102',
                'room_type' => 'Deluxe Room',
                'description' => 'Spacious room with queen bed and mini fridge',
                'price' => 2500.00,
                'capacity' => 3,
                'status' => 'available',
                'amenities' => ['AC', 'WiFi', 'TV', 'Private Bathroom', 'Mini Fridge'],
                'created_at' => new MongoDB\BSON\UTCDateTime()
            ],
            [
                'room_number' => '201',
                'room_type' => 'Suite',
                'description' => 'Luxury suite with king bed and living area',
                'price' => 4000.00,
                'capacity' => 4,
                'status' => 'available',
                'amenities' => ['AC', 'WiFi', 'Smart TV', 'Private Bathroom', 'Mini Bar', 'Balcony'],
                'created_at' => new MongoDB\BSON\UTCDateTime()
            ],
            [
                'room_number' => '103',
                'room_type' => 'Standard Room',
                'description' => 'Cozy room perfect for solo travelers',
                'price' => 1500.00,
                'capacity' => 2,
                'status' => 'available',
                'amenities' => ['AC', 'WiFi', 'TV', 'Private Bathroom'],
                'created_at' => new MongoDB\BSON\UTCDateTime()
            ],
            [
                'room_number' => '202',
                'room_type' => 'Family Room',
                'description' => 'Large room with multiple beds for families',
                'price' => 3500.00,
                'capacity' => 6,
                'status' => 'available',
                'amenities' => ['AC', 'WiFi', 'TV', 'Private Bathroom', 'Mini Fridge', 'Extra Space'],
                'created_at' => new MongoDB\BSON\UTCDateTime()
            ]
        ];
        
        $rooms->insertMany($testRooms);
        echo "<div class='success'>✓ Created " . count($testRooms) . " test rooms</div>";
    }
    
    // Create Parking Spaces Collection
    echo "<h2>Setting up Parking Spaces Collection...</h2>";
    $parking = $db->parking_spaces;
    
    $existingParking = $parking->countDocuments();
    if ($existingParking > 0) {
        echo "<div class='info'>ℹ Parking spaces already exist ($existingParking spaces found). Skipping parking creation.</div>";
    } else {
        $testParking = [];
        for ($i = 1; $i <= 20; $i++) {
            $testParking[] = [
                'space_number' => 'P' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'type' => ($i <= 15) ? 'regular' : 'premium',
                'status' => 'available',
                'price_per_day' => ($i <= 15) ? 100.00 : 200.00,
                'created_at' => new MongoDB\BSON\UTCDateTime()
            ];
        }
        
        $parking->insertMany($testParking);
        echo "<div class='success'>✓ Created " . count($testParking) . " parking spaces</div>";
    }
    
    // Create indexes for better performance
    echo "<h2>Creating Database Indexes...</h2>";
    $users->createIndex(['username' => 1], ['unique' => true]);
    $users->createIndex(['email' => 1]);
    $rooms->createIndex(['room_number' => 1], ['unique' => true]);
    $rooms->createIndex(['status' => 1]);
    $parking->createIndex(['space_number' => 1], ['unique' => true]);
    echo "<div class='success'>✓ Database indexes created</div>";
    
    // Display test credentials
    echo "<div class='credentials'>
        <h3>🔑 Test Login Credentials</h3>
        <p><strong>Admin Account:</strong><br>
        Username: admin<br>
        Password: admin123</p>
        
        <p><strong>Receptionist Account:</strong><br>
        Username: receptionist<br>
        Password: recep123</p>
        
        <p><strong>Guest Account:</strong><br>
        Username: guest1<br>
        Password: guest123</p>
    </div>";
    
    echo "<div class='success'><h3>✓ Database Setup Complete!</h3></div>";
    echo "<p><a href='login.php' style='display: inline-block; padding: 10px 20px; background: #1e3a5f; color: white; text-decoration: none; border-radius: 5px;'>Go to Login Page</a></p>";
    
} catch (Exception $e) {
    echo "<div class='error'>✗ Error: " . $e->getMessage() . "</div>";
    echo "<p>Please make sure:</p>";
    echo "<ul>";
    echo "<li>MongoDB server is running</li>";
    echo "<li>MongoDB PHP extension is installed</li>";
    echo "<li>Connection URI in config.php is correct</li>";
    echo "</ul>";
}

echo "</body></html>";
?>