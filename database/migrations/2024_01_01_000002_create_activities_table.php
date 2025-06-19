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
        Schema::create('activities', function (Blueprint $table) {
            // This ID comes from the external API, so it's not auto-incrementing.
            $table->bigInteger('id')->primary();
            
            $table->integer('level_id');
            $table->string('name');
            $table->string('type');
            $table->text('description')->nullable();
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->string('rt_type');
            $table->string('rt_visibility');
            
            $table->string('location')->nullable();
            $table->string('cover_picture')->nullable();
            $table->boolean('canceled')->default(false);

            // Using decimal for precision is better for coordinates.
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            // Foreign key to link back to the source API endpoint.
            $table->foreignId('api_endpoint_id')->constrained('api_endpoints')->onDelete('cascade');
            
            $table->timestamps();

            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
}; 