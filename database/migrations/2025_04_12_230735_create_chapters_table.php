<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chapters', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('content');
            $table->integer('chapter_number');
            $table->unsignedBigInteger('novel_id');
            $table->timestamps();

            $table->foreign('novel_id')->references('id')->on('novels')->onDelete('cascade');
            $table->unique(['novel_id', 'chapter_number']); // Añadimos esta línea para garantizar unicidad
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chapters');
    }
};