<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(
        protected CustomerService $customerService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $customers = Customer::where('organization_id', $request->user()->current_organization_id)
            ->latest()
            ->paginate($request->query('per_page', 15));

        return response()->json(CustomerResource::collection($customers)->response()->getData(true));
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = $this->customerService->create(
            $request->toServiceData(),
            $request->user()->current_organization_id
        );

        return response()->json([
            'message' => 'Customer created successfully.',
            'data' => new CustomerResource($customer),
        ], 201);
    }

    public function show(Request $request, Customer $customer): CustomerResource
    {
        $this->authorizeOrganizationAccess($customer, $request);

        return new CustomerResource($customer);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $this->authorizeOrganizationAccess($customer, $request);

        $this->customerService->update($customer, $request->toServiceData());

        return response()->json([
            'message' => 'Customer updated successfully.',
            'data' => new CustomerResource($customer->fresh()),
        ]);
    }

    public function destroy(Request $request, Customer $customer): JsonResponse
    {
        $this->authorizeOrganizationAccess($customer, $request);

        $customer->delete();

        return response()->json([
            'message' => 'Customer deleted successfully.',
        ]);
    }

    private function authorizeOrganizationAccess(Customer $customer, Request $request): void
    {
        if ($customer->organization_id !== $request->user()->current_organization_id) {
            abort(403, 'Unauthorized access to customer');
        }
    }
}
