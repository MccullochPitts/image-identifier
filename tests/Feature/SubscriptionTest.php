<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Set up Stripe test mode
    config(['cashier.key' => env('STRIPE_KEY')]);
    config(['cashier.secret' => env('STRIPE_SECRET')]);
});

test('user can subscribe to standard plan with monthly billing', function () {
    $user = User::factory()->create();

    // Create subscription using Stripe test token
    $user->newSubscription('default', env('SPARK_STANDARD_MONTHLY_PLAN'))
        ->create('pm_card_visa');

    expect($user->subscribed('default'))->toBeTrue()
        ->and($user->subscription('default')->stripe_price)->toBe(env('SPARK_STANDARD_MONTHLY_PLAN'))
        ->and($user->subscription('default')->active())->toBeTrue();
});

test('user can subscribe to standard plan with yearly billing', function () {
    $user = User::factory()->create();

    $user->newSubscription('default', env('SPARK_STANDARD_YEARLY_PLAN'))
        ->create('pm_card_visa');

    expect($user->subscribed('default'))->toBeTrue()
        ->and($user->subscription('default')->stripe_price)->toBe(env('SPARK_STANDARD_YEARLY_PLAN'))
        ->and($user->subscription('default')->active())->toBeTrue();
});

test('user can subscribe with trial period', function () {
    $user = User::factory()->create();

    $user->newSubscription('default', env('SPARK_STANDARD_MONTHLY_PLAN'))
        ->trialDays(7)
        ->create('pm_card_visa');

    expect($user->subscribed('default'))->toBeTrue()
        ->and($user->subscription('default')->onTrial())->toBeTrue()
        ->and($user->subscription('default')->trial_ends_at)->not->toBeNull();
});

test('user can cancel subscription', function () {
    $user = User::factory()->create();

    $user->newSubscription('default', env('SPARK_STANDARD_MONTHLY_PLAN'))
        ->create('pm_card_visa');

    expect($user->subscribed('default'))->toBeTrue();

    // Cancel subscription
    $user->subscription('default')->cancel();

    expect($user->subscription('default')->canceled())->toBeTrue()
        ->and($user->subscription('default')->onGracePeriod())->toBeTrue()
        ->and($user->subscription('default')->ends_at)->not->toBeNull();
});

test('user can cancel subscription immediately', function () {
    $user = User::factory()->create();

    $user->newSubscription('default', env('SPARK_STANDARD_MONTHLY_PLAN'))
        ->create('pm_card_visa');

    // Cancel immediately
    $user->subscription('default')->cancelNow();

    expect($user->subscription('default')->canceled())->toBeTrue()
        ->and($user->subscription('default')->onGracePeriod())->toBeFalse()
        ->and($user->subscribed('default'))->toBeFalse();
});

test('user can resume canceled subscription', function () {
    $user = User::factory()->create();

    $user->newSubscription('default', env('SPARK_STANDARD_MONTHLY_PLAN'))
        ->create('pm_card_visa');

    // Cancel subscription
    $user->subscription('default')->cancel();
    expect($user->subscription('default')->canceled())->toBeTrue();

    // Resume subscription
    $user->subscription('default')->resume();

    expect($user->subscription('default')->canceled())->toBeFalse()
        ->and($user->subscription('default')->active())->toBeTrue()
        ->and($user->subscription('default')->ends_at)->toBeNull();
});

test('user gets correct upload limit for standard plan', function () {
    $user = User::factory()->create();

    $user->newSubscription('default', env('SPARK_STANDARD_MONTHLY_PLAN'))
        ->create('pm_card_visa');

    expect($user->uploadLimit())->toBe(1000);
});

test('user without subscription has zero upload limit', function () {
    $user = User::factory()->create();

    expect($user->uploadLimit())->toBe(0)
        ->and($user->canUpload())->toBeFalse();
});

test('user can upload when under limit', function () {
    $user = User::factory()->create([
        'uploads_count' => 500,
    ]);

    $user->newSubscription('default', env('SPARK_STANDARD_MONTHLY_PLAN'))
        ->create('pm_card_visa');

    expect($user->canUpload())->toBeTrue()
        ->and($user->remainingUploads())->toBe(500);
});

test('user cannot upload when limit reached', function () {
    $user = User::factory()->create([
        'uploads_count' => 1000,
    ]);

    $user->newSubscription('default', env('SPARK_STANDARD_MONTHLY_PLAN'))
        ->create('pm_card_visa');

    expect($user->canUpload())->toBeFalse()
        ->and($user->remainingUploads())->toBe(0);
});

test('upload count increments correctly', function () {
    $user = User::factory()->create([
        'uploads_count' => 0,
    ]);

    $user->newSubscription('default', env('SPARK_STANDARD_MONTHLY_PLAN'))
        ->create('pm_card_visa');

    expect($user->uploads_count)->toBe(0);

    $user->incrementUploads();

    expect($user->fresh()->uploads_count)->toBe(1);
});

test('user can check subscription status', function () {
    $user = User::factory()->create();

    expect($user->subscribed('default'))->toBeFalse();

    $user->newSubscription('default', env('SPARK_STANDARD_MONTHLY_PLAN'))
        ->create('pm_card_visa');

    // Refresh user to get updated subscription
    $user = $user->fresh();

    expect($user->subscribed('default'))->toBeTrue()
        ->and($user->subscribedToPrice(env('SPARK_STANDARD_MONTHLY_PLAN'), 'default'))->toBeTrue()
        ->and($user->subscription('default')->active())->toBeTrue();
});

test('subscription has correct billing cycle', function () {
    $user = User::factory()->create();

    $user->newSubscription('default', env('SPARK_STANDARD_MONTHLY_PLAN'))
        ->create('pm_card_visa');

    $subscription = $user->subscription('default');

    expect($subscription->recurring())->toBeTrue()
        ->and($subscription->stripe_price)->toBe(env('SPARK_STANDARD_MONTHLY_PLAN'));
});

// Startup Plan Tests
test('user can subscribe to startup plan with monthly billing', function () {
    $user = User::factory()->create();

    $user->newSubscription('default', env('SPARK_STARTUP_MONTHLY_PLAN'))
        ->create('pm_card_visa');

    expect($user->subscribed('default'))->toBeTrue()
        ->and($user->subscription('default')->stripe_price)->toBe(env('SPARK_STARTUP_MONTHLY_PLAN'))
        ->and($user->subscription('default')->active())->toBeTrue();
});

test('user gets correct upload limit for startup plan', function () {
    $user = User::factory()->create();

    $user->newSubscription('default', env('SPARK_STARTUP_MONTHLY_PLAN'))
        ->create('pm_card_visa');

    expect($user->uploadLimit())->toBe(100000);
});

// Enterprise Plan Tests
test('user can subscribe to enterprise plan with monthly billing', function () {
    $user = User::factory()->create();

    $user->newSubscription('default', env('SPARK_ENTERPRISE_MONTHLY_PLAN'))
        ->create('pm_card_visa');

    expect($user->subscribed('default'))->toBeTrue()
        ->and($user->subscription('default')->stripe_price)->toBe(env('SPARK_ENTERPRISE_MONTHLY_PLAN'))
        ->and($user->subscription('default')->active())->toBeTrue();
});

test('user gets correct upload limit for enterprise plan', function () {
    $user = User::factory()->create();

    $user->newSubscription('default', env('SPARK_ENTERPRISE_MONTHLY_PLAN'))
        ->create('pm_card_visa');

    expect($user->uploadLimit())->toBe(1000000);
});

test('user can swap from standard to startup plan', function () {
    $user = User::factory()->create();

    $user->newSubscription('default', env('SPARK_STANDARD_MONTHLY_PLAN'))
        ->create('pm_card_visa');

    expect($user->uploadLimit())->toBe(1000);

    // Swap to Startup plan
    $user->subscription('default')->swap(env('SPARK_STARTUP_MONTHLY_PLAN'));

    expect($user->fresh()->uploadLimit())->toBe(100000)
        ->and($user->subscription('default')->stripe_price)->toBe(env('SPARK_STARTUP_MONTHLY_PLAN'));
});

test('user can swap from startup to enterprise plan', function () {
    $user = User::factory()->create();

    $user->newSubscription('default', env('SPARK_STARTUP_MONTHLY_PLAN'))
        ->create('pm_card_visa');

    expect($user->uploadLimit())->toBe(100000);

    // Swap to Enterprise plan
    $user->subscription('default')->swap(env('SPARK_ENTERPRISE_MONTHLY_PLAN'));

    expect($user->fresh()->uploadLimit())->toBe(1000000)
        ->and($user->subscription('default')->stripe_price)->toBe(env('SPARK_ENTERPRISE_MONTHLY_PLAN'));
});
