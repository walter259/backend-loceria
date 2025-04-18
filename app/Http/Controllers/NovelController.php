<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Novel;

class NovelController extends Controller
{
    // Listar todas las novelas con su autor y categoría
    public function show()
    {
        $novels = Novel::with(['user', 'category'])->get()->map(function ($novel) {
            return [
                'id' => $novel->id,
                'title' => $novel->title,
                'description' => $novel->description,
                'user_id' => $novel->user_id,
                'author' => $novel->user ? $novel->user->name : null,
                'category_id' => $novel->category_id,
                'category' => $novel->category ? $novel->category->name : null,
                'created_at' => $novel->created_at,
                'updated_at' => $novel->updated_at,
            ];
        });
        return response()->json(["novels" => $novels]);
    }

    // Crear una nueva novela
    public function store(Request $request)
    {
        // Validar los datos, pero sin requerir user_id
        $data = $request->validate([
            'title' => 'required|string',
            'description' => 'required|string',
            'category_id' => 'required|integer|exists:categories,id',
        ]);

        // Obtener el usuario autenticado desde el token
        $user = $request->user();

        // Agregar el user_id al array de datos
        $data['user_id'] = $user->id;

        $novel = Novel::create($data);

        return response()->json([
            'message' => 'Novel created',
            'novel' => [
                'id' => $novel->id,
                'title' => $novel->title,
                'description' => $novel->description,
                'user_id' => $novel->user_id,
                'category_id' => $novel->category_id,
                'created_at' => $novel->created_at,
                'updated_at' => $novel->updated_at,
            ]
        ], 201);
    }

    // Actualizar una novela existente
    public function update(Request $request, $id)
    {
        // Validar los datos, sin incluir user_id en la solicitud
        $data = $request->validate([
            'title' => 'sometimes|string',
            'description' => 'sometimes|string',
            'category_id' => 'sometimes|integer|exists:categories,id',
        ]);

        $novel = Novel::find($id);

        if (!$novel) {
            return response()->json([
                'message' => 'Novel not found'
            ], 404);
        }

        // Verificar si el usuario autenticado tiene permiso para actualizar esta novela
        $user = $request->user();
        if ($novel->user_id !== $user->id && $user->role_id !== 3) { // Permitir a los Admins (role_id: 3) actualizar cualquier novela
            return response()->json([
                'message' => 'Unauthorized: You can only update your own novels.'
            ], 403);
        }

        $novel->update($data);

        return response()->json([
            'message' => 'Novel updated',
            'novel' => [
                'id' => $novel->id,
                'title' => $novel->title,
                'description' => $novel->description,
                'user_id' => $novel->user_id,
                'category_id' => $novel->category_id,
                'created_at' => $novel->created_at,
                'updated_at' => $novel->updated_at,
            ]
        ]);
    }

    // Eliminar una novela
    public function destroy(Request $request, $id)
    {
        $novel = Novel::find($id);

        if (!$novel) {
            return response()->json([
                'message' => 'Novel not found'
            ], 404);
        }

        // Verificar si el usuario autenticado tiene permiso para eliminar esta novela
        $user = $request->user();
        if ($novel->user_id !== $user->id && $user->role_id !== 3) { // Permitir a los Admins (role_id: 3) eliminar cualquier novela
            return response()->json([
                'message' => 'Unauthorized: You can only delete your own novels.'
            ], 403);
        }

        $novel->delete();

        return response()->json([
            'message' => 'Novel deleted'
        ]);
    }
}