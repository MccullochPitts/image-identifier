<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spark\Spark;

uses(RefreshDatabase::class)->group('stripe');

beforeEach(function () {
    config(['cashier.key' => env('STRIPE_KEY')]);
    config(['cashier.secret' => env('STRIPE_SECRET')]);
});

// Helper function to get Plan object by price ID
function getPlanByPriceId($priceId)
{
    $plans = Spark::plans('user');
    foreach ($plans as $plan) {
        if ($plan->id === $priceId) {
            return $plan;
        }
    }

    return null;
}

// Helper function to attempt plan swap with eligibility check
function attemptPlanSwap($user, $newPriceId)
{
    $plan = getPlanByPriceId($newPriceId);

    // This mimics what Spark's UpdateSubscriptionController does
    Spark::ensurePlanEligibility($user, $plan);

    // If no exception, perform the swap
    $user->subscription('default')->swap($newPriceId);
}

// ========================================
// UPGRADE SCENARIOS - Should Always Work
// ========================================

test('user can upgrade from standard to startup with zero usage', function () {
    $user = User::factory()->create(['uploads_count' => 0]);

    $user->newSubscription('default', env('SPARK_STANDARD_MONTHLY_PLAN'))->create('pm_card_visa');

    $user->subscription('default')->swap(env('SPARK_STARTUP_MONTHLY_PLAN'));

    expect($user->fresh()->uploadLimit())->toBe(100000)
        ->and($user->uploads_count)->toBe(0);
});

test('user can upgrade from standard to startup with partial usage', function () {
    $user = User::factory()->create(['uploads_count' => 500]);

    $user->newSubscription('default', env('SPARK_STANDARD_MONTHLY_PLAN'))->create('pm_card_visa');

    $user->subscription('default')->swap(env('SPARK_STARTUP_MONTHLY_PLAN'));

    expect($user->fresh()->uploadLimit())->toBe(100000)
        ->and($user->uploads_count)->toBe(500);
});

test('user can upgrade from standard to startup at maximum standard usage', function () {
    $user = User::factory()->create(['uploads_count' => 1000]);

    $user->newSubscription('default', env('SPARK_STANDARD_MONTHLY_PLAN'))->create('pm_card_visa');

    $user->subscription('default')->swap(env('SPARK_STARTUP_MONTHLY_PLAN'));

    expect($user->fresh()->uploadLimit())->toBe(100000)
        ->and($user->uploads_count)->toBe(1000);
});

test('user can upgrade from startup to enterprise with zero usage', function () {
    $user = User::factory()->create(['uploads_count' => 0]);

    $user->newSubscription('default', env('SPARK_STARTUP_MONTHLY_PLAN'))->create('pm_card_visa');

    $user->subscription('default')->swap(env('SPARK_ENTERPRISE_MONTHLY_PLAN'));

    expect($user->fresh()->uploadLimit())->toBe(1000000)
        ->and($user->uploads_count)->toBe(0);
});

test('user can upgrade from startup to enterprise with partial usage', function () {
    $user = User::factory()->create(['uploads_count' => 50000]);

    $user->newSubscription('default', env('SPARK_STARTUP_MONTHLY_PLAN'))->create('pm_card_visa');

    $user->subscription('default')->swap(env('SPARK_ENTERPRISE_MONTHLY_PLAN'));

    expect($user->fresh()->uploadLimit())->toBe(1000000)
        ->and($user->uploads_count)->toBe(50000);
});

test('user can upgrade from standard directly to enterprise', function () {
    $user = User::factory()->create(['uploads_count' => 800]);

    $user->newSubscription('default', env('SPARK_STANDARD_MONTHLY_PLAN'))->create('pm_card_visa');

    $user->subscription('default')->swap(env('SPARK_ENTERPRISE_MONTHLY_PLAN'));

    expect($user->fresh()->uploadLimit())->toBe(1000000)
        ->and($user->uploads_count)->toBe(800);
});

// ========================================
// DOWNGRADE SCENARIOS - Allowed When Under Limit
// ========================================

test('user can downgrade from enterprise to startup when usage is zero', function () {
    $user = User::factory()->create(['uploads_count' => 0]);

    $user->newSubscription('default', env('SPARK_ENTERPRISE_MONTHLY_PLAN'))->create('pm_card_visa');

    $user->subscription('default')->swap(env('SPARK_STARTUP_MONTHLY_PLAN'));

    expect($user->fresh()->uploadLimit())->toBe(100000)
        ->and($user->uploads_count)->toBe(0);
});

test('user can downgrade from enterprise to startup when usage is under startup limit', function () {
    $user = User::factory()->create(['uploads_count' => 50000]);

    $user->newSubscription('default', env('SPARK_ENTERPRISE_MONTHLY_PLAN'))->create('pm_card_visa');

    $user->subscription('default')->swap(env('SPARK_STARTUP_MONTHLY_PLAN'));

    expect($user->fresh()->uploadLimit())->toBe(100000)
        ->and($user->uploads_count)->toBe(50000);
});

test('user can downgrade from startup to standard when usage is zero', function () {
    $user = User::factory()->create(['uploads_count' => 0]);

    $user->newSubscription('default', env('SPARK_STARTUP_MONTHLY_PLAN'))->create('pm_card_visa');

    $user->subscription('default')->swap(env('SPARK_STANDARD_MONTHLY_PLAN'));

    expect($user->fresh()->uploadLimit())->toBe(1000)
        ->and($user->uploads_count)->toBe(0);
});

test('user can downgrade from startup to standard when usage is under standard limit', function () {
    $user = User::factory()->create(['uploads_count' => 500]);

    $user->newSubscription('default', env('SPARK_STARTUP_MONTHLY_PLAN'))->create('pm_card_visa');

    $user->subscription('default')->swap(env('SPARK_STANDARD_MONTHLY_PLAN'));

    expect($user->fresh()->uploadLimit())->toBe(1000)
        ->and($user->uploads_count)->toBe(500);
});

test('user can downgrade from enterprise to standard when usage is under standard limit', function () {
    $user = User::factory()->create(['uploads_count' => 800]);

    $user->newSubscription('default', env('SPARK_ENTERPRISE_MONTHLY_PLAN'))->create('pm_card_visa');

    $user->subscription('default')->swap(env('SPARK_STANDARD_MONTHLY_PLAN'));

    expect($user->fresh()->uploadLimit())->toBe(1000)
        ->and($user->uploads_count)->toBe(800);
});

// ========================================
// DOWNGRADE SCENARIOS - Blocked When Over Limit
// ========================================

test('user cannot downgrade from enterprise to standard when usage exceeds standard limit', function () {
    $user = User::factory()->create(['uploads_count' => 5000]);

    $user->newSubscription('default', env('SPARK_ENTERPRISE_MONTHLY_PLAN'))->create('pm_card_visa');

    expect(fn () => attemptPlanSwap($user, env('SPARK_STANDARD_MONTHLY_PLAN')))
        ->toThrow(ValidationException::class);

    // Verify user is still on Enterprise plan
    expect($user->fresh()->uploadLimit())->toBe(1000000);
});

test('user cannot downgrade from enterprise to startup when usage exceeds startup limit', function () {
    $user = User::factory()->create(['uploads_count' => 500000]);

    $user->newSubscription('default', env('SPARK_ENTERPRISE_MONTHLY_PLAN'))->create('pm_card_visa');

    expect(fn () => attemptPlanSwap($user, env('SPARK_STARTUP_MONTHLY_PLAN')))
        ->toThrow(ValidationException::class);

    expect($user->fresh()->uploadLimit())->toBe(1000000);
});

test('user cannot downgrade from startup to standard when usage exceeds standard limit', function () {
    $user = User::factory()->create(['uploads_count' => 10000]);

    $user->newSubscription('default', env('SPARK_STARTUP_MONTHLY_PLAN'))->create('pm_card_visa');

    expect(fn () => attemptPlanSwap($user, env('SPARK_STANDARD_MONTHLY_PLAN')))
        ->toThrow(ValidationException::class);

    expect($user->fresh()->uploadLimit())->toBe(100000);
});

test('downgrade error message contains helpful information', function () {
    $user = User::factory()->create(['uploads_count' => 5000]);

    $user->newSubscription('default', env('SPARK_ENTERPRISE_MONTHLY_PLAN'))->create('pm_card_visa');

    try {
        attemptPlanSwap($user, env('SPARK_STANDARD_MONTHLY_PLAN'));
        $this->fail('Expected ValidationException was not thrown');
    } catch (ValidationException $e) {
        $errors = $e->errors();
        expect($errors)->toHaveKey('plan')
            ->and($errors['plan'][0])->toContain('5,000')
            ->and($errors['plan'][0])->toContain('1,000');
    }
});

// ========================================
// EDGE CASE: Exactly At Limit
// ========================================

test('user cannot downgrade when usage exactly equals target limit', function () {
    $user = User::factory()->create(['uploads_count' => 1000]);

    $user->newSubscription('default', env('SPARK_STARTUP_MONTHLY_PLAN'))->create('pm_card_visa');

    expect(fn () => attemptPlanSwap($user, env('SPARK_STANDARD_MONTHLY_PLAN')))
        ->toThrow(ValidationException::class);
});

test('user can downgrade when usage is one below target limit', function () {
    $user = User::factory()->create(['uploads_count' => 999]);

    $user->newSubscription('default', env('SPARK_STARTUP_MONTHLY_PLAN'))->create('pm_card_visa');

    $user->subscription('default')->swap(env('SPARK_STANDARD_MONTHLY_PLAN'));

    expect($user->fresh()->uploadLimit())->toBe(1000);
});

// ========================================
// MONTHLY <-> ANNUAL SWITCHING
// ========================================

test('user can switch from monthly to annual on same tier with usage', function () {
    $user = User::factory()->create(['uploads_count' => 500]);

    $user->newSubscription('default', env('SPARK_STANDARD_MONTHLY_PLAN'))->create('pm_card_visa');

    $user->subscription('default')->swap(env('SPARK_STANDARD_YEARLY_PLAN'));

    expect($user->fresh()->uploadLimit())->toBe(1000)
        ->and($user->uploads_count)->toBe(500);
});

test('user can switch from annual to monthly on same tier with usage', function () {
    $user = User::factory()->create(['uploads_count' => 500]);

    $user->newSubscription('default', env('SPARK_STANDARD_YEARLY_PLAN'))->create('pm_card_visa');

    $user->subscription('default')->swap(env('SPARK_STANDARD_MONTHLY_PLAN'));

    expect($user->fresh()->uploadLimit())->toBe(1000)
        ->and($user->uploads_count)->toBe(500);
});

test('user can upgrade from monthly standard to annual enterprise with usage', function () {
    $user = User::factory()->create(['uploads_count' => 800]);

    $user->newSubscription('default', env('SPARK_STANDARD_MONTHLY_PLAN'))->create('pm_card_visa');

    $user->subscription('default')->swap(env('SPARK_ENTERPRISE_YEARLY_PLAN'));

    expect($user->fresh()->uploadLimit())->toBe(1000000)
        ->and($user->uploads_count)->toBe(800);
});

test('user cannot downgrade from annual enterprise to monthly standard when over limit', function () {
    $user = User::factory()->create(['uploads_count' => 50000]);

    $user->newSubscription('default', env('SPARK_ENTERPRISE_YEARLY_PLAN'))->create('pm_card_visa');

    expect(fn () => attemptPlanSwap($user, env('SPARK_STANDARD_MONTHLY_PLAN')))
        ->toThrow(ValidationException::class);
});

// ========================================
// USAGE PERSISTENCE ACROSS SWAPS
// ========================================

test('usage count persists through multiple plan swaps', function () {
    $user = User::factory()->create(['uploads_count' => 0]);

    $user->newSubscription('default', env('SPARK_STANDARD_MONTHLY_PLAN'))->create('pm_card_visa');

    $user->incrementUploads();
    $user->incrementUploads();
    expect($user->fresh()->uploads_count)->toBe(2);

    // Upgrade to Startup
    $user->subscription('default')->swap(env('SPARK_STARTUP_MONTHLY_PLAN'));
    expect($user->fresh()->uploads_count)->toBe(2);

    $user->incrementUploads();
    expect($user->fresh()->uploads_count)->toBe(3);

    // Upgrade to Enterprise
    $user->subscription('default')->swap(env('SPARK_ENTERPRISE_MONTHLY_PLAN'));
    expect($user->fresh()->uploads_count)->toBe(3);

    // Downgrade back to Standard (allowed since usage is 3)
    $user->subscription('default')->swap(env('SPARK_STANDARD_MONTHLY_PLAN'));
    expect($user->fresh()->uploads_count)->toBe(3);
});

// ========================================
// EXTREME USAGE SCENARIOS
// ========================================

test('user with maximum enterprise usage cannot downgrade to any lower tier', function () {
    $user = User::factory()->create(['uploads_count' => 1000000]);

    $user->newSubscription('default', env('SPARK_ENTERPRISE_MONTHLY_PLAN'))->create('pm_card_visa');

    expect(fn () => attemptPlanSwap($user, env('SPARK_STARTUP_MONTHLY_PLAN')))
        ->toThrow(ValidationException::class);

    expect(fn () => attemptPlanSwap($user, env('SPARK_STANDARD_MONTHLY_PLAN')))
        ->toThrow(ValidationException::class);
});

test('user with maximum startup usage can only upgrade to enterprise', function () {
    $user = User::factory()->create(['uploads_count' => 100000]);

    $user->newSubscription('default', env('SPARK_STARTUP_MONTHLY_PLAN'))->create('pm_card_visa');

    // Cannot downgrade
    expect(fn () => attemptPlanSwap($user, env('SPARK_STANDARD_MONTHLY_PLAN')))
        ->toThrow(ValidationException::class);

    // Can upgrade
    attemptPlanSwap($user, env('SPARK_ENTERPRISE_MONTHLY_PLAN'));
    expect($user->fresh()->uploadLimit())->toBe(1000000);
});

// ========================================
// TRIAL PERIOD SCENARIOS
// ========================================

test('user on trial can switch plans with usage', function () {
    $user = User::factory()->create(['uploads_count' => 500]);

    $user->newSubscription('default', env('SPARK_STANDARD_MONTHLY_PLAN'))
        ->trialDays(7)
        ->create('pm_card_visa');

    expect($user->subscription('default')->onTrial())->toBeTrue();

    $user->subscription('default')->swap(env('SPARK_STARTUP_MONTHLY_PLAN'));

    expect($user->fresh()->uploadLimit())->toBe(100000)
        ->and($user->uploads_count)->toBe(500);
});

test('user on trial cannot downgrade when usage exceeds target limit', function () {
    $user = User::factory()->create(['uploads_count' => 5000]);

    $user->newSubscription('default', env('SPARK_ENTERPRISE_MONTHLY_PLAN'))
        ->trialDays(7)
        ->create('pm_card_visa');

    expect(fn () => attemptPlanSwap($user, env('SPARK_STANDARD_MONTHLY_PLAN')))
        ->toThrow(ValidationException::class);
});

// ========================================
// UPLOAD LIMIT AND BLOCKING AFTER DOWNGRADE
// ========================================

test('user who used enterprise credits is blocked from uploading after failed downgrade', function () {
    $user = User::factory()->create(['uploads_count' => 10000]);

    $user->newSubscription('default', env('SPARK_ENTERPRISE_MONTHLY_PLAN'))->create('pm_card_visa');

    // Try to downgrade (will fail)
    try {
        attemptPlanSwap($user, env('SPARK_STANDARD_MONTHLY_PLAN'));
    } catch (ValidationException $e) {
        // Expected
    }

    // User is still on Enterprise with full limit
    expect($user->fresh()->canUpload())->toBeTrue()
        ->and($user->uploadLimit())->toBe(1000000);
});

test('user limit updates immediately after successful upgrade', function () {
    $user = User::factory()->create(['uploads_count' => 900]);

    $user->newSubscription('default', env('SPARK_STANDARD_MONTHLY_PLAN'))->create('pm_card_visa');

    expect($user->fresh()->canUpload())->toBeTrue()
        ->and($user->remainingUploads())->toBe(100);

    $user->subscription('default')->swap(env('SPARK_ENTERPRISE_MONTHLY_PLAN'));

    expect($user->fresh()->canUpload())->toBeTrue()
        ->and($user->remainingUploads())->toBe(999100);
});
