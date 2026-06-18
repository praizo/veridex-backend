<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\Organization;
use App\Services\ActivityLog\ActivityLogService;
use App\Services\Customer\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    public function __construct(
        protected CustomerService $customerService,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Customer::class);

        $customers = Customer::where('organization_id', $request->user()->current_organization_id)
            ->latest()
            ->paginate($request->query('per_page', 15));

        return response()->json(CustomerResource::collection($customers)->response()->getData(true));
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $this->authorize('export', Customer::class);

        $orgId = $request->user()->current_organization_id;

        $response = new StreamedResponse(function () use ($orgId) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'ID',
                'First Name',
                'Last Name',
                'Name',
                'Type',
                'TIN',
                'Email',
                'Phone',
                'Address',
                'City',
                'Postal Zone',
                'Country Code',
                'Created At',
            ]);

            Customer::where('organization_id', $orgId)
                ->latest()
                ->chunk(100, function ($customers) use ($handle) {
                    foreach ($customers as $customer) {
                        fputcsv($handle, [
                            $customer->uuid,
                            $customer->first_name ? ucwords($customer->first_name) : '',
                            $customer->last_name ? ucwords($customer->last_name) : '',
                            $customer->name ? ucwords($customer->name) : '',
                            $customer->type ? ucfirst($customer->type) : '',
                            $customer->tin,
                            $customer->email,
                            $customer->telephone,
                            $customer->street_name,
                            $customer->city_name,
                            $customer->postal_zone,
                            $customer->country_code,
                            $customer->created_at ? $customer->created_at->format('Y-m-d H:i:s') : '',
                        ]);
                    }
                });

            fclose($handle);
        });

        $date = now()->format('Y_m_d_His');
        $organization = Organization::findOrFail($orgId);

        $this->activityLog->log(
            $request->user(),
            'customer.exported',
            $organization,
            "Customer CSV exported for {$organization->name}.",
        );

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="customers_export_'.$date.'.csv"');

        return $response;
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = $this->customerService->create(
            $request->toServiceData(),
            $request->user()->current_organization_id
        );

        $this->activityLog->log(
            $request->user(),
            'customer.created',
            $customer,
            "Customer {$customer->name} created.",
            ['customer_id' => $customer->uuid],
        );

        return response()->json([
            'message' => 'Customer created successfully.',
            'data' => new CustomerResource($customer),
        ], 201);
    }

    public function show(Request $request, Customer $customer): CustomerResource
    {
        $this->authorize('view', $customer);

        return new CustomerResource($customer);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $before = $customer->only(array_keys($request->toServiceData()));
        $this->customerService->update($customer, $request->toServiceData());
        $customer = $customer->fresh();

        $this->activityLog->log(
            $request->user(),
            'customer.updated',
            $customer,
            "Customer {$customer->name} updated.",
            [
                'customer_id' => $customer->uuid,
                'before' => $before,
                'after' => $customer->only(array_keys($request->toServiceData())),
            ],
        );

        return response()->json([
            'message' => 'Customer updated successfully.',
            'data' => new CustomerResource($customer),
        ]);
    }

    public function destroy(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('delete', $customer);
        $customerName = $customer->name;
        $customerUuid = $customer->uuid;

        $customer->delete();

        $this->activityLog->log(
            $request->user(),
            'customer.deleted',
            $customer,
            "Customer {$customerName} deleted.",
            ['customer_id' => $customerUuid],
        );

        return response()->json([
            'message' => 'Customer deleted successfully.',
        ]);
    }
}
