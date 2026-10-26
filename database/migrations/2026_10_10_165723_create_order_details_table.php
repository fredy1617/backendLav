
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_details', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->foreignId('service_id')
                ->nullable()
                ->constrained('services')
                ->nullOnDelete();

            // Copias del catálogo para conservar el historial.
            $table->string('service_name', 150);
            $table->string('pricing_type', 20);

            // Permite cantidades fraccionarias, por ejemplo 2.500 kg.
            $table->decimal('quantity', 12, 3)->default(1);

            // El precio puede modificarse sin cambiar el catálogo.
            $table->decimal('unit_price', 12, 2)->default(0);

            $table->decimal('subtotal', 12, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_details');
    }
};
