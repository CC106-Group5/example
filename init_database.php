<?php
/**
 * Database Initialization Script
 * Run this file once to set up the database with initial data
 */

require_once 'config.php';

try {
    $client = new MongoDB\Client($mongoUri);
    $db = $client->elodge_db;
    
    echo "Starting database initialization...\n\n";
    
    // Create admin user
    $adminExists = $db->users->findOne(['username' => 'admin']);
    
    if (!$adminExists) {
        $db->users->insertOne([
            'username' => 'admin',
            'email' => 'admin@elodge.com',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'role' => 'admin',
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'updated_at' => new MongoDB\BSON\UTCDateTime()
        ]);
        echo "✓ Admin user created (username: admin, password: admin123)\n";
    } else {
        echo "✓ Admin user already exists\n";
    }
    
    // Create sample user
    $userExists = $db->users->findOne(['username' => 'user']);
    
    if (!$userExists) {
        $db->users->insertOne([
            'username' => 'user',
            'email' => 'user@elodge.com',
            'password' => password_hash('user123', PASSWORD_DEFAULT),
            'role' => 'user',
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'updated_at' => new MongoDB\BSON\UTCDateTime()
        ]);
        echo "✓ Sample user created (username: user, password: user123)\n";
    } else {
        echo "✓ Sample user already exists\n";
    }
    
    // Create sample rooms
    $roomCount = $db->rooms->countDocuments();
    
    if ($roomCount == 0) {
        $rooms = [
            [
                'room_number' => '101',
                'room_type' => 'Single',
                'price' => 1500.00,
                'capacity' => 1,
                'status' => 'available',
                'description' => 'Cozy single room with all basic amenities',
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ],
            [
                'room_number' => '102',
                'room_type' => 'Double',
                'price' => 2500.00,
                'capacity' => 2,
                'status' => 'available',
                'description' => 'Comfortable double room perfect for couples',
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ],
            [
                'room_number' => '201',
                'room_type' => 'Suite',
                'price' => 4500.00,
                'capacity' => 4,
                'status' => 'available',
                'description' => 'Luxury suite with living area and premium amenities',
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ],
            [
                'room_number' => '202',
                'room_type' => 'Deluxe',
                'price' => 3500.00,
                'capacity' => 3,
                'status' => 'available',
                'description' => 'Spacious deluxe room with city view',
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ],
            [
                'room_number' => '301',
                'room_type' => 'Suite',
                'price' => 5000.00,
                'capacity' => 4,
                'status' => 'available',
                'description' => 'Premium suite on top floor with panoramic views',
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ]
        ];
        
        $db->rooms->insertMany($rooms);
        echo "✓ Created 5 sample rooms\n";
    } else {
        echo "✓ Rooms already exist ($roomCount rooms)\n";
    }
    
    // Create sample parking spaces
    $parkingCount = $db->parking_spaces->countDocuments();
    
    if ($parkingCount == 0) {
        $parkingSpaces = [
            [
                'space_number' => 'P-01',
                'type' => 'Standard',
                'price' => 100.00,
                'status' => 'available',
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ],
            [
                'space_number' => 'P-02',
                'type' => 'Standard',
                'price' => 100.00,
                'status' => 'available',
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ],
            [
                'space_number' => 'P-03',
                'type' => 'Covered',
                'price' => 150.00,
                'status' => 'available',
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ],
            [
                'space_number' => 'P-04',
                'type' => 'VIP',
                'price' => 200.00,
                'status' => 'available',
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ]
        ];
        
        $db->parking_spaces->insertMany($parkingSpaces);
        echo "✓ Created 4 sample parking spaces\n";
    } else {
        echo "✓ Parking spaces already exist ($parkingCount spaces)\n";
    }
    
    // Create sample booking
    $bookingCount = $db->bookings->countDocuments();
    
    if ($bookingCount == 0) {
        $db->bookings->insertOne([
            'guest_name' => 'John Doe',
            'guest_email' => 'john@example.com',
            'guest_phone' => '+63 912 345 6789',
            'room_type' => 'Double',
            'room_number' => '102',
            'check_in' => new MongoDB\BSON\UTCDateTime(strtotime('+1 day') * 1000),
            'check_out' => new MongoDB\BSON\UTCDateTime(strtotime('+3 days') * 1000),
            'total_price' => 5000.00,
            'status' => 'active',
            'parking_required' => true,
            'parking_space' => 'P-01',
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'updated_at' => new MongoDB\BSON\UTCDateTime()
        ]);
        echo "✓ Created sample booking\n";
    } else {
        echo "✓ Bookings already exist ($bookingCount bookings)\n";
    }
    
    // Initialize system settings
    $settingsExists = $db->settings->findOne(['type' => 'system']);
    
    if (!$settingsExists) {
        $db->settings->insertOne([
            'type' => 'system',
            'hotel_name' => 'Adine Hotel',
            'hotel_email' => 'info@adinehotel.com',
            'hotel_phone' => '+63 123 456 7890',
            'hotel_address' => 'Quezon City, Metro Manila, Philippines',
            'check_in_time' => '14:00',
            'check_out_time' => '12:00',
            'tax_rate' => 12.00,
            'currency' => 'PHP',
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'updated_at' => new MongoDB\BSON\UTCDateTime()
        ]);
        echo "✓ System settings initialized\n";
    } else {
        echo "✓ System settings already exist\n";
    }
    
    // Create indexes for better performance
    $db->users->createIndex(['username' => 1], ['unique' => true]);
    $db->users->createIndex(['email' => 1], ['unique' => true]);
    $db->rooms->createIndex(['room_number' => 1], ['unique' => true]);
    $db->parking_spaces->createIndex(['space_number' => 1], ['unique' => true]);
    $db->bookings->createIndex(['created_at' => -1]);
    echo "✓ Database indexes created\n";
    
    echo "\n========================================\n";
    echo "Database initialization completed!\n";
    echo "========================================\n";
    echo "\nLogin credentials:\n";
    echo "Admin - Username: admin, Password: admin123\n";
    echo "User  - Username: user, Password: user123\n";
    echo "\nYou can now access the application!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}