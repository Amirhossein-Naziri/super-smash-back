<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Converting existing codes to lowercase...\n";

try {
    // Get all stage photos with codes
    $photos = DB::table('stage_photos')
        ->whereNotNull('code_1')
        ->whereNotNull('code_2')
        ->get();

    echo "Found " . $photos->count() . " photos with codes.\n";

    foreach ($photos as $photo) {
        $oldCode1 = $photo->code_1;
        $oldCode2 = $photo->code_2;
        
        $newCode1 = strtolower(trim(preg_replace('/\s+/', '', $oldCode1)));
        $newCode2 = strtolower(trim(preg_replace('/\s+/', '', $oldCode2)));
        
        // Update the codes
        DB::table('stage_photos')
            ->where('id', $photo->id)
            ->update([
                'code_1' => $newCode1,
                'code_2' => $newCode2,
                'updated_at' => now()
            ]);
        
        echo "Photo {$photo->id}: {$oldCode1} -> {$newCode1}, {$oldCode2} -> {$newCode2}\n";
    }
    
    echo "All codes converted successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
