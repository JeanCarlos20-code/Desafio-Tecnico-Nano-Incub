<?php

namespace App\Modules\User\Infra\Http\Requests;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Informe seu e-mail.',
            'email.email' => 'Informe um endereço de e-mail válido.',
            'password.required' => 'Informe sua senha.',
        ];
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        throw ValidationException::withMessages([
            'credentials' => 'E-mail ou senha inválidos.',
        ]);
    }

    public function hit(): void
    {
        RateLimiter::hit($this->throttleKey(), 60);
    }

    public function clear(): void
    {
        RateLimiter::clear($this->throttleKey());
    }

    public function throttleKey(): string
    {
        return Str::transliterate($this->string('email').'|'.$this->ip());
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (! is_string($email)) {
            return;
        }

        $this->merge([
            'email' => Str::lower(trim($email)),
        ]);
    }
}
