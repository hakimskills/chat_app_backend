<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            // Denormalized cache for fast "sort conversation list by most
            // recent activity" without a join+aggregate on every request.
            $table->foreignId('last_message_id')->nullable()
                  ->after('direct_key')
                  ->constrained('messages')->nullOnDelete();
        });

        Schema::table('conversation_participants', function (Blueprint $table) {
            // The read cursor: "this participant has read up through this
            // message." Unread count = messages in the conversation with
            // id > last_read_message_id (and created after joined_at).
            $table->foreignId('last_read_message_id')->nullable()
                  ->after('role')
                  ->constrained('messages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_message_id');
        });

        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_read_message_id');
        });
    }
};