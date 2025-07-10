<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Índice para ordenar por fecha de creación
            $table->index('created_at');
            
            // Full-text search para búsquedas de texto
            if (Schema::hasColumn('products', 'brand')) {
                $table->fullText(['name', 'brand']);
            } else {
                $table->fullText('name');
            }
        });
        
        Schema::table('sales', function (Blueprint $table) {
            // Índice individual para consultas por usuario
            $table->index('user_id');
            
            // Índice individual para consultas por fecha
            $table->index('created_at');
            
            // Índice compuesto para consultas por usuario Y fecha
            $table->index(['user_id', 'created_at']);
        });
    }
    
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            
            if (Schema::hasColumn('products', 'brand')) {
                $table->dropFullText(['name', 'brand']);
            } else {
                $table->dropFullText(['name']);
            }
        });
        
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['user_id', 'created_at']);
        });
    }
};