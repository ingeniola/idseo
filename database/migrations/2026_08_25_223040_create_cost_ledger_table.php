<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * client_id y project_id se dejan sin constraint: las tablas `clients`
     * y `projects` todavía no existen (Fase 1, paso 4 del SPEC). Una
     * migración posterior debe agregar las foreign keys una vez existan.
     */
    public function up(): void
    {
        Schema::create('cost_ledger', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('client_id')->nullable();
            $table->foreignId('project_id')->nullable();
            $table->string('endpoint_group');
            $table->decimal('cost', 10, 6);
            $table->string('task_reference')->nullable();
            $table->timestamps();

            $table->index('date');
            $table->index(['client_id', 'date']);
            $table->index(['project_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_ledger');
    }
};
