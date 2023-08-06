<section class="product-tabs section-padding position-relative">
    <div class="container">
        <div class="section-title style-2 wow animate__animated animate__fadeIn">
            <div class="title">
                <h3>{!! BaseHelper::clean($shortcode->title) !!}</h3>
            </div>
        </div>
        <div class="row product-grid-{{ (int)$shortcode->per_row > 0 ? (int)$shortcode->per_row : 4 }}">
            @foreach($products as $product)
                <div class="col-xxl-3 col-xl-3 col-lg-6 col-md-6 mb-lg-0 mb-md-5 mb-sm-5">
                    <div class="product-cart-wrap mb-30 wow animate__animated animate__fadeIn" data-wow-delay="{{ ($loop->index + 1) / 10 }}s">
                        <div class="product-img-action-wrap">
                            <div class="product-img product-img-zoom">
                                <a href="{{ $product->url }}">
                                    <img class="default-img" src="{{ RvMedia::getImageUrl($product->image, 'product-thumb', false, RvMedia::getDefaultImage()) }}" alt="{{ $product->name }}" />
                                    <img class="hover-img" src="{{ RvMedia::getImageUrl($product->image, 'product-thumb', false, RvMedia::getDefaultImage()) }}" alt="{{ $product->name }}" />
                                </a>
                            </div>
                            <div class="product-action-1">
                                <a aria-label="{{ __('Quick View') }}" href="#" class="action-btn hover-up js-quick-view-button" data-url="{{ route('public.ajax.quick-view', $product->id) }}">
                                    <i class="fi-rs-eye"></i>
                                </a>
                                @if (EcommerceHelper::isWishlistEnabled())
                                    <a aria-label="{{ __('Add To Wishlist') }}" href="#" class="action-btn hover-up js-add-to-wishlist-button" data-url="{{ route('public.wishlist.add', $product->id) }}">
                                        <i class="fi-rs-heart"></i>
                                    </a>
                                @endif
                                @if (EcommerceHelper::isCompareEnabled())
                                    <a aria-label="{{ __('Add To Compare') }}" href="#" class="action-btn hover-up js-add-to-compare-button" data-url="{{ route('public.compare.add', $product->id) }}">
                                        <i class="fi-rs-shuffle"></i>
                                    </a>
                                @endif
                            </div>
                            <div class="product-badges product-badges-position product-badges-mrg">
                                @if ($product->isOutOfStock())
                                    <span style="background-color: #000; font-size: 11px;">{{ __('Out Of Stock') }}</span>
                                @else
                                    @if ($product->productLabels->count())
                                        @foreach ($product->productLabels as $label)
                                            <span @if ($label->color) style="background-color: {{ $label->color }}" @endif>{{ $label->name }}</span>
                                        @endforeach
                                    @else
                                        @if ($product->front_sale_price !== $product->price)
                                            <span class="hot">{{ get_sale_percentage($product->price, $product->front_sale_price) }}</span>
                                        @endif
                                    @endif
                                @endif
                            </div>
                        </div>
                        <div class="product-content-wrap">
                            @php $category = $product->categories->sortByDesc('id')->first(); @endphp
                            @if ($category)
                                <div class="product-category">
                                    <a href="{{ $category->url }}">{!! BaseHelper::clean($category->name) !!}</a>
                                </div>
                            @endif
                            <h2><a href="{{ $product->url }}">{!! BaseHelper::clean($product->name) !!}</a></h2>
                            @if (EcommerceHelper::isReviewEnabled() && $product->reviews_count)
                                <div class="product-rate-cover">
                                    <div class="product-rate d-inline-block">
                                        <div class="product-rating" style="width: {{ $product->reviews_avg * 20 }}%"></div>
                                    </div>
                                    <span class="font-small ml-5 text-muted"> ({{ $product->reviews_count }})</span>
                                </div>
                            @endif
                            @if (is_plugin_active('marketplace') && $product->store->id)
                                <div>
                                    <span class="font-small text-muted">{{ __('Sold By') }} <a href="{{ $product->store->url }}">{{ $product->store->name }}</a></span>
                                </div>
                            @endif
                            <div class="product-card-bottom">
                                <div class="product-price">
                                    <span>{{ format_price($product->front_sale_price_with_taxes) }}</span>
                                    @if ($product->front_sale_price !== $product->price)
                                        <span class="old-price">{{ format_price($product->price_with_taxes) }}</span>
                                    @endif
                                </div>
                                @if (EcommerceHelper::isCartEnabled())
                                    <div class="add-cart">
                                        <a aria-label="{{ __('Add To Cart') }}"
                                           class="action-btn add-to-cart-button add"
                                           data-id="{{ $product->id }}"
                                           data-url="{{ route('public.cart.add-to-cart') }}"
                                           href="#">
                                            <i class="fi-rs-shopping-cart mr-5"></i> <span class="d-inline-block">{{ __('Add') }}</span>
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
