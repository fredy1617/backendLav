
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->restrictOnDelete();

            $table->date('received_at');

            $table->date('estimated_delivery_at')->nullable();

            $table->string('status', 30)->default('Recibida');

            $table->text('observations')->nullable();

            $table->decimal('total', 12, 2)->default(0);

            $table->timestamps();

            $table->index(['status', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
