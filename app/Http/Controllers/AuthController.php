<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{

    protected function getCookieSettings()
    {
        $isProduction = app()->environment('production');
        
        if ($isProduction) {
            $domain = parse_url(config('app.url'), PHP_URL_HOST);
            // Remove 'www.' if present
            $domain = preg_replace('/^www\./i', '', $domain);
            
            // Extract the main domain
            $parts = explode('.', $domain);
            if (count($parts) > 2) {
                array_shift($parts); // Remove subdomain
                $domain = '.' . implode('.', $parts); // Prepend with dot for cross-subdomain support
            } else {
                $domain = '.' . $domain;
            }

            return [
                'path' => '/',
                'domain' => null, // Let the browser handle the domain
                'secure' => true,
                'httponly' => true,
                'samesite' => 'None', // Must be 'None' for cross-site requests in production
            ];
        }

        // Development settings
        return [
            'path' => '/',
            'domain' => null,     // null works for localhost
            'secure' => false,    // false for http in development
            'httponly' => true,   // keep cookies http-only
            'samesite' => 'Lax', // Lax is fine for development
        ];
    }

    public function register(Request $request) 
    {
        $request->validate([
            'username' => 'required|string|max:255|unique:users',
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'username' => $request->username,
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Generate both access and refresh tokens
        $accessToken = Auth::claims(['type' => 'access'])
            ->setTTL(15)        // 15 minutes
            ->login($user);
        
        $refreshToken = Auth::claims(['type' => 'refresh'])
            ->setTTL(10080)     // 7 days
            ->login($user);

        $cookieSettings = $this->getCookieSettings();

        // Set HTTP-only cookies with proper domain settings
        $accessCookie = cookie(
            'access_token',
            $accessToken,
            15,
            $cookieSettings['path'],
            $cookieSettings['domain'],
            $cookieSettings['secure'],
            $cookieSettings['httponly'],
            false,
            $cookieSettings['samesite']
        );

        $refreshCookie = cookie(
            'refresh_token',
            $refreshToken,
            10080,
            $cookieSettings['path'],
            $cookieSettings['domain'],
            $cookieSettings['secure'],
            $cookieSettings['httponly'],
            false,
            $cookieSettings['samesite']
        );

        return response()->json([
            'status' => 'success',
            'message' => 'User registered successfully',
            'user' => $user,
            'authorization' => [
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'type' => 'bearer',
            ],
        ])->withCookie($accessCookie)->withCookie($refreshCookie);
    }

    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            // Custom validation messages
            ], [
            'login.required' => 'Please provide your username or email address.',
            'login.string' => 'Login must be a text value.',
            'password' => 'required|string',
        ]);

        // Check if login is email or username
        $loginField = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $credentials = [
            $loginField => $request->login,
            'password' => $request->password
        ];

        if (!$token = Auth::attempt($credentials)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials',
            ], 401);
        }

        // Generate both access and refresh tokens
        $accessToken = Auth::claims(['type' => 'access'])->setTTL(15)->attempt($credentials);
        $refreshToken = Auth::claims(['type' => 'refresh'])->setTTL(10080)->attempt($credentials);

        $cookieSettings = $this->getCookieSettings();

        // Set HTTP-only cookies with proper domain settings
        $accessCookie = cookie(
            'access_token',
            $accessToken,
            15,
            $cookieSettings['path'],
            $cookieSettings['domain'],
            $cookieSettings['secure'],
            $cookieSettings['httponly'],
            false,
            $cookieSettings['samesite']
        );

        $refreshCookie = cookie(
            'refresh_token',
            $refreshToken,
            10080,
            $cookieSettings['path'],
            $cookieSettings['domain'],
            $cookieSettings['secure'],
            $cookieSettings['httponly'],
            false,
            $cookieSettings['samesite']
        );

        return response()->json([
            'status' => 'success',
            'user' => Auth::user(),
            'authorization' => [
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'type' => 'bearer',
            ],
        ])->withCookie($accessCookie)->withCookie($refreshCookie);
    }

    public function logout()
    {
        Auth::logout();
        
        $cookieSettings = $this->getCookieSettings();
        
        // Clear both cookies with proper domain settings
        $accessCookie = cookie(
            'access_token',
            '',
            -1,
            $cookieSettings['path'],
            $cookieSettings['domain'],
            $cookieSettings['secure'],
            $cookieSettings['httponly'],
            false,
            $cookieSettings['samesite']
        );
        
        $refreshCookie = cookie(
            'refresh_token',
            '',
            -1,
            $cookieSettings['path'],
            $cookieSettings['domain'],
            $cookieSettings['secure'],
            $cookieSettings['httponly'],
            false,
            $cookieSettings['samesite']
        );
        
        return response()->json([
            'status' => 'success',
            'message' => 'Successfully logged out',
        ])->withCookie($accessCookie)->withCookie($refreshCookie);
    }

    public function refresh(Request $request)
    {
        try {
            $refreshToken = $request->cookie('refresh_token');
            
            if (!$refreshToken) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Refresh token not found',
                ], 401);
            }

            Auth::setToken($refreshToken);
            $claims = Auth::getPayload();
            
            if ($claims->get('type') !== 'refresh') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid refresh token',
                ], 401);
            }

            $newAccessToken = Auth::claims(['type' => 'access'])->setTTL(15)->tokenById(Auth::id());
            
            $cookieSettings = $this->getCookieSettings();

            $accessCookie = cookie(
                'access_token',
                $newAccessToken,
                15,
                $cookieSettings['path'],
                $cookieSettings['domain'],
                $cookieSettings['secure'],
                $cookieSettings['httponly'],
                false,
                $cookieSettings['samesite']
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Token refreshed successfully',
                'authorization' => [
                    'access_token' => $newAccessToken,
                    'type' => 'bearer',
                ],
            ])->withCookie($accessCookie);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid refresh token',
            ], 401);
        }
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Current password is incorrect',
            ], 400);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Password changed successfully',
        ]);
    }
}