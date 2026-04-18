<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
 use Illuminate\Http\JsonResponse;
 
class NrsResourceController extends Controller
{
    public function __construct(
        protected \App\Services\Nrs\NrsResourceService $resourceService
    ) {}

    public function hsCodes(): JsonResponse
    {
        return response()->json(['data' => $this->resourceService->getHsCodes()]);
    }

    public function currencies(): JsonResponse
    {
        return response()->json(['data' => $this->resourceService->getCurrencies()]);
    }

    public function taxCategories(): JsonResponse
    {
        return response()->json(['data' => $this->resourceService->getTaxCategories()]);
    }

    public function invoiceTypes(): JsonResponse
    {
        return response()->json(['data' => $this->resourceService->getInvoiceTypes()]);
    }

    public function paymentMeans(): JsonResponse
    {
        return response()->json(['data' => $this->resourceService->getPaymentMeans()]);
    }

    public function serviceCodes(): JsonResponse
    {
        return response()->json(['data' => []]);
    }

    public function vatExemptions(): JsonResponse
    {
        return response()->json(['data' => []]);
    }
}