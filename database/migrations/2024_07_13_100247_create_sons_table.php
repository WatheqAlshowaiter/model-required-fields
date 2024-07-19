<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WatheqAlshowaiter\ModelRequiredFields\Constants;

class CreateSonsTable extends Migration
{
    public function up(): void
    {
        Schema::create('sons', function (Blueprint $table) {

            if ((float) App::version() >= Constants::VERSION_AFTER_UUID_SUPPORT && DB::connection()->getDriverName() !== 'mariadb') {
                $table->uuid('id')->primary(); // primary key => ignored
            } else {
                $table->bigIncrements('id'); // primary key => ignored
            }

            if ((float) App::version() >= Constants::VERSION_AFTER_FOREIGN_ID_SUPPORT) {
                $table->foreignId('father_id')->constrained(); // required
            } else {
                $table->unsignedBigInteger('father_id');
                $table->foreign('father_id')->references('id')->on('fathers'); // required
            }

            if ((float) App::version() >= Constants::VERSION_AFTER_ULID_SUPPORT && DB::connection()->getDriverName() !== 'sqlsrv') {
                $table->foreignUlid('mother_id')->nullable()->constrained(); // nullable => ignored
            } else {
                if ((float) App::version() >= Constants::VERSION_AFTER_FOREIGN_ID_SUPPORT) {
                    $table->foreignId('mother_id')->nullable()->constrained(); // nullable => ignored
                } else {
                    $table->unsignedBigInteger('mother_id')->nullable();
                    $table->foreign('mother_id')->references('id')->on('mothers');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sons');
    }
}
