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
            'country_code' => ['nullable', 'string', 'size:2'],
        ]);

        $customer = $this->customerService->create($validated, $request->user()->current_organization_id);

        return response()->json([
            'message' => 'Customer created successfully.',
            'data' => new CustomerResource($customer),
        ], 201);
    }

    public function show(Customer $customer): CustomerResource
    {
        return new CustomerResource($customer);
    }
}
