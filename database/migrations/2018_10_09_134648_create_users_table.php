<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('FirstName');
            $table->string('LastName');
            $table->string('EmailId')->unique();
            $table->string('Password');
            $table->integer('UserType');
            $table->string('Title')->nullable();
            $table->string('Phone')->nulslable();
            $table->string('Currency')->nullable();
            $table->string('Address')->nullable();
            $table->string('City')->nullable();
            $table->string('State')->nullable();
            $table->string('Postal_code')->nullable();
            $table->string('Company_name')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}
