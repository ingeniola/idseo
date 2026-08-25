<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Log de auditoría de cada llamada HTTP a DataForSEO, sin excepción
     * (sección 3.4 del SPEC). http_status y api_status_code son
     * nullable: un fallo de conexión pura (timeout, DNS) no llega a
     * tener ninguno de los dos. Sin updated_at: es un log inmutable.
     */
    public function up(): void
    {
        Schema::create('dataforseo_requests', function (Blueprint $table) {
            $table->id();
            $table->string('method', 10);
            $table->string('endpoint');
            $table->unsignedInteger('duration_ms');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('api_status_code')->nullable();
            $table->decimal('cost', 10, 6)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dataforseo_requests');
    }
};
