<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spark\Billable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use Billable, HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'uploads_count',
        'uploads_reset_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'uploads_reset_at' => 'datetime',
        ];
    }

    /**
     * Get the upload limit based on the user's subscription plan.
     */
    public function uploadLimit(): int
    {
        if (! $this->subscribed()) {
            return 0;
        }

        $planName = $this->subscription()->stripe_price;

        return match ($planName) {
            env('SPARK_STANDARD_MONTHLY_PLAN'), env('SPARK_STANDARD_YEARLY_PLAN') => 1000,
            env('SPARK_STARTUP_MONTHLY_PLAN'), env('SPARK_STARTUP_YEARLY_PLAN') => 100000,
            env('SPARK_ENTERPRISE_MONTHLY_PLAN'), env('SPARK_ENTERPRISE_YEARLY_PLAN') => 1000000,
            default => 0,
        };
    }

    /**
     * Check if the user can upload more images.
     */
    public function canUpload(): bool
    {
        return $this->uploads_count < $this->uploadLimit();
    }

    /**
     * Increment the upload count.
     */
    public function incrementUploads(): void
    {
        $this->increment('uploads_count');
    }

    /**
     * Reset upload count (typically called when subscription renews via webhook).
     */
    public function resetUploads(): void
    {
        $this->uploads_count = 0;
        $this->uploads_reset_at = now();
        $this->save();
    }

    /**
     * Get remaining uploads for the current period.
     */
    public function remainingUploads(): int
    {
        return max(0, $this->uploadLimit() - $this->uploads_count);
    }

    /**
     * Check if user is on a premium plan (Startup or Enterprise).
     * Premium plans get full-resolution AI processing.
     * Standard plan gets cost-optimized 768px processing.
     */
    public function hasPremiumPlan(): bool
    {
        if (! $this->subscribed()) {
            return false;
        }

        // Get the user's current plan price ID
        $currentPriceId = $this->subscription()->stripe_price;

        // Get premium plan IDs from Spark config (Startup and Enterprise)
        $plans = config('spark.billables.user.plans', []);
        $premiumPlanIds = [];

        foreach ($plans as $plan) {
            // Premium plans are Startup and Enterprise (not Standard)
            if (in_array($plan['name'], ['Startup', 'Enterprise'])) {
                $premiumPlanIds[] = $plan['monthly_id'];
                $premiumPlanIds[] = $plan['yearly_id'];
            }
        }

        return in_array($currentPriceId, $premiumPlanIds);
    }

    /**
     * Get maximum allowed image dimension based on user's subscription plan.
     * - Standard plan: 768px (cost-optimized for AI processing)
     * - Startup/Enterprise plans: 2048px (full resolution)
     */
    public function maxImageDimension(): int
    {
        return $this->hasPremiumPlan() ? 2048 : 768;
    }

    /**
     * Get all images belonging to this user.
     */
    public function images(): HasMany
    {
        return $this->hasMany(Image::class);
    }

    // TODO: Implement webhook listener for subscription renewal
    // Listen for invoice.payment_succeeded webhook event from Stripe
    // and call resetUploads() to reset the counter on each billing cycle
    // This ensures uploads reset when the subscription actually renews,
    // not just on a calendar month basis.
}
