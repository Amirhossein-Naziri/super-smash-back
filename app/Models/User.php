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
        'referral_code',
        'referred_by',
        'referral_count',
        'referral_rewards',
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

    /**
     * Get the user who referred this user
     */
    public function referrer()
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    /**
     * Get users referred by this user
     */
    public function referrals()
    {
        return $this->hasMany(User::class, 'referred_by');
    }

    /**
     * Get referral records made by this user
     */
    public function referralRecords()
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }

    /**
     * Get referral record for this user (if they were referred)
     */
    public function referralRecord()
    {
        return $this->hasOne(Referral::class, 'referred_id');
    }

    /**
     * Generate a unique referral code for this user
     */
    public function generateReferralCode()
    {
        do {
            $code = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8));
        } while (self::where('referral_code', $code)->exists());

        $this->update(['referral_code' => $code]);
        return $code;
    }

    /**
     * Add referral reward to user's score
     */
    public function addReferralReward($amount)
    {
        $this->increment('score', $amount);
        $this->increment('referral_rewards', $amount);
    }

    /**
     * Increment referral count
     */
    public function incrementReferralCount()
    {
        $this->increment('referral_count');
    }
}
