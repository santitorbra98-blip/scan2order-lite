<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_restaurants')->nullable()->after('status')->comment('NULL = unlimited');
            $table->unsignedSmallInteger('max_catalogs')->nullable()->after('max_restaurants')->comment('NULL = unlimited');
            $table->unsignedSmallInteger('max_products')->nullable()->after('max_catalogs')->comment('NULL = unlimited');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['max_restaurants', 'max_catalogs', 'max_products']);
        });
    }
};
