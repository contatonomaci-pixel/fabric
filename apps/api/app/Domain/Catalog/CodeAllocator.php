<?php

namespace App\Domain\Catalog;

use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CodeAllocator
{
    public function lock(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('A emissão de códigos exige transação.');
        }
        if (! DB::table('internal_code_sequences')->where('id', 1)->lockForUpdate()->first()) {
            throw new \LogicException('Sequência interna não inicializada.');
        }
    }

    public function next(): string
    {
        $this->lock();
        $value = DB::table('internal_code_sequences')->where('id', 1)->value('value') + 1;
        if ($value > 999999999999) {
            throw ValidationException::withMessages(['sku' => 'Sequência interna esgotada; solicite revisão da configuração.']);
        }
        DB::table('internal_code_sequences')->where('id', 1)->update(['value' => $value]);

        return 'FC-'.str_pad((string) $value, 12, '0', STR_PAD_LEFT);
    }

    public function reserve(Company $company, CommercialSku $sku, string $namespace, string $code): void
    {
        $entry = new CodeReservation;
        $entry->forceFill(['company_id' => $company->id, 'sku_id' => $sku->id, 'namespace' => $namespace, 'scope' => $namespace === 'sku' ? $company->public_id : 'global', 'code' => $code])->save();
    }
}
