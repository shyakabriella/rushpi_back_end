<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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
         * Always return the same response so people cannot
         * discover which email addresses are registered.
         */
        if (! $user instanceof User) {
            return $this->forgotResponse();
        }

        $token = Password::broker()
            ->createToken($user);

        $frontendUrl = rtrim(
            (string) config(
                'app.frontend_url',
                env(
                    'FRONTEND_URL',
                    'https://rushpi.asyncafrica.com'
                )
            ),
            '/'
        );

        $resetUrl = $frontendUrl
            .'/reset-password?'
            .http_build_query([
                'token' => $token,
                'email' => $email,
            ]);

        Mail::html(
            $this->emailContent(
                $user->name,
                $resetUrl
            ),
            function (Message $message) use (
                $email
            ): void {
                $message
                    ->to($email)
                    ->subject(
                        'Reset your RushPi password'
                    );
            }
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
                'email' =>
                    Str::lower(
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
                 * Revoke old sessions after a password reset.
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

    private function emailContent(
        string $name,
        string $resetUrl
    ): string {
        $safeName = e($name);
        $safeUrl = e($resetUrl);

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Reset your RushPi password</title>
        </head>
        <body style="margin:0;background:#f1f5f9;font-family:Arial,sans-serif;color:#0f172a;">
            <div style="max-width:600px;margin:0 auto;padding:32px 16px;">
                <div style="border-radius:20px;background:#ffffff;padding:32px;box-shadow:0 15px 40px rgba(15,23,42,.08);">
                    <h1 style="margin:0;color:#0754d8;font-size:28px;">
                        RushPi
                    </h1>

                    <h2 style="margin:28px 0 12px;font-size:22px;">
                        Reset your password
                    </h2>

                    <p style="line-height:1.7;color:#475569;">
                        Hello {$safeName},
                    </p>

                    <p style="line-height:1.7;color:#475569;">
                        We received a request to reset your RushPi account password.
                    </p>

                    <a
                        href="{$safeUrl}"
                        style="display:inline-block;margin:18px 0;border-radius:999px;background:#0754d8;padding:14px 24px;color:#ffffff;text-decoration:none;font-weight:700;"
                    >
                        Reset password
                    </a>

                    <p style="line-height:1.7;color:#64748b;font-size:14px;">
                        If you did not request this change, you can safely ignore this email.
                    </p>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }
}
