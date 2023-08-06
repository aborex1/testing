<?php

namespace Botble\Ecommerce\Models;

use Botble\Base\Enums\BaseStatusEnum;
use Botble\Base\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class ProductAttributeSet extends BaseModel
{
    protected $table = 'ec_product_attribute_sets';

    protected $fillable = [
        'title',
        'slug',
        'status',
        'order',
        'display_layout',
        'is_searchable',
        'is_comparable',
        'is_use_in_product_listing',
        'use_image_from_product_variation',
    ];

    protected $casts = [
        'status' => BaseStatusEnum::class,
    ];

    public function attributes(): HasMany
    {
        return $this->hasMany(ProductAttribute::class, 'attribute_set_id')->orderBy('order', 'ASC');
    }

    public function categories(): MorphToMany
    {
        return $this->morphToMany(ProductCategory::class, 'reference', 'ec_product_categorizables', 'reference_id', 'category_id');
    }

    protected static function boot(): void
    {
        parent::boot();

        self::deleting(function (ProductAttributeSet $productAttributeSet) {
            $attributes = ProductAttribute::where('attribute_set_id', $productAttributeSet->id)->get();

            foreach ($attributes as $attribute) {
                $attribute->delete();
            }

            $productAttributeSet->categories()->detach();
        });
    }
}
