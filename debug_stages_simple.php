<?php

require_once 'vendor/autoload.php';

use App\Models\Stage;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Debug Stages ===\n";

try {
    $stages = Stage::with(['photos'])->orderBy('stage_number')->get();
    
    echo "Total stages: " . $stages->count() . "\n\n";
    
    foreach ($stages as $stage) {
        echo "Stage ID: {$stage->id}\n";
        echo "Stage Number: {$stage->stage_number}\n";
        echo "Points: {$stage->points}\n";
        echo "Photos Count: " . $stage->photos->count() . "\n";
        echo "---\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
