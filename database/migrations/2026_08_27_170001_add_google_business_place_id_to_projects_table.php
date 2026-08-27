<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 3, "Monitoreo de reseñas y Google Business Profile" (sección
     * 5 del SPEC). No hay campo para esto en la sección 4 —
     * business_data/google/reviews/task_post necesita identificar el
     * negocio con `keyword`, `cid` o `place_id`; se eligió `place_id`
     * por ser el identificador estable de Google Maps (a diferencia de
     * `keyword`, que es ambiguo, y `cid`, menos conocido/accesible
     * para quien configura el proyecto). Nullable: no todos los
     * proyectos son de SEO local con ficha de Google Business.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('google_business_place_id')->nullable()->after('domain');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('google_business_place_id');
        });
    }
};
