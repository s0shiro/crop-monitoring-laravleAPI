<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    public function register(Request $request) {
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

        $token = Auth::login($user);

        // Set HTTP-only cookie with the token
        $cookie = cookie(
            'auth_token',     // name
            $token,           // value 
            60,               // minutes (1 hour)
            '/',              // path
            null,             // domain
            false,            // secure (set to true in production)
            true,             // httpOnly
            false,            // raw
            'lax'             // sameSite (important for cross-domain usage)
        );

        return response()->json([
            'status' => 'success',
            'message' => 'User registered successfully',
            'user' => $user,
            'authorization' => [
                'token' => $token,
                'type' => 'bearer',
            ],
        ])->withCookie($cookie);
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
                'message' => 'Unauthorized',
            ], 401);
        }

        // Set HTTP-only cookie with the token
        $cookie = cookie(
            'auth_token',     // name
            $token,           // value 
            60,            // minutes (1 hour)
            '/',              // path
            null,             // domain
            false,            // secure (set to true in production)
            true,             // httpOnly
            false,            // raw
            'lax'             // sameSite (important for cross-domain usage)
        );

        return response()->json([
            'status' => 'success',
            'user' => Auth::user(),
            'authorization' => [
                'token' => $token,
                'type' => 'bearer',
            ],
        ])->withCookie($cookie);
    }

    public function logout()
    {
        Auth::logout();
        
        // Clear the cookie
        $cookie = cookie()->forget('auth_token');
        
        return response()->json([
            'status' => 'success',
            'message' => 'Successfully logged out',
        ])->withCookie($cookie);
    }

    public function refresh() {
        $newToken = Auth::refresh();
        
        // Set HTTP-only cookie with the refreshed token
        $cookie = cookie(
            'auth_token',     // name
            $newToken,        // value 
            60,               // minutes (1 hour)
            '/',              // path
            null,             // domain
            false,            // secure
            true,             // httpOnly
        );
        
        return response()->json([
            'status' => 'success',
            'message' => 'Token refreshed successfully',
            'user' => Auth::user(),
            'authorization' => [
                'token' => $newToken,
                'type' => 'bearer',
            ],
        ])->withCookie($cookie);
    }
}