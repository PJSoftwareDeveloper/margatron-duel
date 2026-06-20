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
            $table->unsignedInteger('played_seconds')->default(0)->after('pa_regenerated_at');
            $table->timestamp('last_seen_at')->nullable()->after('played_seconds');
            $table->unsignedSmallInteger('vitality_points_assigned')->default(0)->after('luck');
            $table->unsignedSmallInteger('strength_points_assigned')->default(0)->after('vitality_points_assigned');
            $table->unsignedSmallInteger('luck_points_assigned')->default(0)->after('strength_points_assigned');
            $table->unsignedInteger('monsters_killed')->default(0)->after('stun');
            $table->unsignedInteger('unique_items_found')->default(0)->after('monsters_killed');
            $table->unsignedInteger('heroic_items_found')->default(0)->after('unique_items_found');
            $table->unsignedInteger('legendary_items_found')->default(0)->after('heroic_items_found');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'played_seconds',
                'last_seen_at',
                'vitality_points_assigned',
                'strength_points_assigned',
                'luck_points_assigned',
                'monsters_killed',
                'unique_items_found',
                'heroic_items_found',
                'legendary_items_found',
            ]);
        });
    }
};
