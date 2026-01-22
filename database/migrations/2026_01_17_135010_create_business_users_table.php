<?php

use App\Constants\Columns;
use App\Constants\Enums;
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
        Schema::create(Tables::BUSINESS_USERS, function (Blueprint $table) {
            $table->id();
            $table->foreignId(Columns::business_id)->constrained(Tables::BUSINESSES)->cascadeOnDelete();
            $table->foreignId(Columns::user_id)->constrained(Tables::USERS)->cascadeOnDelete();
            $table->enum(Columns::role, [Enums::OWNER, Enums::STAFF]);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Tables::BUSINESS_USERS);
    }
};
