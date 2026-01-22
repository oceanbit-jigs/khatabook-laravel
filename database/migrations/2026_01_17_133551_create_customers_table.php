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
        Schema::create(Tables::CUSTOMERS, function (Blueprint $table) {
            $table->id();
            $table->foreignId(Columns::business_id)->constrained(Tables::BUSINESSES)->cascadeOnDelete();
            $table->string(Columns::name);
            $table->string(Columns::phone);
            $table->string(Columns::email)->nullable();
            $table->string(Columns::address)->nullable();
            $table->decimal(Columns::opening_balance, 15, 2)->default(0.0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Tables::CUSTOMERS);
    }
};
