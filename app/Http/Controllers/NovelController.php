<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Novel;
use App\Services\CloudinaryService;

class NovelController extends Controller
{
    protected $cloudinaryService;

    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;
    }

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
                'image' => $novel->image,
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
        try {
            $data = $request->validate([
                'title' => 'required|string',
                'description' => 'required|string',
                'category_id' => 'required|integer|exists:categories,id',
                'image' => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            ]);

            $user = $request->user();
            $data['user_id'] = $user->id;

            if ($request->hasFile('image') && $request->file('image')->isValid()) {
                $uploadedImage = $this->cloudinaryService->upload($request->file('image')->getRealPath(), [
                    'folder' => 'novels',
                    'resource_type' => 'image',
                ]);
                $data['image'] = $uploadedImage['secure_url'];
            }

            $novel = Novel::create($data);

            return response()->json([
                'message' => 'Novel created',
                'novel' => [
                    'id' => $novel->id,
                    'title' => $novel->title,
                    'description' => $novel->description,
                    'user_id' => $novel->user_id,
                    'category_id' => $novel->category_id,
                    'image' => $novel->image,
                    'created_at' => $novel->created_at,
                    'updated_at' => $novel->created_at,
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error creating novel',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // Actualizar una novela existente
    public function update(Request $request, $id)
    {
        try {
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

            $data = $request->validate([
                'title' => 'sometimes|required|string',
                'description' => 'sometimes|required|string',
                'category_id' => 'sometimes|required|integer|exists:categories,id',
                'image' => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            ]);

            // Manejar la actualización de la imagen
            if ($request->hasFile('image') && $request->file('image')->isValid()) {
                // Eliminar la imagen anterior de Cloudinary si existe
                if ($novel->image) {
                    $publicId = $this->cloudinaryService->getPublicIdFromUrl($novel->image);
                    $this->cloudinaryService->delete($publicId, ['resource_type' => 'image']);
                }

                // Subir la nueva imagen
                $uploadedImage = $this->cloudinaryService->upload($request->file('image')->getRealPath(), [
                    'folder' => 'novels',
                    'resource_type' => 'image',
                ]);
                $data['image'] = $uploadedImage['secure_url'];
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
                    'image' => $novel->image,
                    'created_at' => $novel->created_at,
                    'updated_at' => $novel->updated_at,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error updating novel',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // Eliminar una novela
    public function destroy(Request $request, $id)
    {
        try {
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

            // Eliminar la imagen de Cloudinary si existe
            if ($novel->image) {
                $publicId = $this->cloudinaryService->getPublicIdFromUrl($novel->image);
                $this->cloudinaryService->delete($publicId, ['resource_type' => 'image']);
            }

            $novel->delete();

            return response()->json([
                'message' => 'Novel deleted'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error deleting novel',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function showById($id)
{
    try {
        $novel = Novel::with(['user', 'category'])->find($id);
        
        if (!$novel) {
            return response()->json([
                'message' => 'Novel not found'
            ], 404);
        }
        
        return response()->json([
            'novel' => [
                'id' => $novel->id,
                'title' => $novel->title,
                'description' => $novel->description,
                'user_id' => $novel->user_id,
                'author' => $novel->user ? $novel->user->name : null,
                'category_id' => $novel->category_id,
                'category' => $novel->category ? $novel->category->name : null,
                'image' => $novel->image,
                'created_at' => $novel->created_at,
                'updated_at' => $novel->updated_at,
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Error retrieving novel',
            'message' => $e->getMessage(),
        ], 500);
    }
}
}
