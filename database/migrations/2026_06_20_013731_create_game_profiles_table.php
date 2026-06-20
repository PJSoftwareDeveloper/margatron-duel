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
        Schema::create('game_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('level')->default(1);
            $table->unsignedInteger('exp')->default(0);
            $table->unsignedInteger('exp_max')->default(20);
            $table->unsignedInteger('gold')->default(100);
            $table->unsignedSmallInteger('pa')->default(20);
            $table->unsignedSmallInteger('pa_max')->default(20);
            $table->unsignedSmallInteger('vitality')->default(5);
            $table->unsignedSmallInteger('strength')->default(5);
            $table->unsignedSmallInteger('luck')->default(5);
            $table->unsignedSmallInteger('attribute_points')->default(0);
            $table->unsignedInteger('hp')->default(50);
            $table->unsignedInteger('hp_max')->default(50);
            $table->unsignedSmallInteger('dmg_min')->default(1);
            $table->unsignedSmallInteger('dmg_max')->default(2);
            $table->unsignedSmallInteger('armor')->default(0);
            $table->decimal('crit_chance', 5, 2)->unsigned()->default(5);
            $table->decimal('crit_power', 6, 2)->unsigned()->default(150);
            $table->decimal('dodge', 5, 2)->unsigned()->default(3);
            $table->decimal('stun', 5, 2)->unsigned()->default(0);
            $table->unsignedTinyInteger('current_map_id')->default(1);
            $table->json('stage_progress');
            $table->json('inventory');
            $table->json('equipped');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_profiles');
    }
};
