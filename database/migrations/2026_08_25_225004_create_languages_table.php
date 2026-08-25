<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Espejo local del catálogo de idiomas de DataForSEO, sincronizado
     * con `dataforseo:sync-locations` (Fase 1, paso 5).
     */
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table) {
            $table->string('language_code')->primary();
            $table->string('language_name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('languages');
    }
};
