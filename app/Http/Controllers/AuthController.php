<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    public function register(Request $request) 
    {
    $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|string|email|max:255|unique:users',
        'password' => 'required|string|min:8|confirmed',
    ]);

    $user = User::create([
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

    // Set HTTP-only cookies
    $accessCookie = cookie(
        'access_token',
        $accessToken,
        15,              // 15 minutes
        '/',
        null,
        false,          // secure (set to true in production)
        true,           // httpOnly
        false,
        'lax'
    );

    $refreshCookie = cookie(
        'refresh_token',
        $refreshToken,
        10080,          // 7 days
        '/',
        null,
        false,          // secure (set to true in production)
        true,           // httpOnly
        false,
        'lax'
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
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $credentials = $request->only('email', 'password');

        if (!$token = Auth::attempt($credentials)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials',
            ], 401);
        }

        // Generate both access and refresh tokens
        $accessToken = Auth::claims(['type' => 'access'])->setTTL(15)->attempt($credentials); // 15 minutes
        $refreshToken = Auth::claims(['type' => 'refresh'])->setTTL(10080)->attempt($credentials); // 7 days

        // Set HTTP-only cookies
        $accessCookie = cookie(
            'access_token',
            $accessToken,
            15,              // 15 minutes
            '/',
            null,
            false,          // secure (set to true in production)
            true,           // httpOnly
            false,
            'lax'
        );

        $refreshCookie = cookie(
            'refresh_token',
            $refreshToken,
            10080,          // 7 days
            '/',
            null,
            false,          // secure (set to true in production)
            true,           // httpOnly
            false,
            'lax'
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
        
        // Clear both cookies
        $accessCookie = cookie()->forget('access_token');
        $refreshCookie = cookie()->forget('refresh_token');
        
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

            // Verify the refresh token
            Auth::setToken($refreshToken);
            $claims = Auth::getPayload();
            
            if ($claims->get('type') !== 'refresh') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid refresh token',
                ], 401);
            }

            // Generate new access token
            $newAccessToken = Auth::claims(['type' => 'access'])->setTTL(15)->tokenById(Auth::id());
            
            $accessCookie = cookie(
                'access_token',
                $newAccessToken,
                15,
                '/',
                null,
                false,
                true,
                false,
                'lax'
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
}