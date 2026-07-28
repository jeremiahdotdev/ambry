<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type')->nullable();
            $table->string('license')->nullable();
            $table->text('attribution')->nullable();
            $table->string('canonical_url')->nullable();
            $table->text('reliability_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('citations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title')->nullable();
            $table->string('locator')->nullable();
            $table->string('url')->nullable();
            $table->text('excerpt')->nullable();
            $table->date('accessed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('saints', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('primary_name');
            $table->string('slug')->unique();
            $table->text('biography')->nullable();
            $table->smallInteger('birth_year')->nullable();
            $table->smallInteger('death_year')->nullable();
            $table->string('gender')->nullable();
            $table->string('canonical_status')->default('saint');
            $table->boolean('is_martyr')->default(false);
            $table->boolean('is_doctor')->default(false);
            $table->timestamps();

            $table->index('primary_name');
            $table->index(['birth_year', 'death_year']);
            $table->index(['is_martyr', 'is_doctor']);
        });

        Schema::create('saint_aliases', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('saint_id')->constrained()->cascadeOnDelete();
            $table->string('alias');
            $table->string('normalized_alias')->index();
            $table->string('language', 12)->nullable();
            $table->foreignUuid('citation_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('confidence', 4, 3)->default(1);
            $table->timestamps();
        });

        Schema::create('feast_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('saint_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('month');
            $table->unsignedTinyInteger('day');
            $table->string('calendar')->default('general');
            $table->string('rite')->nullable();
            $table->string('locality')->nullable();
            $table->foreignUuid('citation_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('confidence', 4, 3)->default(1);
            $table->timestamps();

            $table->index(['month', 'day']);
        });

        Schema::create('patronages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('patronage_saint', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('saint_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('patronage_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('citation_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('confidence', 4, 3)->default(1);
            $table->boolean('is_tradition')->default(false);
            $table->timestamps();

            $table->unique(['saint_id', 'patronage_id']);
        });

        Schema::create('religious_orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('abbreviation')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('religious_order_saint', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('saint_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('religious_order_id')->constrained()->cascadeOnDelete();
            $table->string('role')->nullable();
            $table->foreignUuid('citation_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('confidence', 4, 3)->default(1);
            $table->timestamps();

            $table->unique(['saint_id', 'religious_order_id', 'role']);
        });

        Schema::create('countries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('iso_code', 3)->nullable();
            $table->text('historical_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('relationship_types', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('inverse_key')->nullable();
            $table->string('category')->nullable();
            $table->boolean('is_symmetric')->default(false);
            $table->boolean('is_derived')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('relationships', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_saint_id')->constrained('saints')->cascadeOnDelete();
            $table->foreignUuid('target_saint_id')->constrained('saints')->cascadeOnDelete();
            $table->foreignId('relationship_type_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('citation_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('confidence', 4, 3)->default(1);
            $table->boolean('is_tradition')->default(false);
            $table->boolean('is_disputed')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['source_saint_id', 'relationship_type_id']);
            $table->index(['target_saint_id', 'relationship_type_id']);
        });

        Schema::create('search_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('entity_type');
            $table->uuid('entity_id');
            $table->string('title');
            $table->text('summary')->nullable();
            $table->text('body')->nullable();
            $table->json('facets')->nullable();
            $table->json('relationship_terms')->nullable();
            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();

            $table->unique(['entity_type', 'entity_id']);
            $table->index('title');
        });

        Schema::create('bookmarks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('saint_id')->constrained()->cascadeOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'saint_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookmarks');
        Schema::dropIfExists('search_documents');
        Schema::dropIfExists('relationships');
        Schema::dropIfExists('relationship_types');
        Schema::dropIfExists('countries');
        Schema::dropIfExists('religious_order_saint');
        Schema::dropIfExists('religious_orders');
        Schema::dropIfExists('patronage_saint');
        Schema::dropIfExists('patronages');
        Schema::dropIfExists('feast_days');
        Schema::dropIfExists('saint_aliases');
        Schema::dropIfExists('saints');
        Schema::dropIfExists('citations');
        Schema::dropIfExists('sources');
    }
};
