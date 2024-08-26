<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WatheqAlshowaiter\ModelRequiredFields\Tests\Constants;

class CreateMothersTable extends Migration
{
    public function up(): void
    {
        Schema::create('mothers', function (Blueprint $table) {
            if ((float) App::version() >= Constants::VERSION_AFTER_ULID_SUPPORT && DB::connection()->getDriverName() !== 'sqlsrv') {
                $table->ulid('id')->primary(); // primary key => ignored
            } else {
                $table->bigIncrements('id'); // primary key => ignored
            }

            $table->enum('types', ['one', 'two'])->default('one'); // default => ignored

            if ((float) App::version() >= Constants::VERSION_AFTER_UUID_SUPPORT && DB::connection()->getDriverName() !== 'mariadb') {
                $table->uuid('uuid'); // required
            } else {
                $table->string('uuid');
            }
            if ((float) App::version() >= Constants::VERSION_AFTER_ULID_SUPPORT) {
                $table->ulid('ulid'); // required
            } else {
                $table->string('ulid'); // required
            }

            if (DB::connection()->getDriverName() === 'mariadb') {
                $table->json('description')->nullable();
            } else {
                $table->text('description')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('another_parent_test_models');
    }
}
