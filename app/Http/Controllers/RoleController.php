<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Role;

class RoleController extends Controller
{
    // Listar todos los roles
    public function show()
    {
        $roles = Role::all()->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'created_at' => $role->created_at,
                'updated_at' => $role->updated_at,
            ];
        });
        return response()->json([
            'message' => 'Roles retrieved successfully',
            'roles' => $roles,
        ]);
    }

    // Crear un nuevo rol
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
        ]);

        $role = Role::create($data);

        return response()->json([
            'message' => 'Role created successfully',
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'created_at' => $role->created_at,
                'updated_at' => $role->updated_at,
            ]
        ], 201);
    }

    // Actualizar un rol existente
    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255|unique:roles,name,' . $id,
        ]);

        $role = Role::findOrFail($id);
        $role->update($data);

        return response()->json([
            'message' => 'Role updated successfully',
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'created_at' => $role->created_at,
                'updated_at' => $role->updated_at,
            ]
        ]);
    }

    // Eliminar un rol
    public function destroy($id)
    {
        $role = Role::findOrFail($id);
        $role->delete();

        return response()->json([
            'message' => 'Role deleted successfully',
        ]);
    }
}