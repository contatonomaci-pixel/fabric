<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $create = function (string $name, Closure $fields) {
            Schema::create($name, function (Blueprint $t) use ($name, $fields) {
                $t->engine = 'InnoDB';
                $t->charset = 'utf8mb4';
                $t->id();
                $t->ulid('public_id')->unique();
                $t->foreignId('company_id')->constrained()->restrictOnDelete();
                $t->unique(['id', 'company_id'], $name.'_tenant_key');
                $t->timestamps();
                $fields($t);
            });
        };
        $ref = function (Blueprint $t, string $field, string $table, bool $nullable = false) {
            $column = $t->unsignedBigInteger($field);
            if ($nullable) {
                $column->nullable();
            }
            $t->foreign([$field, 'company_id'])->references(['id', 'company_id'])->on($table)->restrictOnDelete();
        };
        $amounts = function (Blueprint $t, string $prefix = '') {
            $t->decimal($prefix.'units', 18, 6)->default(0);
            $t->decimal($prefix.'grams', 18, 6)->default(0);
        };
        $create('inventory_operations', function ($t) use ($ref) {
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('kind', 30);
            $t->string('idempotency_key', 100);
            $t->string('request_hash', 64);
            $t->string('reason', 500);
            $ref($t, 'reversal_of_id', 'inventory_operations', true);
            $t->unique(['company_id', 'idempotency_key'], 'inventory_operation_idempotency');
            // NULL é intencional para originais; cada original admite só um estorno.
            $t->unique(['company_id', 'reversal_of_id'], 'inventory_operation_one_reversal');
            $t->index(['company_id', 'created_at']);
        });
        $create('suppliers', function ($t) {
            $t->string('name', 150);
            $t->string('tax_id', 40)->nullable();
            $t->string('email', 200)->nullable();
            $t->string('phone', 40)->nullable();
            $t->boolean('active')->default(true);
        });
        $create('inventory_policies', function ($t) use ($ref) {
            $ref($t, 'variant_id', 'product_variants');
            $t->string('mode', 10);
            $t->boolean('requires_expiry');
            $t->boolean('active')->default(true);
            $t->unique(['company_id', 'variant_id']);
        });
        $create('locations', function ($t) use ($ref) {
            $ref($t, 'warehouse_id', 'warehouses');
            $t->string('name', 150);
            $t->string('code', 40);
            $t->boolean('active')->default(true);
            $t->unique(['company_id', 'warehouse_id', 'code']);
        });
        $create('purchase_orders', function ($t) use ($ref) {
            $ref($t, 'operation_id', 'inventory_operations');
            $ref($t, 'supplier_id', 'suppliers');
            $t->string('reference', 100)->nullable();
            $t->date('expected_on')->nullable();
            $t->string('status', 30)->default('open');
            $t->unique(['company_id', 'operation_id']);
        });
        $create('purchase_order_items', function ($t) use ($ref) {
            $ref($t, 'order_id', 'purchase_orders');
            $ref($t, 'variant_id', 'product_variants');
            $ref($t, 'policy_id', 'inventory_policies');
            $ref($t, 'unit_id', 'units_of_measure');
            $t->decimal('quantity', 18, 6);
            $t->decimal('received_quantity', 18, 6)->default(0);
            $t->decimal('unit_cost', 18, 4);
            $t->decimal('total_cost', 18, 4);
        });
        $create('inventory_lots', function ($t) use ($ref) {
            $ref($t, 'variant_id', 'product_variants');
            $ref($t, 'policy_id', 'inventory_policies');
            $ref($t, 'supplier_id', 'suppliers');
            $t->string('code', 40);
            $t->string('supplier_lot_code', 100);
            $t->date('manufactured_on')->nullable();
            $t->date('expires_on')->nullable();
            $t->string('no_expiry_reason', 500)->nullable();
            $t->string('quality_status', 20);
            $t->unique(['company_id', 'code']);
            $t->index(['company_id', 'variant_id', 'quality_status', 'expires_on'], 'inventory_lots_fefo');
        });
        $create('inventory_balances', function ($t) use ($ref, $amounts) {
            $ref($t, 'lot_id', 'inventory_lots');
            $ref($t, 'location_id', 'locations');
            $amounts($t);
            $amounts($t, 'reserved_');
            $t->unique(['company_id', 'lot_id', 'location_id']);
        });
        $create('purchase_receipts', function ($t) use ($ref) {
            $ref($t, 'operation_id', 'inventory_operations');
            $ref($t, 'order_item_id', 'purchase_order_items');
            $ref($t, 'balance_id', 'inventory_balances');
            $t->date('received_on');
            $t->decimal('quantity', 18, 6);
            $t->decimal('unit_cost', 18, 4);
            $t->decimal('total_cost', 18, 4);
            $t->unique(['company_id', 'operation_id']);
        });
        $create('inventory_reservations', function ($t) use ($ref) {
            $ref($t, 'operation_id', 'inventory_operations');
            $ref($t, 'variant_id', 'product_variants');
            $ref($t, 'warehouse_id', 'warehouses');
            $t->string('status', 20)->default('active');
            $t->unique(['company_id', 'operation_id']);
        });
        $create('inventory_allocations', function ($t) use ($ref, $amounts) {
            $ref($t, 'reservation_id', 'inventory_reservations');
            $ref($t, 'balance_id', 'inventory_balances');
            $amounts($t);
            $t->unique(['company_id', 'reservation_id', 'balance_id'], 'inventory_allocation_unique_position');
        });
        $create('inventory_movements', function ($t) use ($ref) {
            $ref($t, 'operation_id', 'inventory_operations');
            $ref($t, 'balance_id', 'inventory_balances');
            $t->decimal('units_delta', 18, 6);
            $t->decimal('grams_delta', 18, 6);
            $t->index(['company_id', 'balance_id', 'created_at'], 'inventory_movement_ledger');
        });
        $create('inventory_weighings', function ($t) use ($ref) {
            $ref($t, 'operation_id', 'inventory_operations');
            $ref($t, 'balance_id', 'inventory_balances');
            $t->decimal('gross_g', 18, 6);
            $t->decimal('tare_g', 18, 6);
            $t->decimal('net_g', 18, 6);
        });
        $create('inventory_counts', function ($t) use ($ref, $amounts) {
            $ref($t, 'operation_id', 'inventory_operations');
            $ref($t, 'balance_id', 'inventory_balances');
            $amounts($t, 'expected_');
            $amounts($t, 'actual_');
        });
        $create('inventory_events', function ($t) use ($ref) {
            $ref($t, 'operation_id', 'inventory_operations', true);
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('resource', 40);
            $t->ulid('resource_public_id');
            $t->json('before')->nullable();
            $t->json('after');
            $t->string('reason', 500);
            $t->index(['company_id', 'resource_public_id', 'created_at'], 'inventory_event_resource');
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE inventory_policies ADD CONSTRAINT inventory_policy_mode CHECK (mode IN ('mass','count','dual'))");
            DB::statement('ALTER TABLE inventory_balances ADD CONSTRAINT inventory_nonnegative_balance CHECK (units >= 0 AND grams >= 0 AND reserved_units >= 0 AND reserved_grams >= 0 AND reserved_units <= units AND reserved_grams <= grams)');
            DB::statement('ALTER TABLE purchase_order_items ADD CONSTRAINT purchase_item_quantities CHECK (quantity > 0 AND received_quantity >= 0 AND received_quantity <= quantity AND unit_cost >= 0 AND total_cost >= 0)');
            DB::statement('ALTER TABLE inventory_weighings ADD CONSTRAINT inventory_valid_tare CHECK (gross_g >= 0 AND tare_g >= 0 AND gross_g >= tare_g AND net_g = gross_g - tare_g)');
        }
    }

    public function down(): void
    {
        foreach (['inventory_events', 'inventory_counts', 'inventory_weighings', 'inventory_movements', 'inventory_allocations', 'inventory_reservations', 'purchase_receipts', 'inventory_balances', 'inventory_lots', 'purchase_order_items', 'purchase_orders', 'locations', 'inventory_policies', 'suppliers', 'inventory_operations'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
