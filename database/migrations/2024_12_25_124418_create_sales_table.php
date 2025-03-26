<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSalesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->decimal('items', 10, 3); // Cambiado a decimal para manejar cantidades no enteras
            $table->decimal('total', 10, 2)->default(0); // Total en formato decimal
            $table->decimal('cash', 10, 2); // Efectivo recibido
            $table->decimal('change', 10, 2); // Cambio entregado
            $table->enum('status', ['PAGADO', 'PENDIENTE', 'CANCELADO'])->default('PAGADO');
            $table->enum('payment_type', ['CONTADO', 'CREDITO'])->default('CONTADO');
            $table->enum('payment_method', ['EFECTIVO', 'TDD', 'TDC', 'TRANS', 'TIGO', 'CHEQUE', 'OTROS'])
                ->default('EFECTIVO');
            $table->decimal('discount', 10, 2)->nullable(); // Descuento como monto decimal
            $table->decimal('discount_total', 10, 2)->nullable(); // Total del descuento aplicado
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
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
        Schema::dropIfExists('sales');
    }
}
