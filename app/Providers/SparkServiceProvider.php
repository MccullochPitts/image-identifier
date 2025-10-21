<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Spark\Plan;
use Spark\Spark;

class SparkServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Spark::billable(User::class)->resolve(function (Request $request) {
            return $request->user();
        });

        Spark::billable(User::class)->authorize(function (User $billable, Request $request) {
            return $request->user() &&
                   $request->user()->id == $billable->id;
        });

        Spark::billable(User::class)->checkPlanEligibility(function (User $billable, Plan $plan) {
            // Determine the target plan's upload limit based on the price ID
            $targetLimit = match ($plan->id) {
                env('SPARK_STANDARD_MONTHLY_PLAN'), env('SPARK_STANDARD_YEARLY_PLAN') => 1000,
                env('SPARK_STARTUP_MONTHLY_PLAN'), env('SPARK_STARTUP_YEARLY_PLAN') => 100000,
                env('SPARK_ENTERPRISE_MONTHLY_PLAN'), env('SPARK_ENTERPRISE_YEARLY_PLAN') => 1000000,
                default => 0,
            };

            // Block plan change if user has met or exceeded the target plan's limit
            if ($billable->uploads_count >= $targetLimit) {
                throw ValidationException::withMessages([
                    'plan' => sprintf(
                        'You have used %s uploads this billing period. Cannot switch to a plan with a %s upload limit. Please wait until your next billing cycle or contact support.',
                        number_format($billable->uploads_count),
                        number_format($targetLimit)
                    ),
                ]);
            }
        });
    }
}
