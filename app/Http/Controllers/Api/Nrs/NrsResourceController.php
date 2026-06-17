<?php

namespace App\Http\Controllers\Api\Nrs;

use App\Http\Controllers\Controller;
use App\Services\Nrs\NrsResourceService;
use Illuminate\Http\JsonResponse;

class NrsResourceController extends Controller
{
    public function __construct(
        protected NrsResourceService $resourceService
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
        return response()->json(['data' => $this->resourceService->getServiceCodes()]);
    }

    public function countries(): JsonResponse
    {
        return response()->json(['data' => $this->resourceService->getCountries()]);
    }

    public function lgas(): JsonResponse
    {
        return response()->json(['data' => $this->resourceService->getLgas()]);
    }

    public function states(): JsonResponse
    {
        return response()->json(['data' => $this->resourceService->getStates()]);
    }

    public function vatExemptions(): JsonResponse
    {
        return response()->json(['data' => $this->resourceService->getVatExemptions()]);
    }
}
