<?php

declare(strict_types=1);

use App\Enums\SearchEngine;
use App\Enums\TargetType;
use App\Enums\TrackingFrequency;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('domain');
            $table->string('target_type')->default(TargetType::Domain->value);
            $table->unsignedInteger('default_location_code');
            $table->string('default_language_code');
            $table->string('search_engine')->default(SearchEngine::Google->value);
            $table->string('tracking_frequency')->default(TrackingFrequency::Weekly->value);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
