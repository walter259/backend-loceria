<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Chapter;
use Illuminate\Database\QueryException;

class ChapterController extends Controller
{
    // Listar todos los capítulos de una novela 
    public function show($novelId)
    {
        // Validar que la novela exista
        if (!\App\Models\Novel::find($novelId)) {
            return response()->json([
                'message' => 'Novel not found',
            ], 404);
        }

        $chapters = Chapter::where('novel_id', $novelId)
            ->orderBy('chapter_number', 'asc')
            ->get()
            ->map(function ($chapter) {
                return [
                    'id' => $chapter->id,
                    'novel_id' => $chapter->novel_id,
                    'chapter_number' => $chapter->chapter_number,
                    'title' => $chapter->title,
                    'created_at' => $chapter->created_at,
                    'updated_at' => $chapter->updated_at,
                ];
            });

        return response()->json([
            'message' => 'Chapters retrieved successfully',
            'chapters' => $chapters,
        ]);
    }

    // Obtener un capítulo específico por su ID (incluye content)
    public function showSingle($novelId, $id)
    {
        // Validar que la novela exista
        if (!\App\Models\Novel::find($novelId)) {
            return response()->json([
                'message' => 'Novel not found',
            ], 404);
        }

        $chapter = Chapter::find($id);

        if (!$chapter) {
            return response()->json([
                'message' => 'Chapter not found',
            ], 404);
        }

        // Validar que el capítulo pertenece a la novela
        if ($chapter->novel_id != $novelId) {
            return response()->json([
                'message' => 'Chapter does not belong to this novel',
            ], 403);
        }

        return response()->json([
            'message' => 'Chapter retrieved successfully',
            'chapter' => [
                'id' => $chapter->id,
                'novel_id' => $chapter->novel_id,
                'chapter_number' => $chapter->chapter_number,
                'title' => $chapter->title,
                'content' => $chapter->content,
                'created_at' => $chapter->created_at,
                'updated_at' => $chapter->updated_at,
            ]
        ]);
    }

    // Crear un nuevo capítulo para una novela específica
    public function store(Request $request, $novelId)
    {
        // Validar que la novela exista
        if (!\App\Models\Novel::find($novelId)) {
            return response()->json([
                'message' => 'Novel not found',
            ], 404);
        }

        $data = $request->validate([
            'title' => 'required|string',
            'content' => 'required|string',
        ]);

        // Calcular el próximo chapter_number para esta novela
        $lastChapter = Chapter::where('novel_id', $novelId)
            ->orderBy('chapter_number', 'desc')
            ->first();

        $data['novel_id'] = $novelId;
        $data['chapter_number'] = $lastChapter ? $lastChapter->chapter_number + 1 : 1;

        try {
            $chapter = Chapter::create($data);
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return response()->json([
                    'message' => 'A chapter with this number already exists for this novel.',
                ], 422);
            }
            throw $e;
        }

        return response()->json([
            'message' => 'Chapter created',
            'chapter' => [
                'id' => $chapter->id,
                'novel_id' => $chapter->novel_id,
                'chapter_number' => $chapter->chapter_number,
                'title' => $chapter->title,
                'content' => $chapter->content,
                'created_at' => $chapter->created_at,
                'updated_at' => $chapter->updated_at,
            ]
        ], 201);
    }

    // Actualizar un capítulo existente usando novel_id y chapter_number
    public function update(Request $request, $novelId, $chapterId)
    {
        // Validar que la novela exista
        if (!\App\Models\Novel::find($novelId)) {
            return response()->json([
                'message' => 'Novel not found',
            ], 404);
        }

        $data = $request->validate([
            'title' => 'sometimes|string',
            'content' => 'sometimes|string',
            'chapter_number' => 'sometimes|integer|min:1',
        ]);

        // Buscar el capítulo por ID
        $chapter = Chapter::find($chapterId);

        if (!$chapter) {
            return response()->json([
                'message' => 'Chapter not found',
            ], 404);
        }

        // Validar que el capítulo pertenece a la novela
        if ($chapter->novel_id != $novelId) {
            return response()->json([
                'message' => 'Chapter does not belong to this novel',
            ], 403);
        }

        // Si se está cambiando el chapter_number, validar que no exista otro capítulo con ese número
        if (isset($data['chapter_number']) && $data['chapter_number'] != $chapter->chapter_number) {
            $existingChapter = Chapter::where('novel_id', $novelId)
                ->where('chapter_number', $data['chapter_number'])
                ->where('id', '!=', $chapterId)
                ->first();

            if ($existingChapter) {
                return response()->json([
                    'message' => 'Another chapter with this number already exists for this novel.',
                ], 422);
            }
        }

        try {
            $chapter->update($data);
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return response()->json([
                    'message' => 'Database constraint violation.',
                ], 422);
            }
            throw $e;
        }

        return response()->json([
            'message' => 'Chapter updated',
            'chapter' => [
                'id' => $chapter->id,
                'novel_id' => $chapter->novel_id,
                'chapter_number' => $chapter->chapter_number,
                'title' => $chapter->title,
                'content' => $chapter->content,
                'created_at' => $chapter->created_at,
                'updated_at' => $chapter->updated_at,
            ]
        ]);
    }

    // Eliminar un capítulo
    public function destroy($novelId, $id)
    {
        // Validar que la novela exista
        if (!\App\Models\Novel::find($novelId)) {
            return response()->json([
                'message' => 'Novel not found',
            ], 404);
        }

        $chapter = Chapter::find($id);

        if (!$chapter) {
            return response()->json([
                'message' => 'Chapter not found',
            ], 404);
        }

        // Validar que el capítulo pertenece a la novela
        if ($chapter->novel_id != $novelId) {
            return response()->json([
                'message' => 'Chapter does not belong to this novel',
            ], 403);
        }

        $novelId = $chapter->novel_id;
        $chapterNumber = $chapter->chapter_number;

        $chapter->delete();

        // Actualizar los chapter_number de los capítulos posteriores
        Chapter::where('novel_id', $novelId)
            ->where('chapter_number', '>', $chapterNumber)
            ->decrement('chapter_number');

        return response()->json([
            'message' => 'Chapter deleted',
        ]);
    }
}
