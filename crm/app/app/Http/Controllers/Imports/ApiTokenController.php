<?php

namespace App\Http\Controllers\Imports;

use App\Http\Controllers\Controller;
use App\Services\Leads\IntegrationUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Owner → API keys. Keys belong to the switched-off "Website & ads (API)" login and can only
 * create leads (ability leads:create). The full key is shown once, right after it's made.
 */
class ApiTokenController extends Controller
{
    public const ABILITIES = ['leads:create'];

    public function index(): View
    {
        $user = IntegrationUser::get();

        return view('imports.api-tokens.index', [
            'tokens' => $user->tokens()->latest('id')->get(),
            'plain' => session('api_token_plain'),
            'plainName' => session('api_token_name'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
        ], ['name.required' => 'Give the key a name, like "Website form".']);

        $token = IntegrationUser::get()->createToken($data['name'], self::ABILITIES);
        activity('api_keys')->causedBy($request->user())->performedOn($token->accessToken)->event('created')->log('API key created: '.$data['name']);

        return redirect()->route('api-tokens.index')
            ->with('api_token_plain', $token->plainTextToken)
            ->with('api_token_name', $data['name']);
    }

    public function destroy(Request $request, int $token): RedirectResponse
    {
        $row = IntegrationUser::get()->tokens()->whereKey($token)->firstOrFail();
        $name = $row->name;
        activity('api_keys')->causedBy($request->user())->event('revoked')->withProperties(['token_id' => $row->id])->log('API key revoked: '.$name);
        $row->delete();

        return redirect()->route('api-tokens.index')->with('status', 'Key "'.$name.'" switched off. Anything still using it now gets "401 Unauthenticated".');
    }
}
