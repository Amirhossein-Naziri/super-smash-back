<?php

/**
 * Application Optimization Script
 * 
 * This script optimizes the Laravel application for better performance
 */

echo "=== Super Smash Backend Optimization ===\n\n";

// Check PHP version
echo "PHP Version: " . PHP_VERSION . "\n";

// Check extensions
$requiredExtensions = ['redis', 'pdo_mysql', 'curl', 'json', 'mbstring'];
$missingExtensions = [];

foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    }
}

if (!empty($missingExtensions)) {
    echo "❌ Missing extensions: " . implode(', ', $missingExtensions) . "\n";
    echo "Please install these extensions for optimal performance.\n\n";
} else {
    echo "✅ All required extensions are installed.\n\n";
}

// Check Redis connection
echo "Testing Redis connection...\n";
try {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    $redis->ping();
    echo "✅ Redis connection successful.\n\n";
} catch (Exception $e) {
    echo "❌ Redis connection failed: " . $e->getMessage() . "\n";
    echo "Please ensure Redis is running for optimal performance.\n\n";
}

// Check database connection
echo "Testing database connection...\n";
try {
    require_once 'vendor/autoload.php';
    $app = require_once 'bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    
    DB::connection()->getPdo();
    echo "✅ Database connection successful.\n\n";
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n\n";
}

// Clear caches
echo "Clearing application caches...\n";
try {
    if (function_exists('exec')) {
        exec('php artisan cache:clear 2>&1', $output, $returnCode);
        if ($returnCode === 0) {
            echo "✅ Application cache cleared.\n";
        } else {
            echo "❌ Failed to clear application cache.\n";
        }
        
        exec('php artisan config:clear 2>&1', $output, $returnCode);
        if ($returnCode === 0) {
            echo "✅ Configuration cache cleared.\n";
        } else {
            echo "❌ Failed to clear configuration cache.\n";
        }
        
        exec('php artisan route:clear 2>&1', $output, $returnCode);
        if ($returnCode === 0) {
            echo "✅ Route cache cleared.\n";
        } else {
            echo "❌ Failed to clear route cache.\n";
        }
        
        exec('php artisan view:clear 2>&1', $output, $returnCode);
        if ($returnCode === 0) {
            echo "✅ View cache cleared.\n";
        } else {
            echo "❌ Failed to clear view cache.\n";
        }
    } else {
        echo "❌ exec() function is disabled. Please clear caches manually.\n";
    }
} catch (Exception $e) {
    echo "❌ Error clearing caches: " . $e->getMessage() . "\n";
}

echo "\n=== Optimization Complete ===\n";
echo "For best performance, ensure:\n";
echo "1. Redis is running and accessible\n";
echo "2. Database connection is stable\n";
echo "3. All required PHP extensions are installed\n";
echo "4. Web server is configured for optimal performance\n";
echo "5. Use production environment settings\n\n";
