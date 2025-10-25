<?php
require 'vendor/autoload.php';

try {
    $client = new MongoDB\Client("mongodb://localhost:27017");
    echo "✅ MongoDB Connected Successfully!";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
