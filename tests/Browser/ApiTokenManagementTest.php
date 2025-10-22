<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('user can create and delete api tokens through ui', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $page = visit('/settings/api-tokens');

    $page->assertSee('API Tokens')
        ->assertSee('Manage API tokens for programmatic access to your account')
        ->assertSee('No API tokens yet')
        ->assertNoJavascriptErrors();

    // Create a token
    $page->fill('[name="name"]', 'My Test Token')
        ->click('button[type="submit"]');

    // Should show the token in a dialog
    $page->waitFor('[role="dialog"]', 5)
        ->assertSee('Token Created Successfully')
        ->assertSee('My Test Token')
        ->assertSee("Make sure to copy your API token now. You won't be able to see it again!")
        ->assertNoJavascriptErrors();

    // Close the dialog
    $page->click('button:has-text("Done")');

    // Should see the token in the list
    $page->waitForText('My Test Token', 5)
        ->assertSee('Created')
        ->assertDontSee('No API tokens yet')
        ->assertNoJavascriptErrors();

    // Delete the token
    $page->click('[aria-label="Delete token"]')
        ->acceptDialog() // Confirm deletion
        ->waitForText('No API tokens yet', 5)
        ->assertDontSee('My Test Token')
        ->assertNoJavascriptErrors();
});

test('api tokens page is accessible from settings navigation', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $page = visit('/settings/profile');

    $page->assertSee('Settings')
        ->click('a:has-text("API Tokens")');

    $page->assertSee('API Tokens')
        ->assertSee('Manage API tokens for programmatic access to your account')
        ->assertNoJavascriptErrors();
});
