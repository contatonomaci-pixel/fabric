<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Inventory\InventoryAccess;
use App\Domain\Inventory\InventoryDirectory;
use App\Domain\Inventory\InventoryRead;
use App\Domain\Inventory\InventoryService;
use App\Http\Controllers\Controller;
use App\Http\Requests\InventoryListRequest;
use App\Http\Requests\InventoryWriteRequest;
use App\Models\Company;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(InventoryListRequest $request, Company $company, InventoryRead $read)
    {
        return ApiResponse::page($read->listing($request->user(), $company, $request->route('resource'), $request->validated()));
    }

    public function show(Request $request, Company $company, string $record, InventoryRead $read)
    {
        $resource = $request->route('resource');
        InventoryAccess::authorize($request->user(), $company, InventoryRead::permission($resource));
        $class = InventoryRead::MODELS[$resource];
        $model = $class::where('company_id', $company->id)->where('public_id', $record)->firstOrFail();
        $read->authorizeRecord($request->user(), $company, $resource, $model);

        return ApiResponse::data($read->resource($request->user(), $company, $resource, $model));
    }

    public function store(InventoryWriteRequest $request, Company $company, InventoryDirectory $directory, InventoryRead $read)
    {
        $resource = $request->route('resource');

        return ApiResponse::data($read->resource($request->user(), $company, $resource, $directory->save($request->user(), $company, $resource, $request->validated())), 201);
    }

    public function update(InventoryWriteRequest $request, Company $company, string $record, InventoryDirectory $directory, InventoryRead $read)
    {
        $resource = $request->route('resource');

        return ApiResponse::data($read->resource($request->user(), $company, $resource, $directory->save($request->user(), $company, $resource, $request->validated(), $record)));
    }

    public function command(InventoryWriteRequest $request, Company $company, InventoryService $service, InventoryRead $read)
    {
        $operation = $service->execute($request->user(), $company, $request->route('kind'), $request->validated());

        return ApiResponse::data($read->resource($request->user(), $company, 'operations', $operation), 201);
    }
}
