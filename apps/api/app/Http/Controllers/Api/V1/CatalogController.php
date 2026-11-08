<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Catalog\CatalogService;
use App\Http\Controllers\Controller;
use App\Http\Requests\CatalogListRequest;
use App\Http\Requests\CatalogWriteRequest;
use App\Models\Company;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CatalogController extends Controller
{
    public function index(CatalogListRequest $request, Company $company, CatalogService $service)
    {
        return ApiResponse::page($service->listing($request->user(), $company, $request->route('resource'), $request->validated()));
    }

    public function show(Request $request, Company $company, string $record, CatalogService $service)
    {
        $resource = $request->route('resource');
        Gate::authorize('catalog.view', [$company, $resource]);

        return ApiResponse::data($service->resource($resource, $service->find($company, $resource, $record)));
    }

    public function store(CatalogWriteRequest $request, Company $company, CatalogService $service)
    {
        $resource = $request->route('resource');

        return ApiResponse::data($service->resource($resource, $service->save($request->user(), $company, $resource, $request->validated())), 201);
    }

    public function update(CatalogWriteRequest $request, Company $company, string $record, CatalogService $service)
    {
        $resource = $request->route('resource');

        return ApiResponse::data($service->resource($resource, $service->save($request->user(), $company, $resource, $request->validated(), $record)));
    }
}
