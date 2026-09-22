<?php

namespace App\Http\Requests\Api;

use App\Models\Customer;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Token-based, unlike the admin panel's session cookie (docs/decisions.md):
 * the storefront is public-first and a customer may never share the panel's
 * parent domain, so there is no SPA session to piggyback on. The order
 * checkout flow already authenticates guests-with-a-token this same way (see
 * OrdersStoreTest), this just adds how the token is first obtained.
 */
class CustomerLoginRequest extends FormRequest
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
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Every failure reason answers with the same message on purpose — see
     * LoginRequest (admin): telling a caller apart between "no such email" and
     * "wrong password" turns this endpoint into an account enumerator.
     *
     * @throws ValidationException
     */
    public function authenticatedCustomer(): Customer
    {
        $this->ensureIsNotRateLimited();

        $customer = Customer::query()->where('email', $this->string('email'))->first();

        if ($customer === null || $customer->password === null || ! Hash::check($this->string('password'), $customer->password)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => ['Las credenciales no son válidas.'],
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        return $customer;
    }

    /**
     * @throws ValidationException
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), maxAttempts: 5)) {
            return;
        }

        Event::dispatch(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => ["Demasiados intentos fallidos. Intenta de nuevo en {$seconds} segundos."],
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower((string) $this->input('email')).'|'.$this->ip());
    }
}
