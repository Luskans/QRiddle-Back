<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('riddles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->constrained('users');
            $table->string('title');
            $table->text('description');
            $table->boolean('is_private')->default(false);
            $table->string('password')->nullable();
            $table->enum('status', ['draft', 'active', 'disabled'])->default('draft');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('riddles');
    }
};
