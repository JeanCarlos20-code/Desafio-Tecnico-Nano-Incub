<?php

namespace App\Modules\User\Infra\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Application\Errors\InvalidCredentials;
use App\Modules\User\Application\UseCases\AuthenticateUser;
use App\Modules\User\Infra\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('User/Login');
    }

    public function store(LoginRequest $request, AuthenticateUser $authenticateUser): RedirectResponse
    {
        $request->ensureIsNotRateLimited();

        $credentials = $request->validated();

        try {
            $authenticateUser->execute($credentials['email'], $credentials['password']);
        } catch (InvalidCredentials) {
            $request->hit();

            throw ValidationException::withMessages([
                'credentials' => 'E-mail ou senha inválidos.',
            ]);
        }

        $request->clear();
        $request->session()->regenerate();

        return redirect()->intended(route('reservations.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
