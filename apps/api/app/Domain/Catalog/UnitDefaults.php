<?php

namespace App\Domain\Catalog;

use App\Models\Company;

class UnitDefaults
{
    public static function ensure(Company $company): void
    {
        foreach (['UN' => ['Unidade', 'count', '1.000000'], 'g' => ['Grama', 'mass', '1.000000'], 'KG' => ['Quilograma', 'mass', '1000.000000'], 'PCT' => ['Pacote', 'package', null], 'CX' => ['Caixa', 'package', null]] as $code => [$name, $dimension, $ratio]) {
            if (! Unit::where('company_id', $company->id)->where('code', $code)->exists()) {
                $unit = new Unit;
                $unit->forceFill(['company_id' => $company->id, 'code' => $code, 'name' => $name, 'dimension' => $dimension, 'ratio_to_base' => $ratio])->save();
            }
        }
    }
}
