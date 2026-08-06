<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();

            // Nullable to allow system messages, e.g. "Jane added Marc to the group"
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('type', ['text', 'image', 'video', 'audio', 'file', 'system'])
                  ->default('text');

            // Nullable — an attachment-only message may have no text body.
            $table->text('body')->nullable();

            $table->foreignId('reply_to_message_id')->nullable()
                  ->constrained('messages')->nullOnDelete();

            $table->timestamp('edited_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Pagination pattern: "give me messages in this conversation,
            // newest first" is the single most common query in a chat app.
            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};