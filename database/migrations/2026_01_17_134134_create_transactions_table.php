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
        Schema::create(Tables::TRANSACTIONS, function (Blueprint $table) {
            $table->id();
            $table->foreignId(Columns::business_id)->constrained(Tables::BUSINESSES)->cascadeOnDelete();
            $table->foreignId(Columns::customer_id)->constrained(Tables::CUSTOMERS)->cascadeOnDelete();
            $table->enum(Columns::transaction_type, [Enums::INCOME, Enums::EXPENSE]);
            $table->decimal(Columns::amount, 15, 2)->default(0.0);
            $table->string(Columns::description)->nullable();
            $table->string(Columns::transaction_date);
            $table->enum(Columns::payment_mode, [Enums::CASH, Enums::ONLINE, Enums::CARD]);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Tables::TRANSACTIONS);
    }
};
