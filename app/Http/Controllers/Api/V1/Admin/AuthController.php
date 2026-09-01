<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminLoginRequest;
use App\Http\Resources\AdminUserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(AdminLoginRequest $request)
    {
        $credentials = $request->validated();

        if (!Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $user = Auth::user();
        $token = $user->createToken('admin-mobile')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'user' => new AdminUserResource($user),
        ], 200);
    }

    // public function logout(Request $request)
    // {
    //     $request->user()->currentAccessToken()->delete();

    //     return response()->json([
    //         'message' => 'Logout successful.',
    //     ], 200);
    // }

    public function logout(Request $request)
{
    $token = $request->user()->currentAccessToken();

    if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
        $token->delete();
    }

    return response()->json([
        'message' => 'Logout successful.',
    ], 200);
}

    public function me(Request $request)
    {
        return response()->json(
        (new AdminUserResource($request->user()))->resolve($request)
    );
    }
}