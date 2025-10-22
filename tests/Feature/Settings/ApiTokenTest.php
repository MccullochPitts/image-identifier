<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('api tokens page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('api-tokens.index'));

    $response->assertOk();
});

test('user can create an api token', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('api-tokens.store'), [
            'name' => 'Test Token',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('api-tokens.index'))
        ->assertSessionHas('token')
        ->assertSessionHas('tokenName', 'Test Token');

    expect($user->tokens()->count())->toBe(1);
    expect($user->tokens->first()->name)->toBe('Test Token');
});

test('token name is required', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('api-tokens.index'))
        ->post(route('api-tokens.store'), [
            'name' => '',
        ]);

    $response
        ->assertSessionHasErrors('name')
        ->assertRedirect(route('api-tokens.index'));

    expect($user->tokens()->count())->toBe(0);
});

test('token name must be unique per user', function () {
    $user = User::factory()->create();

    $user->createToken('Duplicate Token');

    $response = $this
        ->actingAs($user)
        ->from(route('api-tokens.index'))
        ->post(route('api-tokens.store'), [
            'name' => 'Duplicate Token',
        ]);

    $response
        ->assertSessionHasErrors('name')
        ->assertRedirect(route('api-tokens.index'));

    expect($user->fresh()->tokens()->count())->toBe(1);
});

test('different users can have tokens with the same name', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $user1->createToken('Shared Name');

    $response = $this
        ->actingAs($user2)
        ->post(route('api-tokens.store'), [
            'name' => 'Shared Name',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('api-tokens.index'));

    expect($user2->fresh()->tokens()->count())->toBe(1);
    expect($user2->tokens->first()->name)->toBe('Shared Name');
});

test('user can delete their own api token', function () {
    $user = User::factory()->create();

    $token = $user->createToken('Test Token');

    expect($user->tokens()->count())->toBe(1);

    $response = $this
        ->actingAs($user)
        ->delete(route('api-tokens.destroy', $token->accessToken->id));

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('api-tokens.index'));

    expect($user->fresh()->tokens()->count())->toBe(0);
});

test('created token can authenticate api requests', function () {
    $user = User::factory()->create();

    $token = $user->createToken('Test Token');

    $response = $this
        ->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
        ->getJson('/api/v1/images');

    $response->assertOk();
});

test('user can only see their own tokens', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $user1->createToken('User 1 Token');
    $user2->createToken('User 2 Token');

    $response = $this
        ->actingAs($user1)
        ->get(route('api-tokens.index'));

    $response->assertOk();

    $tokens = $response->viewData('page')['props']['tokens'];

    expect($tokens)->toHaveCount(1);
    expect($tokens[0]['name'])->toBe('User 1 Token');
});

test('user cannot delete another users token', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $token = $user2->createToken('User 2 Token');

    expect($user2->tokens()->count())->toBe(1);

    $response = $this
        ->actingAs($user1)
        ->delete(route('api-tokens.destroy', $token->accessToken->id));

    $response->assertRedirect(route('api-tokens.index'));

    // Token should still exist
    expect($user2->fresh()->tokens()->count())->toBe(1);
});

test('guest cannot access api tokens page', function () {
    $response = $this->get(route('api-tokens.index'));

    $response->assertRedirect(route('login'));
});

test('guest cannot create api tokens', function () {
    $response = $this->post(route('api-tokens.store'), [
        'name' => 'Test Token',
    ]);

    $response->assertRedirect(route('login'));
});
