<?php

namespace Database\Seeders;

use Botble\Base\Facades\MetaBox;
use Botble\Base\Supports\BaseSeeder;
use Botble\Ecommerce\Models\ProductCategory;
use Botble\Slug\Facades\SlugHelper;
use Botble\Slug\Models\Slug;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ProductCategorySeeder extends BaseSeeder
{
    public function run(): void
    {
        $this->uploadFiles('product-categories');

        $categories = [
            [
                'name' => 'Milks and Dairies',
            ],
            [
                'name' => 'Clothing & beauty',
            ],
            [
                'name' => 'Pet Toy',
            ],
            [
                'name' => 'Baking material',
            ],
            [
                'name' => 'Fresh Fruit',
            ],
            [
                'name' => 'Wines & Drinks',
            ],
            [
                'name' => 'Fresh Seafood',
            ],
            [
                'name' => 'Fast food',
            ],
            [
                'name' => 'Vegetables',
            ],
            [
                'name' => 'Bread and Juice',
            ],
            [
                'name' => 'Cake & Milk',
            ],
            [
                'name' => 'Coffee & Teas',
            ],
            [
                'name' => 'Pet Foods',
            ],
            [
                'name' => 'Diet Foods',
            ],
        ];

        ProductCategory::query()->truncate();

        foreach ($categories as $index => $item) {
            $this->createCategoryItem($index, $item);
        }
    }

    protected function colors(): array
    {
        return [
            '#F2FCE4',
            '#FFFCEB',
            '#ECFFEC',
            '#FEEFEA',
            '#FFF3EB',
            '#FFF3FF',
        ];
    }

    protected function createCategoryItem(int $index, array $category, int $parentId = 0): void
    {
        $category['parent_id'] = $parentId;
        $category['order'] = $index;
        $category['is_featured'] = $index < 12;
        $category['image'] = 'product-categories/image-' . ($index + 1) . '.png';

        if (Arr::has($category, 'children')) {
            $children = $category['children'];
            unset($category['children']);
        } else {
            $children = [];
        }

        $createdCategory = ProductCategory::query()->create($category);

        Slug::query()->create([
            'reference_type' => ProductCategory::class,
            'reference_id' => $createdCategory->id,
            'key' => Str::slug($createdCategory->name),
            'prefix' => SlugHelper::getPrefix(ProductCategory::class),
        ]);

        MetaBox::saveMetaBoxData($createdCategory, 'icon_image', 'product-categories/icon-' . ($index + 1) . '.png');
        MetaBox::saveMetaBoxData(
            $createdCategory,
            'background_color',
            Arr::get($this->colors(), $index % count($this->colors()))
        );

        if ($children) {
            foreach ($children as $childIndex => $child) {
                $this->createCategoryItem($childIndex, $child, $createdCategory->id);
            }
        }
    }
}
