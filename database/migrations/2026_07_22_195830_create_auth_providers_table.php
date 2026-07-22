<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stores one row per (user, provider) link. This is what allows the
     * system to support Google today and Apple/GitHub/Facebook later
     * without touching the users table or adding new columns per provider.
     */
    public function up(): void
    {
        Schema::create('auth_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // e.g. "google", "apple", "github", "facebook"
            $table->string('provider');

            // The provider's stable unique identifier for the account
            // (Google: the "sub" claim from the ID token)
            $table->string('provider_user_id');

            $table->string('avatar')->nullable();
            $table->timestamps();

            // A given provider account can only ever be linked to one user.
            $table->unique(['provider', 'provider_user_id']);

            // Fast lookup of "does this user have a google link?"
            $table->index(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_providers');
    }
};
