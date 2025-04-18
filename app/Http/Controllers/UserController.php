<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function show()
    {
        $users = User::with('role')->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'user' => $user->user,
                'name' => $user->name,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'role_name' => $user->role ? $user->role->name : null, // Asumiendo que el modelo Role tiene un campo role_name
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ];
        });

        return response()->json([
            'message' => 'Users retrieved successfully',
            'users' => $users,
        ]);
    }

    public function store(Request $request)
    {
        // Verificar si ya existe un Admin
        if ($request->role_id == 3 && User::where('role_id', 3)->exists()) {
            return response()->json([
                'message' => 'Only one Admin is allowed in the system.'
            ], 403);
        }

        $data = $request->validate([
            'user' => 'required|string|max:255|unique:users,user',
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role_id' => 'required|integer|exists:roles,id',
        ]);

        try {
            $user = User::create([
                'user' => $data['user'],
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role_id' => $data['role_id'],
            ]);

            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'message' => 'User created successfully',
                'user' => [
                    'id' => $user->id,
                    'user' => $user->user,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role_id' => $user->role_id,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ],
                'token' => $token ?? null,
                'type' => 'Bearer',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'user' => 'sometimes|string|max:255|unique:users,user,' . $id,
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $id,
            'password' => 'sometimes|string|min:8|confirmed',
            'role_id' => 'sometimes|integer|exists:roles,id',
        ]);

        try {
            $user = User::findOrFail($id);

            // Verificar si se intenta asignar role_id: 3 (Admin) y ya existe otro Admin
            if (isset($data['role_id']) && $data['role_id'] == 3 && User::where('role_id', 3)->where('id', '!=', $id)->exists()) {
                return response()->json([
                    'message' => 'Only one Admin is allowed in the system.'
                ], 403);
            }

            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }

            $user->update($data);

            return response()->json([
                'message' => 'User updated successfully',
                'user' => [
                    'id' => $user->id,
                    'user' => $user->user,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role_id' => $user->role_id,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $user = User::findOrFail($id);
            $user->delete();

            return response()->json([
                'message' => 'User deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}