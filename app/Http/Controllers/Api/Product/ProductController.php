<?php

namespace App\Http\Controllers\Api\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ActivityLog\ActivityLogService;
use App\Services\Product\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        protected ProductService $productService,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $products = Product::where('organization_id', $request->user()->current_organization_id)
            ->latest()
            ->paginate($request->query('per_page', 15));

        return response()->json(ProductResource::collection($products)->response()->getData(true));
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->productService->create(
            $request->toServiceData(),
            $request->user()->current_organization_id
        );

        $this->activityLog->log(
            $request->user(),
            'product.created',
            $product,
            "Product {$product->name} created.",
            ['product_id' => $product->uuid],
        );

        return response()->json([
            'message' => 'Product created successfully.',
            'data' => new ProductResource($product),
        ], 201);
    }

    public function show(Request $request, Product $product): ProductResource
    {
        $this->authorize('view', $product);

        return new ProductResource($product);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $before = $product->only(array_keys($request->toServiceData()));
        $this->productService->update($product, $request->toServiceData());
        $product = $product->fresh();

        $this->activityLog->log(
            $request->user(),
            'product.updated',
            $product,
            "Product {$product->name} updated.",
            [
                'product_id' => $product->uuid,
                'before' => $before,
                'after' => $product->only(array_keys($request->toServiceData())),
            ],
        );

        return response()->json([
            'message' => 'Product updated successfully.',
            'data' => new ProductResource($product),
        ]);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $this->authorize('delete', $product);
        $productName = $product->name;
        $productUuid = $product->uuid;

        $product->delete();

        $this->activityLog->log(
            $request->user(),
            'product.deleted',
            $product,
            "Product {$productName} deleted.",
            ['product_id' => $productUuid],
        );

        return response()->json([
            'message' => 'Product deleted successfully.',
        ]);
    }
}
