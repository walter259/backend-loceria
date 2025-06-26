<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Crear roles si no existen
        if (Role::count() === 0) {
            Role::create(['name' => 'Usuario']);
            Role::create(['name' => 'Moderador']);
            Role::create(['name' => 'Admin']);
        }

        // Crear un administrador inicial si no existe
        if (!User::where('role_id', 3)->exists()) {
            $adminEmail = env('ADMIN_EMAIL', 'admin@example.com');
            $adminPassword = Str::random(16); // Genera contraseña aleatoria

            User::create([
                'user' => 'admin',
                'name' => 'Admin User',
                'email' => $adminEmail,
                'password' => Hash::make($adminPassword),
                'role_id' => 3, // Admin
            ]);

            // Muestra la contraseña generada
            $this->command->info("=== ADMIN USER CREATED ===");
            $this->command->info("Email: $adminEmail");
            $this->command->info("Password: $adminPassword");
            $this->command->warn("SAVE THIS PASSWORD! It won't be shown again.");
        }
    }
}