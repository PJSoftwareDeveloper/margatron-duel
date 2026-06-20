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
        Schema::table('game_profiles', function (Blueprint $table): void {
            $table->json('rest_tasks')->nullable()->after('legendary_items_found');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_profiles', function (Blueprint $table): void {
            $table->dropColumn('rest_tasks');
        });
    }
};
