<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

final class PasswordResetController extends Controller
{
    public function forgot(
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'email' => [
                'required',
                'email',
                'max:255',
            ],
        ]);

        $email = Str::lower(
            trim((string) $validated['email'])
        );

        $user = User::query()
            ->where('email', $email)
            ->first();

        /*
         * Use a generic response to avoid revealing whether
         * a particular email is registered.
         */
        if (! $user instanceof User) {
            return $this->forgotResponse();
        }

        $token = Password::broker()
            ->createToken($user);

        $frontendUrl = rtrim(
            (string) config(
                'app.frontend_url',
                'https://rushpi.asyncafrica.com'
            ),
            '/'
        );

        $resetUrl = $frontendUrl
            .'/reset-password?'
            .http_build_query([
                'token' => $token,
                'email' => $email,
            ]);

        $user->notify(
            new ResetPasswordNotification(
                $resetUrl
            )
        );

        return $this->forgotResponse();
    }

    public function reset(
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'email' => [
                'required',
                'email',
                'max:255',
            ],
            'token' => [
                'required',
                'string',
            ],
            'password' => [
                'required',
                'confirmed',
                PasswordRule::min(8),
            ],
        ]);

        $status = Password::broker()->reset(
            [
                'email' => Str::lower(
                    trim(
                        (string) $validated['email']
                    )
                ),
                'token' =>
                    (string) $validated['token'],
                'password' =>
                    (string) $validated['password'],
                'password_confirmation' =>
                    (string)
                        $validated[
                            'password_confirmation'
                        ],
            ],
            function (
                User $user,
                string $password
            ): void {
                $user->forceFill([
                    'password' =>
                        Hash::make($password),
                    'remember_token' =>
                        Str::random(60),
                ])->save();

                /*
                 * Sign out existing API sessions after the
                 * password has been changed.
                 */
                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'success' => false,
                'message' => __($status),
                'errors' => [
                    'email' => [
                        __($status),
                    ],
                ],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' =>
                'Your password has been reset successfully. You can now sign in.',
        ]);
    }

    private function forgotResponse(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' =>
                'If an account exists for this email, password-reset instructions have been sent.',
        ]);
    }
}
