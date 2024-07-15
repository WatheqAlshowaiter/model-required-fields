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
        Schema::create('sons', function (Blueprint $table) {
            $table->uuid('id')->primary(); // primary key => ignored
            $table->foreignId('parent_id')->constrained(); // required
            if ((float) App::version() >= Constants::VERSION_AFTER_ULID_SUPPORT) {
                $table->foreignUlid('mother_id')->nullable()->constrained(); // nullable => ignored
            } else {
                $table->foreignId('mother_id')->nullable()->constrained(); // nullable => ignored
            }
            $table->foreignId('father_id')->nullable()->constrained(); // nullable => ignored
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('children');
    }
};
