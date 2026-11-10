<?php

namespace App\Domain\Inventory;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class InventoryAccess
{
    public const COMMANDS = [
        'order' => 'purchasing.manage', 'cancel-order' => 'purchasing.manage', 'receive' => 'purchasing.receive',
        'reserve' => 'inventory.reserve', 'consume' => 'inventory.issue', 'cancel-reservation' => 'inventory.reserve',
        'issue' => 'inventory.issue', 'transfer' => 'inventory.manage', 'count' => 'inventory.count',
        'weigh' => 'inventory.weigh', 'quality' => 'inventory.release', 'reverse' => 'inventory.reverse',
    ];

    public static function authorize(User $actor, Company $company, string $permission): void
    {
        Gate::forUser($actor)->authorize('inventory.access', [$company, $permission]);
    }

    public static function allowed(User $actor, Company $company, string $permission): bool
    {
        return $actor->hasCompanyPermission($company, $permission);
    }
}
