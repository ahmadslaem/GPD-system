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
      Schema::create('transfer_requests', function (Blueprint $table) {

    $table->id();

    $table->foreignId('family_id')
        ->constrained()
        ->cascadeOnDelete();


    $table->foreignId('from_camp_id')
        ->constrained('camps');


    $table->foreignId('to_camp_id')
        ->constrained('camps');


    $table->foreignId('requested_by')
        ->constrained('users');


    $table->text('reason');


    $table->enum('status',[
        'pending',
        'approved',
        'rejected'
    ])->default('pending');


    $table->text('manager_note')
        ->nullable();


    $table->foreignId('reviewed_by')
        ->nullable()
        ->constrained('users');


    $table->timestamps();

});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfer_requests');
    }
};
