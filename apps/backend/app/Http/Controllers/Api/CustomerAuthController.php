<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CustomerLoginRequest;
use App\Http\Requests\Api\CustomerRegisterRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Token-based customer authentication (see CustomerLoginRequest for why this
 * differs from the admin panel's session cookie). There is no server-side
 * session to invalidate on logout: revoking the Sanctum token is the whole
 * story.
 */
class CustomerAuthController extends Controller
{
    public function register(CustomerRegisterRequest $request): JsonResponse
    {
        $customer = Customer::create([
            ...$request->safe()->only(['name', 'email', 'phone', 'document_type', 'document_number']),
            'password' => Hash::make($request->string('password')),
        ]);

        return $this->tokenResponse($customer, 201);
    }

    public function login(CustomerLoginRequest $request): JsonResponse
    {
        return $this->tokenResponse($request->authenticatedCustomer());
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user('customer')?->currentAccessToken()?->delete();

        // The sanctum guard caches the user it resolved from the token; without
        // this, a worker process (or, in tests, later calls in the same test)
        // would keep authenticating the token this just deleted. Same reasoning
        // as Admin\AuthController::logout().
        Auth::forgetGuards();

        return response()->json(null, 204);
    }

    public function me(Request $request): CustomerResource
    {
        return CustomerResource::make($request->user('customer'));
    }

    private function tokenResponse(Customer $customer, int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => CustomerResource::make($customer)->resolve(),
            'token' => $customer->createToken('storefront')->plainTextToken,
        ], $status);
    }
}
