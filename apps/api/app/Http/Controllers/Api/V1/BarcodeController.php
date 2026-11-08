<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Catalog\BarcodeRenderer;
use App\Domain\Catalog\CatalogEvent;
use App\Domain\Catalog\CatalogService;
use App\Domain\Catalog\CommercialSku;
use App\Domain\Catalog\ProductBarcode;
use App\Http\Controllers\Controller;
use App\Http\Requests\BarcodeRequest;
use App\Http\Requests\CatalogListRequest;
use App\Models\Company;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BarcodeController extends Controller
{
    public function reserve(BarcodeRequest $request, Company $company, CatalogService $service)
    {
        Gate::authorize('catalog.manage', [$company, 'commercial-skus']);
        $data = $request->validated();
        $sku = $service->find($company, 'commercial-skus', $data['sku_id']);

        return ApiResponse::data($service->barcode($request->user(), $company, $sku)->only(['public_id', 'code', 'kind']));
    }

    public function validateCode(BarcodeRequest $request, Company $company, CatalogService $service)
    {
        Gate::authorize('catalog.view', [$company, 'commercial-skus']);
        $data = $request->validated();
        $barcode = ProductBarcode::where('company_id', $company->id)->where('code', $data['code'])->first();
        $sku = $barcode ? CommercialSku::where('company_id', $company->id)->whereKey($barcode->sku_id)->firstOrFail() : null;

        return ApiResponse::data(['recognized' => $barcode !== null, 'barcode' => $barcode?->only(['public_id', 'code', 'kind']), 'sku' => $sku ? ['public_id' => $sku->public_id, 'sku' => $sku->sku, 'name' => $sku->name, 'effective_active' => $service->available('commercial-skus', $sku)] : null, 'gtin_official' => false, 'retail_approved' => false]);
    }

    public function export(Request $request, Company $company, string $record, string $format, CatalogService $service, BarcodeRenderer $renderer)
    {
        Gate::authorize('catalog.view', [$company, 'commercial-skus']);
        $sku = $service->find($company, 'commercial-skus', $record);
        $barcode = ProductBarcode::where('company_id', $company->id)->where('sku_id', $sku->id)->firstOrFail();
        $content = match ($format) {
            'svg' => $renderer->svg($barcode->code),
            'png' => $renderer->png($barcode->code),
            'pdf' => $renderer->pdf($barcode, $sku),
        };

        return response($content)->header('Content-Type', ['svg' => 'image/svg+xml', 'png' => 'image/png', 'pdf' => 'application/pdf'][$format])
            ->header('Content-Disposition', 'attachment; filename="'.$barcode->code.'.'.$format.'"')->header('Cache-Control', 'private, no-store');
    }

    public function history(CatalogListRequest $request, Company $company)
    {
        abort_unless($request->user()->hasCompanyPermission($company, 'catalog.audit'), 403);
        $query = CatalogEvent::where('company_id', $company->id);
        if ($request->input('resource_id')) {
            $query->where('resource_public_id', $request->input('resource_id'));
        }
        $page = $query->orderByDesc('id')->paginate($request->integer('per_page', 20));
        $actors = User::whereIn('id', $page->getCollection()->pluck('actor_id')->filter()->unique())->get(['id', 'public_id', 'name'])->keyBy('id');
        $page->setCollection($page->getCollection()->map(fn ($event) => [...$event->only(['public_id', 'resource', 'resource_public_id', 'action', 'before', 'after', 'created_at']), 'actor' => $actors->get($event->actor_id)?->only(['public_id', 'name'])]));

        return ApiResponse::page($page);
    }
}
