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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->string('instagram_user_id')->index();
            $table->string('instagram_username')->nullable();
            $table->string('message_type'); // 'dm', 'comment', 'story_reply', 'story_mention', 'mention'
            $table->text('message_text');
            $table->string('media_id')->nullable(); // For comments/mentions
            $table->string('comment_id')->nullable(); // For comments
            $table->json('raw_payload')->nullable(); // Store full webhook payload
            $table->boolean('synced_to_hubspot')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
