@extends(EcommerceHelper::viewPath('customers.master'))
@section('content')
    <div class="section-header">
        <h3>{{ SeoHelper::getTitle() }}</h3>
    </div>
    <div class="section-content">
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>{{ __('Image') }}</th>
                        <th>{{ __('Product Name') }}</th>
                        <th class="text-center">{{ __('Times downloaded') }}</th>
                        <th>{{ __('Ordered at') }}</th>
                        <th>{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @if (count($orderProducts) > 0)
                        @foreach ($orderProducts as $orderProduct)
                            <tr>
                                <td>
                                    <img src="{{ RvMedia::getImageUrl($orderProduct->product_image, 'thumb', false, RvMedia::getDefaultImage()) }}" width="50" alt="{{ $orderProduct->product_name }}">
                                </td>
                                <td>
                                    {{ $orderProduct->product_name }}
                                    @if ($sku = Arr::get($orderProduct->options, 'sku')) ({{ $sku }}) @endif
                                    @if ($attributes = Arr::get($orderProduct->options, 'attributes'))
                                        <p class="mb-0">
                                            <small>{{ $attributes }}</small>
                                        </p>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span>{{ $orderProduct->times_downloaded }}</span>
                                </td>
                                <td>{{ $orderProduct->created_at->translatedFormat('M d, Y h:m') }}</td>
                                <td>
                                    <div class="dropdown position-static">
                                        <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown"
                                            aria-haspopup="true" aria-expanded="false">
                                            <span>{{ __('Download') }}</span>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            @if ($orderProduct->product_file_internal_count)
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('customer.downloads.product', $orderProduct->id) }}">
                                                        <i class="icon icon-download"></i>
                                                        <span>{{ __('All files') }}</span>
                                                    </a>
                                                </li>
                                            @endif
                                            @if ($orderProduct->product_file_external_count)
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('customer.downloads.product', [$orderProduct->id, 'external' => true]) }}">
                                                        <i class="icon icon-link2"></i>
                                                        <span>{{ __('External link downloads') }}</span>
                                                    </a>
                                                </li>
                                            @endif
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="5" class="text-center">{{ __('No digital products!') }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>

        <div class="pagination">
            {!! $orderProducts->links() !!}
        </div>
    </div>
@endsection
