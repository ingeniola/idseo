<?php

declare(strict_types=1);

use App\DataForSeo\Enums\DataForSeoTaskStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dataforseo_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('task_id')->unique();
            $table->string('endpoint');
            $table->nullableMorphs('taskable');
            $table->string('status')->default(DataForSeoTaskStatus::Pending->value);
            $table->json('payload_sent');
            $table->longText('payload_received')->nullable();
            $table->decimal('cost', 10, 6)->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dataforseo_tasks');
    }
};
