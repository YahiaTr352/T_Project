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
        Schema::create('profile_update_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->string('requested_by_auth', 50)->default('whatsapp'); // face, whatsapp, session, etc.
            $table->string('change_type', 50); // profile_info, contact_add, contact_update, contact_delete, child_add, marriage_add, marriage_update, location_add, location_update, location_delete
            $table->unsignedBigInteger('target_id')->nullable(); // Target ID for existing contact, marriage, location if updating/deleting
            $table->json('old_data')->nullable();
            $table->json('proposed_data');
            $table->json('attached_files')->nullable(); // Array of temporary uploaded file paths
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['person_id', 'status']);
            $table->index('change_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profile_update_requests');
    }
};
