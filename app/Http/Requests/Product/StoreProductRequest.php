<?php

namespace App\Http\Requests\Product;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        if ($product instanceof Product) {
            return Gate::allows('update', $product);
        }

        return Gate::allows('create', Product::class);
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('item_type')) {
            $this->merge(['item_type' => 'goods']);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'item_type' => ['nullable', Rule::in(['goods', 'service'])],
            'description' => ['nullable', 'string', 'max:1000'],
            'sku' => ['nullable', 'string', 'max:50'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'hsn_code' => ['nullable', 'required_if:item_type,goods', 'string'],
            'item_category' => ['nullable', 'required_if:item_type,goods', 'string', 'max:1000'],
            'isic_code' => ['nullable', 'required_if:item_type,service', 'string'],
            'service_category' => ['nullable', 'required_if:item_type,service', 'string', 'max:1000'],
            'product_category' => ['required', 'string'],
            'tax_rate' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Map frontend field names to canonical DB column names.
     */
    public function toServiceData(): array
    {
        $v = $this->validated();
        $itemType = $v['item_type'] ?? 'goods';

        return [
            'item_type' => $itemType,
            'name' => $v['name'],
            'description' => $v['description'] ?? null,
            'quantity' => $v['quantity'] ?? 1,
            'unit_price' => $v['price'],
            'unit_code' => $v['unit'] ?? 'EA',
            'hs_code' => $itemType === 'goods' ? ($v['hsn_code'] ?? null) : null,
            'item_category' => $itemType === 'goods' ? ($v['item_category'] ?? null) : null,
            'isic_code' => $itemType === 'service' ? ($v['isic_code'] ?? null) : null,
            'service_category' => $itemType === 'service' ? ($v['service_category'] ?? null) : null,
            'tax_category' => $v['product_category'],
            'tax_rate' => $v['tax_rate'] ?? 7.5,
        ];
    }
}
