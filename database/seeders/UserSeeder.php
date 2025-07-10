<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminRole = Role::where('name', 'admin')->first();

        if (!$adminRole) {
            $this->command->error("Admin role not found. Please run RoleSeeder first.");
            return;
        }

        // Crear un administrador inicial si no existe
        if (!User::where('role_id', $adminRole->id)->exists()) {
            $adminEmail = env('ADMIN_EMAIL', 'admin@example.com');
            $adminPassword = Str::random(16); // Genera contraseña aleatoria

            User::create([
                'name' => 'Admin User',
                'email' => $adminEmail,
                'password' => Hash::make($adminPassword),
                'role_id' => $adminRole->id,
            ]);

            // Muestra la contraseña generada
            $this->command->info("=== ADMIN USER CREATED ===");
            $this->command->info("Email: $adminEmail");
            $this->command->info("Password: $adminPassword");
            $this->command->warn("SAVE THIS PASSWORD! It won't be shown again.");
        } else {
            $this->command->info("Admin user already exists, skipping creation.");
        }   //
    }
}
