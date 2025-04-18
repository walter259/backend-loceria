<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        // Asegurarse de que el rol "Usuario" exista
        $userRole = Role::where('name', 'Usuario')->first();
        if (!$userRole) {
            return response()->json([
                'message' => 'Role "Usuario" not found. Please ensure roles are seeded.'
            ], 500);
        }

        $data = $request->validate([
            'user' => 'required|string|max:255|unique:users,user',
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        try {
            $user = User::create([
                'user' => $data['user'],
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role_id' => $userRole->id, // Usar el ID del rol "Usuario"
            ]);

            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'message' => 'User registered successfully',
                'user' => [
                    'id' => $user->id,
                    'user' => $user->user,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role_id' => $user->role_id,
                ],
                'token' => $token,
                'type' => 'Bearer',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to register user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required_without:user|string|email',
            'user' => 'required_without:email|string',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'] ?? null)
                    ->orWhere('user', $data['user'] ?? null)
                    ->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
                'user' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => [
                'id' => $user->id,
                'user' => $user->user,
                'name' => $user->name,
                'email' => $user->email,
                'role_id' => $user->role_id,
            ],
            'token' => $token,
            'type' => 'Bearer',
        ]);
    }

    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'message' => 'Logout successful',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to logout',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}