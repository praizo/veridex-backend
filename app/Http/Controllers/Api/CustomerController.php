<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'tin' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string'],
            'postal_zone' => ['nullable', 'string', 'max:20'],
            'country_code' => ['nullable', 'string', 'size:2'],
        ]);

        $mappedData = [
            'name' => $validated['name'],
            'tin' => $validated['tin'],
            'email' => $validated['email'],
            'telephone' => $validated['phone'] ?? null,
            'street_name' => $validated['address'] ?? null,
            'city_name' => $validated['city'] ?? null,
            'postal_zone' => $validated['postal_zone'] ?? null,
            'country_code' => $validated['country_code'] ?? 'NG',
        ];

        $customer = $this->customerService->create($mappedData, $request->user()->current_organization_id);

        return response()->json([
            'message' => 'Customer created successfully.',
            'data' => new CustomerResource($customer),
        ], 201);
    }

    public function show(Customer $customer): JsonResponse|CustomerResource
    {
        if ($customer->organization_id !== request()->user()->current_organization_id) {
            return response()->json(['message' => 'Unauthorized access to customer'], 403);
        }
        return new CustomerResource($customer);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        if ($customer->organization_id !== $request->user()->current_organization_id) {
            return response()->json(['message' => 'Unauthorized access to customer'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'tin' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string'],
            'postal_zone' => ['nullable', 'string', 'max:20'],
            'country_code' => ['nullable', 'string', 'size:2'],
        ]);

        $mappedData = [
            'name' => $validated['name'],
            'tin' => $validated['tin'],
            'email' => $validated['email'],
            'telephone' => $validated['phone'] ?? null,
            'street_name' => $validated['address'] ?? null,
            'city_name' => $validated['city'] ?? null,
            'postal_zone' => $validated['postal_zone'] ?? null,
            'country_code' => $validated['country_code'] ?? 'NG',
        ];

        $this->customerService->update($customer, $mappedData);

        return response()->json([
            'message' => 'Customer updated successfully.',
            'data' => new CustomerResource($customer->fresh()),
        ]);
    }

    public function destroy(Request $request, Customer $customer): JsonResponse
    {
        if ($customer->organization_id !== $request->user()->current_organization_id) {
            return response()->json(['message' => 'Unauthorized access to customer'], 403);
        }

        $customer->delete();

        return response()->json([
            'message' => 'Customer deleted successfully.',
        ]);
    }
}
