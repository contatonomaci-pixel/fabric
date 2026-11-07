<?php

namespace App\Domain\Catalog;

final class CatalogDefinition
{
    public const MODELS = ['factories' => Factory::class, 'warehouses' => Warehouse::class, 'units' => Unit::class, 'product-families' => ProductFamily::class, 'products' => Product::class, 'product-variants' => ProductVariant::class, 'commercial-skus' => CommercialSku::class];

    public const FIELDS = [
        'factories' => ['name', 'timezone', 'active'],
        'warehouses' => ['name', 'kind', 'active'],
        'units' => ['code', 'name', 'dimension', 'ratio_to_base', 'active'],
        'product-families' => ['name', 'active'],
        'products' => ['name', 'type', 'active'],
        'product-variants' => ['name', 'flavor', 'nominal_unit_weight_g', 'attributes', 'active'],
        'commercial-skus' => ['name', 'sku', 'packaging_qty', 'nominal_net_weight_g', 'variable_weight', 'price', 'active'],
    ];

    public const REFERENCES = [
        'warehouses' => ['factory_id' => Factory::class],
        'product-families' => ['parent_id' => ProductFamily::class],
        'products' => ['family_id' => ProductFamily::class],
        'product-variants' => ['product_id' => Product::class],
        'commercial-skus' => ['variant_id' => ProductVariant::class, 'unit_id' => Unit::class],
    ];

    public static function permission(string $resource, bool $write): string
    {
        return in_array($resource, ['factories', 'warehouses'], true)
            ? ($write ? 'companies.manage' : 'companies.view')
            : ($write ? 'catalog.manage' : 'catalog.view');
    }
}
