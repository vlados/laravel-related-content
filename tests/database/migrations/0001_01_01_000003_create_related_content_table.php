<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('related-content.tables.related_content', 'related_content');

        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->morphs('source');
            $table->morphs('related');
            $table->float('similarity')->default(0);
            $table->timestamps();

            $table->unique(['source_type', 'source_id', 'related_type', 'related_id'], 'unique_relation');
            $table->index(['source_type', 'source_id', 'similarity']);
        });
    }

    public function down(): void
    {
        $tableName = config('related-content.tables.related_content', 'related_content');
        Schema::dropIfExists($tableName);
    }
};
