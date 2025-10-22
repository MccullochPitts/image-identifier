<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreApiTokenRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApiTokenController extends Controller
{
    /**
     * Show the API tokens management page.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('api/tokens', [
            'tokens' => $request->user()->tokens->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'last_used_at' => $token->last_used_at?->toISOString(),
                'created_at' => $token->created_at->toISOString(),
            ]),
            'token' => session('token'),
            'tokenName' => session('tokenName'),
        ]);
    }

    /**
     * Create a new API token.
     */
    public function store(StoreApiTokenRequest $request): RedirectResponse
    {
        $token = $request->user()->createToken($request->validated('name'));

        return to_route('api-tokens.index')->with([
            'token' => $token->plainTextToken,
            'tokenName' => $request->validated('name'),
        ]);
    }

    /**
     * Delete an API token.
     */
    public function destroy(Request $request, string $tokenId): RedirectResponse
    {
        $request->user()->tokens()->where('id', $tokenId)->delete();

        return to_route('api-tokens.index');
    }
}
