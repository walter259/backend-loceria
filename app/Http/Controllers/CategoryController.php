<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Category;

class CategoryController extends Controller
{
    // Listar todas las categorías con el usuario creador
    public function show()
    {
        $categories = Category::with('user')->get()->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'user_id' => $category->user_id,
                'created_by' => $category->user ? $category->user->name : null,
                'created_at' => $category->created_at,
                'updated_at' => $category->updated_at,
            ];
        });
        return response()->json([
            'message' => 'Categories retrieved successfully',
            'categories' => $categories,
        ]);
    }

    // Crear una nueva categoría
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
        ]);

        // Obtener el usuario autenticado y asignar su ID
        $user = $request->user();
        $data['user_id'] = $user->id;

        $category = Category::create($data);

        return response()->json([
            'message' => 'Category created successfully',
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'user_id' => $category->user_id,
                'created_at' => $category->created_at,
                'updated_at' => $category->updated_at,
            ]
        ], 201);
    }

    // Actualizar una categoría existente
    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255|unique:categories,name,' . $id,
            'user_id' => 'sometimes|integer|exists:users,id',
        ]);

        $category = Category::findOrFail($id);

        // Verificar si el usuario autenticado tiene permiso para actualizar esta categoría
        $user = $request->user();
        if ($category->user_id !== $user->id && $user->role_id !== 3) {
            return response()->json([
                'message' => 'Unauthorized: You can only update your own categories.'
            ], 403);
        }

        $category->update($data);

        return response()->json([
            'message' => 'Category updated successfully',
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'user_id' => $category->user_id,
                'created_at' => $category->created_at,
                'updated_at' => $category->updated_at,
            ]
        ]);
    }

    // Eliminar una categoría
    public function destroy($id)
    {
        $category = Category::findOrFail($id);

        // Verificar si el usuario autenticado tiene permiso para eliminar esta categoría
        $user = request()->user();
        if ($category->user_id !== $user->id && $user->role_id !== 3) {
            return response()->json([
                'message' => 'Unauthorized: You can only delete your own categories.'
            ], 403);
        }

        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully',
        ]);
    }
}