<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sku' => ['nullable', 'string', 'max:50'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'hsn_code' => ['required', 'string'],
            'item_category' => ['nullable', 'string', 'max:1000'],
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

        return [
            'name' => $v['name'],
            'description' => $v['description'] ?? null,
            'quantity' => $v['quantity'] ?? 1,
            'unit_price' => $v['price'],
            'unit_code' => $v['unit'] ?? 'EA',
            'hs_code' => $v['hsn_code'],
            'item_category' => $v['item_category'] ?? null,
            'tax_category' => $v['product_category'],
            'tax_rate' => $v['tax_rate'] ?? 7.5,
        ];
    }
}
