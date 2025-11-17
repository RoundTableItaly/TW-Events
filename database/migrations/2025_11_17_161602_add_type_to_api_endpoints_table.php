<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('api_endpoints', function (Blueprint $table) {
            $table->enum('type', ['Nazionale', 'Zona', 'Tavola'])->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_endpoints', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
