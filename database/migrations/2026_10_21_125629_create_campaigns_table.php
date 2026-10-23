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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('channel'); // email, sms
            $table->text('audience')->nullable(); // all, confirmed, waitlisted, etc.
            $table->timestamp('scheduled_at')->nullable();
            $table->json('stats')->nullable(); // sent, opened, clicked
            $table->string('status')->default('draft'); // draft, scheduled, sent
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
