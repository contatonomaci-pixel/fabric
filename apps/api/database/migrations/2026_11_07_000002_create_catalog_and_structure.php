<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['factories', 'warehouses', 'units_of_measure', 'product_families', 'products', 'product_variants', 'commercial_skus', 'product_barcodes'] as $name) {
            Schema::create($name, function (Blueprint $t) use ($name) {
                $t->id();
                $t->ulid('public_id')->unique();
                $t->foreignId('company_id')->constrained()->restrictOnDelete();
                $t->boolean('active')->default(true);
                $t->timestamps();
                // Chave composta permite FKs que também garantem o tenant no banco.
                $t->unique(['id', 'company_id'], $name.'_tenant_key');
                $t->index(['company_id', 'active']);
                if ($name === 'factories') {
                    $t->string('name', 150);
                    $t->string('timezone', 80);
                } elseif ($name === 'warehouses') {
                    $t->unsignedBigInteger('factory_id');
                    $t->string('name', 150);
                    $t->string('kind', 30);
                    $t->foreign(['factory_id', 'company_id'])->references(['id', 'company_id'])->on('factories')->restrictOnDelete();
                } elseif ($name === 'units_of_measure') {
                    $t->string('code', 12);
                    $t->string('name', 80);
                    $t->string('dimension', 12);
                    $t->decimal('ratio_to_base', 18, 6)->nullable();
                    $t->unique(['company_id', 'code']);
                } elseif ($name === 'product_families') {
                    $t->string('name', 150);
                    $t->unsignedBigInteger('parent_id')->nullable();
                    $t->foreign(['parent_id', 'company_id'])->references(['id', 'company_id'])->on('product_families')->restrictOnDelete();
                } elseif ($name === 'products') {
                    $t->string('name', 150);
                    $t->string('type', 20);
                    $t->unsignedBigInteger('family_id');
                    $t->foreign(['family_id', 'company_id'])->references(['id', 'company_id'])->on('product_families')->restrictOnDelete();
                } elseif ($name === 'product_variants') {
                    $t->unsignedBigInteger('product_id');
                    $t->string('name', 150);
                    $t->string('flavor', 100)->nullable();
                    $t->decimal('nominal_unit_weight_g', 18, 6)->nullable();
                    $t->json('attributes')->nullable();
                    $t->foreign(['product_id', 'company_id'])->references(['id', 'company_id'])->on('products')->restrictOnDelete();
                } elseif ($name === 'commercial_skus') {
                    $t->unsignedBigInteger('variant_id');
                    $t->unsignedBigInteger('unit_id');
                    $t->string('name', 150);
                    $t->string('sku', 40);
                    $t->decimal('packaging_qty', 18, 6)->nullable();
                    $t->decimal('nominal_net_weight_g', 18, 6)->nullable();
                    $t->boolean('variable_weight')->default(false);
                    $t->decimal('price', 18, 4)->nullable();
                    $t->unique(['company_id', 'sku']);
                    $t->foreign(['variant_id', 'company_id'])->references(['id', 'company_id'])->on('product_variants')->restrictOnDelete();
                    $t->foreign(['unit_id', 'company_id'])->references(['id', 'company_id'])->on('units_of_measure')->restrictOnDelete();
                } else {
                    $t->unsignedBigInteger('sku_id');
                    $t->string('kind', 30)->default('internal_code128');
                    $t->string('code', 40);
                    $t->unique(['kind', 'code']);
                    $t->unique(['company_id', 'sku_id', 'kind']);
                    $t->foreign(['sku_id', 'company_id'])->references(['id', 'company_id'])->on('commercial_skus')->restrictOnDelete();
                }
            });
        }
        // Contador técnico global: serializa a emissão, sem conter dados de empresas.
        Schema::create('internal_code_sequences', function (Blueprint $t) {
            $t->unsignedTinyInteger('id')->primary();
            $t->unsignedBigInteger('value')->default(0);
        });
        DB::table('internal_code_sequences')->insert(['id' => 1, 'value' => 0]);
        Schema::create('code_reservations', function (Blueprint $t) {
            $t->id();
            $t->ulid('public_id')->unique();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('sku_id');
            $t->string('namespace', 30);
            $t->string('scope', 30); // Sempre preenchido: global ou ULID da empresa.
            $t->string('code', 40);
            $t->timestamp('created_at');
            $t->unique(['namespace', 'scope', 'code'], 'code_reservations_namespace_scope_code_unique');
            $t->foreign(['sku_id', 'company_id'])->references(['id', 'company_id'])->on('commercial_skus')->restrictOnDelete();
        });
        Schema::create('catalog_events', function (Blueprint $t) {
            $t->id();
            $t->ulid('public_id')->unique();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('resource', 40);
            $t->ulid('resource_public_id');
            $t->string('action', 30);
            $t->json('before')->nullable();
            $t->json('after');
            $t->timestamp('created_at');
            $t->index(['company_id', 'created_at']);
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE units_of_measure ADD CONSTRAINT units_dimension_ratio CHECK ((dimension = \'package\' AND ratio_to_base IS NULL) OR (dimension IN (\'mass\', \'count\') AND ratio_to_base IS NOT NULL AND ratio_to_base > 0))');
            DB::statement('ALTER TABLE commercial_skus ADD CONSTRAINT skus_nonnegative_price CHECK (price IS NULL OR price >= 0), ADD CONSTRAINT skus_positive_pack CHECK (packaging_qty IS NULL OR packaging_qty > 0), ADD CONSTRAINT skus_positive_weight CHECK (nominal_net_weight_g IS NULL OR nominal_net_weight_g > 0)');
            DB::statement('ALTER TABLE product_variants ADD CONSTRAINT variants_positive_weight CHECK (nominal_unit_weight_g IS NULL OR nominal_unit_weight_g > 0)');
        }
    }

    public function down(): void
    {
        foreach (['catalog_events', 'code_reservations', 'internal_code_sequences', 'product_barcodes', 'commercial_skus', 'product_variants', 'products', 'product_families', 'units_of_measure', 'warehouses', 'factories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
