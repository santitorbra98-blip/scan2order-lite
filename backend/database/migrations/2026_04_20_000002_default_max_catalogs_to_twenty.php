<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereNull('max_catalogs')->update(['max_catalogs' => 20]);

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_catalogs')->default(20)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_catalogs')->nullable()->default(null)->change();
        });
    }
};
