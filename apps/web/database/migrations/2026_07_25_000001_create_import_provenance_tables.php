<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->nullable();
            $table->string('author')->nullable();
            $table->string('edition')->nullable();
            $table->string('language', 12)->default('en');
            $table->string('url')->nullable();
            $table->string('checksum')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['source_id', 'slug']);
        });

        Schema::create('import_batches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_id')->nullable()->constrained()->nullOnDelete();
            $table->string('adapter');
            $table->string('status')->default('running');
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->json('report')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('imported_facts', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('import_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('citation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entity_type');
            $table->uuid('entity_id');
            $table->string('field');
            $table->json('value');
            $table->decimal('confidence', 4, 3)->default(1);
            $table->boolean('is_tradition')->default(false);
            $table->boolean('is_disputed')->default(false);
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->index('field');
        });

        Schema::create('research_leads', function (Blueprint $table): void {
            $table->id();
            $table->string('source');
            $table->string('external_id')->nullable();
            $table->string('candidate_name');
            $table->string('status')->default('needs_catholic_source');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['source', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_leads');
        Schema::dropIfExists('imported_facts');
        Schema::dropIfExists('import_batches');
        Schema::dropIfExists('source_documents');
    }
};
