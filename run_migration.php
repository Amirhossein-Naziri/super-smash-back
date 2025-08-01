<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

echo "Creating admin_states table...\n";

Schema::create('admin_states', function (Blueprint $table) {
    $table->id();
    $table->bigInteger('chat_id');
    $table->json('state_data');
    $table->timestamp('expires_at')->nullable();
    $table->timestamps();
    
    $table->index('chat_id');
    $table->index('expires_at');
});

echo "Admin states table created successfully!\n"; 