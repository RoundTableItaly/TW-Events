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
        Schema::table('activities', function (Blueprint $table) {
            // Index for soft deletes queries (whereNull/whereNotNull deleted_at)
            $table->index('deleted_at', 'activities_deleted_at_index');
            
            // Index for start_date queries (used in statistics and ordering)
            $table->index('start_date', 'activities_start_date_index');
            
            // Index for rt_type grouping queries
            $table->index('rt_type', 'activities_rt_type_index');
            
            // Composite index for common query pattern: deleted_at + start_date
            $table->index(['deleted_at', 'start_date'], 'activities_deleted_at_start_date_index');
            
            // Composite index for api_endpoint_id + deleted_at (used in statistics)
            $table->index(['api_endpoint_id', 'deleted_at'], 'activities_api_endpoint_deleted_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropIndex('activities_deleted_at_index');
            $table->dropIndex('activities_start_date_index');
            $table->dropIndex('activities_rt_type_index');
            $table->dropIndex('activities_deleted_at_start_date_index');
            $table->dropIndex('activities_api_endpoint_deleted_index');
        });
    }
};

