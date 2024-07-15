<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;
use WatheqAlshowaiter\ModelRequiredFields\Constants;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mothers', function (Blueprint $table) {
            $table->ulid('id')->primary(); // primary key => ignored
            $table->enum('types', ['one', 'two'])->default('one'); // default => ignored
            $table->uuid('uuid'); // required
            if ((float) App::version() >= Constants::VERSION_AFTER_ULID_SUPPORT) {
                $table->ulid('ulid'); // required
            } else {
                $table->string('ulid'); // required
            }
            $table->json('description')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('another_parent_test_models');
    }
};
