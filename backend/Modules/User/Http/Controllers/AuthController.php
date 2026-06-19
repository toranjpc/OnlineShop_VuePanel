<?php

namespace Modules\User\Http\Controllers;

use Modules\User\Models\User;
use Modules\User\Models\ExtData;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        try {
            $data = $request->validate([
                'name'     => ['required', 'string', 'max:255'],
                'mobile'   => ['required', 'digits_between:10,15', 'unique:users,mobile'],
                'password' => ['required', 'string', 'min:6', 'confirmed'],
            ]);

            $user = User::create([
                'name'     => $data['name'],
                'mobile'   => $data['mobile'],
                'password' => Hash::make($data['password']),
            ]);

            $token = $user->createToken('auth')->plainTextToken;

            return response()->json([
                'status' => true,
                'user'   => $user,
                'token'  => $token,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'اطلاعات وارد شده نامعتبر است.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Register error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'خطا در ثبت نام. لطفاً دوباره تلاش کنید.',
            ], 500);
        }
    }

    public function login(Request $request)
    {
        try {
            $data = $request->validate([
                'mobile'   => ['required', 'digits_between:10,15'],
                'password' => ['required', 'string'],
                'remember' => ['sometimes', 'boolean'],
            ]);

            $user = User::with('jobOption:id,title')->where('mobile', $data['mobile'])->first();

            // Log::info('Login attempt', [
            //     'mobile' => $data['mobile'],
            //     'user_exists' => $user ? true : false,
            //     'ip' => $request->ip(),
            // ]);

            if (!$user || !Hash::check($data['password'], $user->password)) {
                return response()->json([
                    'status'  => false,
                    'message' => 'اطلاعات ورود اشتباه است.',
                ], 401);
            }

            $remember = $request->boolean('remember');
            $user->tokens()->delete();
            $expiresAt = $remember ? now()->addDays(30) : now()->addHours(8);
            $token = $user->createToken('auth', ['*'], $expiresAt)->plainTextToken;

            $lastSeen = now()->timestamp;
            ExtData::create([
                'kind' => 'login',
                'f_id' => (int) $user->id,
                'title' => 'کاربر از طریق لاگین آنلاین شد',
                'datas' => [
                    'last_seen' => date('Y-m-d H:i:s', $lastSeen),
                    'last_seen_shamsi' => jdate($lastSeen)->format('Y-m-d H:i:s'),
                    // 'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ],
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'status' => true,
                'user'   => $user,
                'token'  => $token,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'اطلاعات وارد شده نامعتبر است.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Login error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'خطا در ورود. لطفاً دوباره تلاش کنید.',
            ], 500);
        }
    }

    public function me(Request $request)
    {
        try {
            $user = $request->user()->loadMissing('jobOption:id,title');

            return response()->json([
                'status' => true,
                'user'   => $user,
            ]);
        } catch (\Exception $e) {
            Log::error('Me error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'خطا در دریافت اطلاعات کاربر.',
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'status'  => true,
                'message' => 'با موفقیت خارج شدید.',
            ]);
        } catch (\Exception $e) {
            Log::error('Logout error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'خطا در خروج.',
            ], 500);
        }
    }

    public function resetPassword(Request $request)
    {
        try {
            $data = $request->validate([
                'mobile'   => ['required', 'digits_between:10,15'],
                'password' => ['required', 'string', 'min:6', 'confirmed'],
            ]);

            $user = User::where('mobile', $data['mobile'])->first();

            if (!$user) {
                return response()->json([
                    'status'  => false,
                    'message' => 'کاربر یافت نشد.',
                ], 404);
            }

            $user->update([
                'password' => Hash::make($data['password']),
                'remember_token' => Str::random(60),
            ]);

            return response()->json([
                'status'  => true,
                'message' => 'رمز عبور با موفقیت تغییر کرد.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'اطلاعات وارد شده نامعتبر است.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Reset password error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'خطا در تغییر رمز عبور.',
            ], 500);
        }
    }

    public function realtimeStream(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'کاربر احراز هویت نشده است.',
            ], 401);
        }

        $key = "users:last_seen";
        return response()->stream(function () use ($key, $user) {
            // Keep this SSE loop bounded so PHP never hits max_execution_time.
            // With weak servers + many concurrent connections, an "infinite" loop is risky.
            $start = microtime(true);
            $maxLifetimeSeconds = 20; // safety buffer under default 30s
            $heartbeatIntervalUs = 15000000; // 15s

            // SSE reconnect hint (EventSource supports "retry: <ms>")
            echo "retry: 3000\n\n";
            @ob_flush();
            flush();

            while (!connection_aborted()) {
                $elapsedSeconds = microtime(true) - $start;
                if ($elapsedSeconds >= $maxLifetimeSeconds) {
                    break;
                }

                Redis::zadd($key, [$user->id => now()->timestamp]);
                echo "data: heartbeat\n\n";
                @ob_flush();
                flush();

                // Don't sleep past the max lifetime (prevents hitting max_execution_time during usleep).
                if (($elapsedSeconds + ($heartbeatIntervalUs / 1000000)) >= $maxLifetimeSeconds) {
                    break;
                }

                usleep($heartbeatIntervalUs);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
