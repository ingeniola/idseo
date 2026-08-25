<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Espejo local del catálogo de ubicaciones de DataForSEO. Se
     * sincroniza con `dataforseo:sync-locations` (Fase 1, paso 5); no se
     * consulta en vivo. location_type queda como string libre: son los
     * valores tal cual los reporta el catálogo externo, no un vocabulario
     * que definamos nosotros.
     */
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->unsignedInteger('location_code')->primary();
            $table->string('location_name');
            $table->string('location_name_canonical');
            $table->string('country_iso_code', 2);
            $table->string('location_type');
            $table->unsignedInteger('parent_code')->nullable();
            $table->timestamps();

            $table->foreign('parent_code')->references('location_code')->on('locations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
