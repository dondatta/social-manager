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
        Schema::create('automation_cooldowns', function (Blueprint $table) {
            $table->id();
            $table->string('instagram_user_id');
            $table->string('action_type');
            $table->timestamp('expires_at');
            $table->timestamps();
            
            $table->index(['instagram_user_id', 'action_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automation_cooldowns');
    }
};
