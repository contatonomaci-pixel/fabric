<?php

namespace Database\Seeders;

use App\Domain\Catalog\UnitDefaults;
use App\Models\Company;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        Company::query()->each(fn ($company) => UnitDefaults::ensure($company));
    }
}
