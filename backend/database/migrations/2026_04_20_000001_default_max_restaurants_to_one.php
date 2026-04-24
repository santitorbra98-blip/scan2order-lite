<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fill NULLs before adding the NOT NULL default
        DB::table('users')->whereNull('max_restaurants')->update(['max_restaurants' => 1]);

        // Set column default to 1
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_restaurants')->default(1)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_restaurants')->nullable()->default(null)->change();
        });
    }
};
