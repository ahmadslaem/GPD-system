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
       Schema::create('family_members', function(Blueprint $table){

        $table->id();


        $table->foreignId('family_id')
              ->constrained()
              ->cascadeOnDelete();


        $table->string('name');


        $table->string('national_id')
              ->nullable();


        $table->date('birth_date')
              ->nullable();


        $table->enum(
            'gender',
            [
                'male',
                'female'
            ]
        );


        $table->boolean('has_disability')
              ->default(false);


        $table->timestamps();
       });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('family_members');
    }
};
