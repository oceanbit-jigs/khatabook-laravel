<?php

use App\Constants\Columns;
use App\Constants\Tables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(Tables::BUSINESSES, function (Blueprint $table) {
            $table->id();
            $table->foreignId(Columns::user_id)->constrained(Tables::USERS)->cascadeOnDelete();
            $table->string(Columns::business_name)->required();
            $table->string(Columns::address)->nullable();
            $table->string(Columns::phone);
            $table->string(Columns::email)->nullable();
            $table->string(Columns::currency)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Tables::BUSINESSES);
    }
};
