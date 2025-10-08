<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'password',
        'phone',
        'city',
        'telegram_user_id',
        'telegram_username',
        'telegram_first_name',
        'telegram_last_name',
        'telegram_language_code',
        'score',
        'level_story',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
    ];

    /**
     * Get the codes used by this user
     */
    public function codes()
    {
        return $this->hasMany(Code::class);
    }

    /**
     * Get voice recordings made by this user
     */
    public function voiceRecordings()
    {
        return $this->hasMany(UserVoiceRecording::class);
    }

    /**
     * Get stage progress for this user
     */
    public function stageProgress()
    {
        return $this->hasMany(UserStageProgress::class);
    }
}
