<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class RegisterController extends BaseController
{
    /**
     * Register API
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:30|unique:users,phone',
            'password' => 'required|string|min:6',
            'c_password' => 'required|same:password',
            'address' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        /**
         * IMPORTANT:
         * Public registration is only for customers.
         * Admin users should be created by seeder or directly by system owner.
         */
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => $request->password,
            'role' => User::ROLE_CUSTOMER,
            'status' => User::STATUS_ACTIVE,
            'address' => $request->address,
        ]);

        $success = [
            'token' => $user->createToken('KamElectronicsToken')->plainTextToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'status' => $user->status,
                'address' => $user->address,
            ],
        ];

        return $this->sendResponse($success, 'User registered successfully.');
    }

    /**
     * Login API
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        if (! Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            return $this->sendError('Unauthorised.', [
                'error' => 'Invalid email or password.',
            ]);
        }

        $user = Auth::user();

        if ($user->status !== User::STATUS_ACTIVE) {
            return $this->sendError('Account Disabled.', [
                'error' => 'Your account is not active. Please contact support.',
            ]);
        }

        $success = [
            'token' => $user->createToken('KamElectronicsToken')->plainTextToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'status' => $user->status,
                'address' => $user->address,
            ],
        ];

        return $this->sendResponse($success, 'User logged in successfully.');
    }

    /**
     * Get logged-in user
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        $success = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'status' => $user->status,
                'address' => $user->address,
            ],
        ];

        return $this->sendResponse($success, 'User profile fetched successfully.');
    }

    /**
     * Logout API
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->sendResponse([], 'User logged out successfully.');
    }
}