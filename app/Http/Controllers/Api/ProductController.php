<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        protected ProductService $productService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $products = Product::where('organization_id', $request->user()->current_organization_id)
            ->latest()
            ->paginate($request->query('per_page', 15));

        return response()->json(ProductResource::collection($products)->response()->getData(true));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sku' => ['nullable', 'string', 'max:50'],
            'price' => ['required', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:50'],
            'hsn_code' => ['required', 'string'],
            'product_category' => ['required', 'string'],
        ]);

        $product = $this->productService->create($validated, $request->user()->current_organization_id);

        return response()->json([
            'message' => 'Product created successfully.',
            'data' => new ProductResource($product),
        ], 201);
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product);
    }
}
