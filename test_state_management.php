<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\AdminState;

echo "Testing AdminState model...\n";

// Test 1: Set state
$chatId = 123456789;
$stateData = [
    'mode' => 'story_creation',
    'stage_number' => 1,
    'current_story' => 1,
    'waiting_for' => 'points'
];

echo "Setting state for chat ID: {$chatId}\n";
AdminState::setState($chatId, $stateData);

// Test 2: Get state
echo "Getting state for chat ID: {$chatId}\n";
$retrievedState = AdminState::getState($chatId);

if ($retrievedState) {
    echo "State retrieved successfully!\n";
    echo "Mode: " . $retrievedState['mode'] . "\n";
    echo "Stage number: " . $retrievedState['stage_number'] . "\n";
    echo "Waiting for: " . $retrievedState['waiting_for'] . "\n";
} else {
    echo "Failed to retrieve state!\n";
}

// Test 3: Update state
echo "Updating state...\n";
$retrievedState['points'] = 200;
AdminState::setState($chatId, $retrievedState);

$updatedState = AdminState::getState($chatId);
if ($updatedState && isset($updatedState['points'])) {
    echo "State updated successfully! Points: " . $updatedState['points'] . "\n";
} else {
    echo "Failed to update state!\n";
}

// Test 4: Clear state
echo "Clearing state...\n";
AdminState::clearState($chatId);

$clearedState = AdminState::getState($chatId);
if (!$clearedState) {
    echo "State cleared successfully!\n";
} else {
    echo "Failed to clear state!\n";
}

echo "Test completed!\n"; 