<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\ManageCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\CompanyListRequest;
use App\Http\Requests\CompanyWriteRequest;
use App\Models\Company;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CompanyController extends Controller
{
    public function index(CompanyListRequest $request)
    {
        $query = Company::query()->whereExists(fn ($q) => $q->selectRaw('1')->from('company_users')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'company_users.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->whereColumn('company_users.company_id', 'companies.id')->where('company_users.user_id', $request->user()->id)->where('permissions.code', 'companies.view'));
        $query->where(function ($q) use ($request) {
            $q->where('active', true);
            if ($request->boolean('include_inactive')) {
                $q->orWhereExists(fn ($membership) => $membership->selectRaw('1')->from('company_users')
                    ->join('role_permissions', 'role_permissions.role_id', '=', 'company_users.role_id')->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                    ->whereColumn('company_users.company_id', 'companies.id')->where('company_users.user_id', $request->user()->id)->where('permissions.code', 'companies.manage'));
            }
        });
        if ($request->input('q')) {
            $query->where(fn ($q) => $q->where('trade_name', 'like', '%'.$request->input('q').'%')->orWhere('legal_name', 'like', '%'.$request->input('q').'%'));
        }
        $page = $query->orderBy('trade_name')->orderBy('id')->paginate((int) $request->input('per_page', 20));

        return response()->json(['data' => $page->getCollection()->map(fn ($company) => $this->resource($company)),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'can_create' => Gate::allows('create', Company::class)], 'request_id' => $request->attributes->get('request_id')]);
    }

    public function show(Request $request, Company $company)
    {
        Gate::authorize('view', $company);

        return ApiResponse::data($this->resource($company));
    }

    public function store(CompanyWriteRequest $request, ManageCompany $service)
    {
        return ApiResponse::data($this->resource($service->save($request->user(), $request->validated())), 201);
    }

    public function update(CompanyWriteRequest $request, Company $company, ManageCompany $service)
    {
        return ApiResponse::data($this->resource($service->save($request->user(), $request->validated(), $company)));
    }

    private function resource(Company $company): array
    {
        $permissions = request()->user()->companies()->where('companies.id', $company->id)
            ->join('role_permissions', 'role_permissions.role_id', '=', 'company_users.role_id')->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')->distinct()->pluck('permissions.code')->all();

        return [...$company->only(['public_id', 'legal_name', 'trade_name', 'timezone', 'active', 'is_demo']), 'name' => $company->trade_name, 'permissions' => $permissions];
    }
}
