<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('phone')->nullable();
                $table->string('address')->nullable();
                $table->string('avatar')->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->boolean('is_admin')->default(false);
                $table->foreignId('team_id')->nullable();
                $table->rememberToken();
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('users');
    }
};
