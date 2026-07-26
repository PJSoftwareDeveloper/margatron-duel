<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('game_profiles')->update([
            'hp' => DB::raw('hp_max'),
        ]);

        Schema::table('game_profiles', function (Blueprint $table): void {
            $table->dropColumn('hp_max');
        });
    }

    public function down(): void
    {
        Schema::table('game_profiles', function (Blueprint $table): void {
            $table->unsignedInteger('hp_max')->default(50)->after('hp');
        });

        DB::table('game_profiles')->update([
            'hp_max' => DB::raw('hp'),
        ]);
    }
};
