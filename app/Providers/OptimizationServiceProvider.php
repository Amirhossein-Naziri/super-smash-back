<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class OptimizationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // فعال کردن query logging برای بهینه‌سازی
        if (config('optimization.monitoring.log_slow_queries')) {
            DB::listen(function ($query) {
                $time = $query->time;
                if ($time > config('optimization.monitoring.slow_query_threshold')) {
                    Log::warning('Slow query detected', [
                        'sql' => $query->sql,
                        'bindings' => $query->bindings,
                        'time' => $time
                    ]);
                }
            });
        }

        // پاک کردن cache هنگام تغییر داده‌ها
        $this->registerCacheInvalidation();
    }

    /**
     * Register cache invalidation
     */
    private function registerCacheInvalidation()
    {
        // پاک کردن cache مراحل هنگام تغییر
        \App\Models\Stage::updated(function ($stage) {
            Cache::forget("stage_details_{$stage->id}");
            Cache::forget('admin_stages_data');
            Cache::forget('total_stages_count');
        });

        \App\Models\Stage::created(function ($stage) {
            Cache::forget('admin_stages_data');
            Cache::forget('total_stages_count');
        });

        // پاک کردن cache کاربران هنگام تغییر
        \App\Models\User::updated(function ($user) {
            Cache::forget("user_{$user->telegram_user_id}");
            Cache::forget("next_stage_user_{$user->id}");
        });

        // پاک کردن cache عکس‌ها هنگام تغییر
        \App\Models\StagePhoto::updated(function ($photo) {
            Cache::forget("stage_details_{$photo->stage_id}");
        });
    }
}
