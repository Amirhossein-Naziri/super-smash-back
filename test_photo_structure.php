<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing photo message structure...\n";

// Simulate a photo message structure
$mockMessage = [
    'message_id' => 123,
    'from' => ['id' => 456, 'first_name' => 'Test'],
    'chat' => ['id' => 789, 'type' => 'private'],
    'date' => time(),
    'photo' => [
        [
            'file_id' => 'test_file_id_1',
            'file_unique_id' => 'test_unique_1',
            'width' => 90,
            'height' => 90,
            'file_size' => 1000
        ],
        [
            'file_id' => 'test_file_id_2',
            'file_unique_id' => 'test_unique_2',
            'width' => 320,
            'height' => 320,
            'file_size' => 5000
        ],
        [
            'file_id' => 'test_file_id_3',
            'file_unique_id' => 'test_unique_3',
            'width' => 800,
            'height' => 800,
            'file_size' => 15000
        ]
    ]
];

echo "Mock message structure:\n";
echo json_encode($mockMessage, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// Test different ways to access photo data
echo "Testing photo access methods:\n";

// Method 1: Direct array access
$photos = $mockMessage['photo'];
echo "Method 1 - Direct array: " . count($photos) . " photos\n";

// Method 2: Get largest photo
$largestPhoto = end($photos);
echo "Method 2 - Largest photo: " . json_encode($largestPhoto) . "\n";

// Method 3: Get file_id
$fileId = $largestPhoto['file_id'];
echo "Method 3 - File ID: " . $fileId . "\n";

// Method 4: Try object-like access
echo "Method 4 - Testing object access:\n";
foreach ($photos as $index => $photo) {
    echo "  Photo {$index}: file_id = " . ($photo['file_id'] ?? 'NOT_FOUND') . "\n";
}

echo "\nTest completed!\n"; 