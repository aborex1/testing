<?php

namespace Botble\Ecommerce\Http\Requests;

use Botble\Support\Http\Requests\Request;

class ProductCollectionRequest extends Request
{
    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:220',
            'description' => 'nullable|string|max:400',
        ];

        if ($this->route()->getName() === 'product-collections.create') {
            $rules = array_merge($rules, [
                'slug' => 'required|string|unique:ec_product_collections',
            ]);
        }

        return $rules;
    }
}
