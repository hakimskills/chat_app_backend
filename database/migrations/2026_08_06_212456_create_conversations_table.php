<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['private', 'group'])->default('private');

            // Group chats only — null for private conversations.
            $table->string('name')->nullable();
            $table->string('avatar')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // Dedup key for private chats: sorted "smallerUserId_largerUserId",
            // e.g. "3_17". Unique so the app can never accidentally create two
            // separate private conversations between the same two users —
            // enforce "find or create" logic against this in the backend.
            // Always null for group conversations.
            $table->string('direct_key')->nullable()->unique();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};