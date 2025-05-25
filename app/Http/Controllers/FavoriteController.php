<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Favorite;
use App\Models\Novel;
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
                    //'user' => $favorite->user ? $favorite->user->name : null,
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

    // Agregar una novela a favoritos
    public function store(Request $request, $novelId)
    {
        // Validar que la novela exista
        if (!Novel::find($novelId)) {
            return response()->json([
                'message' => 'Novel not found'
            ], 404);
        }

        $userId = Auth::id();

        // Verificar si ya existe el favorito
        $existingFavorite = Favorite::where('user_id', $userId)
            ->where('novel_id', $novelId)
            ->first();

        if ($existingFavorite) {
            return response()->json([
                'message' => 'This novel is already in your favorites'
            ], 409);
        }

        $favorite = Favorite::create([
            'user_id' => $userId,
            'novel_id' => $novelId
        ]);

        return response()->json([
            'message' => 'Novel added to favorites',
            'favorite' => [
                'id' => $favorite->id,
                'user_id' => $favorite->user_id,
                'novel_id' => $favorite->novel_id,
                'created_at' => $favorite->created_at,
            ]
        ], 201);
    }

    // Eliminar una novela de favoritos
    public function destroy($id)
    {
        $favorite = Favorite::find($id);

        if (!$favorite) {
            return response()->json([
                'message' => 'Favorite not found'
            ], 404);
        }

        // Verificar que el favorito pertenece al usuario autenticado
        if ($favorite->user_id != Auth::id()) {
            return response()->json([
                'message' => 'Unauthorized to delete this favorite'
            ], 403);
        }

        $favorite->delete();

        return response()->json([
            'message' => 'Novel removed from favorites'
        ]);
    }
}