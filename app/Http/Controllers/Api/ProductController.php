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

    private function mapProductPayload(array $validated): array
    {
        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'unit_price' => $validated['price'],
            'unit_code' => $validated['unit'],
            'hs_code' => $validated['hsn_code'],
            'tax_category' => $validated['product_category'],
            'tax_rate' => $validated['tax_rate'] ?? 7.5,
        ];
    }

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
            'tax_rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        $product = $this->productService->create(
            $this->mapProductPayload($validated),
            $request->user()->current_organization_id
        );

        return response()->json([
            'message' => 'Product created successfully.',
            'data' => new ProductResource($product),
        ], 201);
    }

    public function show(Product $product): ProductResource
    {
        if ($product->organization_id !== request()->user()->current_organization_id) {
            abort(403, 'Unauthorized access to product');
        }

        return new ProductResource($product);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sku' => ['nullable', 'string', 'max:50'],
            'price' => ['required', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:50'],
            'hsn_code' => ['required', 'string'],
            'product_category' => ['required', 'string'],
            'tax_rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->productService->update($product, $this->mapProductPayload($validated));

        return response()->json([
            'message' => 'Product updated successfully.',
            'data' => new ProductResource($product->fresh()),
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully.',
        ]);
    }
}
