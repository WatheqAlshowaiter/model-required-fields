<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;
use WatheqAlshowaiter\ModelRequiredFields\Constants;

class CreateFathersTable extends Migration
{
    public function up(): void
    {
        Schema::create('fathers', function (Blueprint $table) {
            if ((float) App::version() >= Constants::VERSION_AFTER_ID_METHOD_SUPPORT) {
                $table->id(); // primary key -> ignored
            } else {
                $table->bigIncrements('id'); // primary key -> ignored
            }
            $table->boolean('active')->default(false); // default => ignored
            $table->string('name'); // required
            $table->string('email'); // required
            $table->string('username')->nullable()->unique(); // nullable => ignored even with unique index
            $table->timestamps(); // created_at, updated_at => ignored because they are nullable
            $table->softDeletes(); // deleted_at => ignored because it is nullable
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_test_models');
    }
}
