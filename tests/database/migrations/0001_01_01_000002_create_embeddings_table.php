<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('related-content.tables.embeddings', 'embeddings');

        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->morphs('embeddable');
            // For SQLite testing, we use text instead of vector
            $table->text('embedding')->nullable();
            $table->string('model')->nullable();
            $table->unsignedInteger('dimensions')->nullable();
            $table->timestamps();

            $table->unique(['embeddable_type', 'embeddable_id']);
        });
    }

    public function down(): void
    {
        $tableName = config('related-content.tables.embeddings', 'embeddings');
        Schema::dropIfExists($tableName);
    }
};
