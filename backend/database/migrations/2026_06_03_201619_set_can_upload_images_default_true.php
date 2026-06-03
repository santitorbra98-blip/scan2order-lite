<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Change the column default to true so new admin users can upload images out of the box.
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('can_upload_images')->default(true)->change();
        });

        // Enable upload access for all existing users that were created with the old false default.
        DB::table('users')->where('can_upload_images', false)->update(['can_upload_images' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('can_upload_images')->default(false)->change();
        });
    }
};
