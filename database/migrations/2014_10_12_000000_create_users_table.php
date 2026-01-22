<?php

use App\Constants\Columns;
use App\Constants\Tables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(Tables::USERS, function (Blueprint $table) {
            $table->bigIncrements(Columns::id);
            $table->string(Columns::name)->nullable();
            $table->string(Columns::email)->unique();
            $table->boolean(Columns::is_email_verify)->default(false);
            $table->string(Columns::phone, 12)->unique();
            $table->boolean(Columns::is_phone_verify)->default(false);
            $table->string(Columns::image_url)->nullable();
            $table->string(Columns::fcm_token)->nullable();
            $table->string(Columns::password)->nullable();
            $table->timestamp(Columns::email_verified_at)->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
            

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists(Tables::USERS);
    }
}
