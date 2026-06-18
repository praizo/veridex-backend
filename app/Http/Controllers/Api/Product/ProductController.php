<?php

namespace App\Http\Controllers\Api\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\Product\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        protected ProductService $productService
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
        $this->productService->update($product, $request->toServiceData());

        return response()->json([
            'message' => 'Product updated successfully.',
            'data' => new ProductResource($product->fresh()),
        ]);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $this->authorize('delete', $product);

        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully.',
        ]);
    }
}
