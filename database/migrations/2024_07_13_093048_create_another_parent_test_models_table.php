<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('another_parent_test_models', function (Blueprint $table) {
            $table->ulid('id')->primary(); // primary key => ignored
            $table->enum('types', ['one', 'two'])->default('one'); // default => ignored
            $table->uuid('uuid'); // required
            $table->ulid(); // required
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('another_parent_test_models');
    }
};
