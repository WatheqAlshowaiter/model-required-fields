<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('child_test_models', function (Blueprint $table) {
            $table->uuid('id')->primary(); // primary key => ignored
            $table->foreignId('parent_id')->constrained(); // required
            $table->foreignUlid('another_parent_id')->nullable()->constrained(); // nullable => ignored
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('children');
    }
};
