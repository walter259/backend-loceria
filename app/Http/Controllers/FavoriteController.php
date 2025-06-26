<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Favorite;
use App\Models\Novel;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{
    // Listar todos los favoritos del usuario autenticado
    public function show()
    {
        $userId = Auth::id();

        $favorites = Favorite::where('user_id', $userId)
            ->with(['novel', 'user'])
            ->get()
            ->map(function ($favorite) {
                return [
                    'id' => $favorite->id,
                    'user_id' => $favorite->user_id,
                    'novel_id' => $favorite->novel_id,
                    'novel' => $favorite->novel ? $favorite->novel->title : null,
                    'image' => $favorite->novel ? $favorite->novel->image : null,
                    'created_at' => $favorite->created_at,
                    'updated_at' => $favorite->updated_at,
                ];
            });

        if ($favorites->isEmpty()) {
            return response()->json([
                'message' => 'No favorites found'
            ], 404);
        }

        return response()->json([
            'message' => 'Favorites retrieved successfully',
            'favorites' => $favorites
        ]);
    }

    // Listar favoritos de un usuario específico por ID
    public function showByUserId($userId)
    {
        // Verificar que el usuario existe
        if (!User::find($userId)) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        $favorites = Favorite::where('user_id', $userId)
            ->with(['novel', 'user'])
            ->get()
            ->map(function ($favorite) {
                return [
                    'id' => $favorite->id,
                    'user_id' => $favorite->user_id,
                    'user' => $favorite->user ? $favorite->user->name : null,
                    'novel_id' => $favorite->novel_id,
                    'novel' => $favorite->novel ? $favorite->novel->title : null,
                    'image' => $favorite->novel ? $favorite->novel->image : null,
                    'created_at' => $favorite->created_at,
                    'updated_at' => $favorite->updated_at,
                ];
            });

        if ($favorites->isEmpty()) {
            return response()->json([
                'message' => 'No favorites found for this user'
            ], 404);
        }

        return response()->json([
            'message' => 'User favorites retrieved successfully',
            'user_id' => $userId,
            'favorites' => $favorites
        ]);
    }

    // Agregar una novela a favoritos de un usuario específico
    public function store(Request $request, $userId, $novelId)
    {
        // Validar que el usuario existe
        if (!User::find($userId)) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        // Validar que la novela exista
        if (!Novel::find($novelId)) {
            return response()->json([
                'message' => 'Novel not found'
            ], 404);
        }

        // Verificar si ya existe el favorito
        $existingFavorite = Favorite::where('user_id', $userId)
            ->where('novel_id', $novelId)
            ->first();

        if ($existingFavorite) {
            return response()->json([
                'message' => 'This novel is already in user favorites'
            ], 409);
        }

        $favorite = Favorite::create([
            'user_id' => $userId,
            'novel_id' => $novelId
        ]);

        $novel = Novel::find($novelId);

        return response()->json([
            'message' => 'Novel added to user favorites',
            'favorite' => [
                'id' => $favorite->id,
                'user_id' => $favorite->user_id,
                'novel_id' => $favorite->novel_id,
                'novel' => $novel ? $novel->title : null,
                'image' => $novel ? $novel->image : null,
                'created_at' => $favorite->created_at,
                'updated_at' => $favorite->updated_at,
            ]
        ], 201);
    }

    // Eliminar una novela de favoritos de un usuario específico
    public function destroy($userId, $novelId)
    {
        // Verificar que el usuario existe
        if (!User::find($userId)) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        // Verificar que la novela existe
        if (!Novel::find($novelId)) {
            return response()->json([
                'message' => 'Novel not found'
            ], 404);
        }

        // Buscar el favorito específico
        $favorite = Favorite::where('user_id', $userId)
            ->where('novel_id', $novelId)
            ->first();

        if (!$favorite) {
            return response()->json([
                'message' => 'Favorite not found for this user and novel'
            ], 404);
        }

        $favorite->delete();

        return response()->json([
            'message' => 'Novel removed from user favorites'
        ]);
    }
}
