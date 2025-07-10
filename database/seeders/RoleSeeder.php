<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Role::count() === 0) {
            Role::create(['name' => 'usuario']);
            Role::create(['name' => 'admin']);

            $this->command->info("Roles created successfully!");
        } else {
            $this->command->info("Roles already exist, skipping creation.");
        }
    }
}
