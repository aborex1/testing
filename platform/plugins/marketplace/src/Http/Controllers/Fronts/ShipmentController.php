<?php

namespace Botble\Marketplace\Http\Controllers\Fronts;

use Botble\Base\Facades\Assets;
use Botble\Base\Facades\PageTitle;
use Botble\Base\Http\Controllers\BaseController;
use Botble\Base\Http\Responses\BaseHttpResponse;
use Botble\Ecommerce\Enums\ShippingCodStatusEnum;
use Botble\Ecommerce\Enums\ShippingStatusEnum;
use Botble\Ecommerce\Events\ShippingStatusChanged;
use Botble\Ecommerce\Facades\OrderHelper;
use Botble\Ecommerce\Http\Requests\ShipmentRequest;
use Botble\Ecommerce\Http\Requests\UpdateShipmentCodStatusRequest;
use Botble\Ecommerce\Models\Customer;
use Botble\Ecommerce\Models\Shipment;
use Botble\Ecommerce\Repositories\Interfaces\OrderHistoryInterface;
use Botble\Ecommerce\Repositories\Interfaces\OrderInterface;
use Botble\Ecommerce\Repositories\Interfaces\ShipmentHistoryInterface;
use Botble\Ecommerce\Repositories\Interfaces\ShipmentInterface;
use Botble\Marketplace\Facades\MarketplaceHelper;
use Botble\Marketplace\Http\Requests\UpdateShippingStatusRequest;
use Botble\Marketplace\Tables\ShipmentTable;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;

class ShipmentController extends BaseController
{
    public function __construct(
        protected OrderInterface $orderRepository,
        protected ShipmentInterface $shipmentRepository,
        protected OrderHistoryInterface $orderHistoryRepository,
        protected ShipmentHistoryInterface $shipmentHistoryRepository
    ) {
        $this->middleware(function ($request, $next) {
            if (! MarketplaceHelper::allowVendorManageShipping()) {
                abort(404);
            }

            return $next($request);
        });
    }

    public function index(ShipmentTable $dataTable)
    {
        PageTitle::setTitle(trans('plugins/ecommerce::shipping.shipments'));

        return $dataTable->render(MarketplaceHelper::viewPath('dashboard.table.base'));
    }

    public function edit(int|string $id)
    {
        Assets::addStylesDirectly('vendor/core/plugins/ecommerce/css/ecommerce.css')
            ->addScriptsDirectly('vendor/core/plugins/ecommerce/js/shipment.js');

        $shipment = $this->findOrFail($id);
        $shipment->load([
            'histories' => function ($query) {
                $query->latest();
            },
            'histories.order',
            'histories.user',
        ]);

        PageTitle::setTitle(trans('plugins/ecommerce::shipping.edit_shipping', ['code' => get_shipment_code($id)]));

        return MarketplaceHelper::view('dashboard.shipments.edit', compact('shipment'));
    }

    public function postUpdateStatus(int|string $id, UpdateShippingStatusRequest $request, BaseHttpResponse $response)
    {
        $shipment = $this->findOrFail($id);
        $previousShipment = $shipment->toArray();
        $shipment->status = $request->input('status');
        $shipment->save();

        $this->shipmentHistoryRepository->createOrUpdate([
            'action' => 'update_status',
            'description' => trans('plugins/ecommerce::shipping.changed_shipping_status', [
                'status' => $shipment->status->label(),
            ]),
            'shipment_id' => $id,
            'order_id' => $shipment->order_id,
            'user_id' => auth('customer')->id(),
            'user_type' => Customer::class,
        ]);

        switch ($shipment->status) {
            case ShippingStatusEnum::DELIVERED:
                $shipment->date_shipped = Carbon::now();
                $shipment->save();

                OrderHelper::shippingStatusDelivered($shipment, $request, 0);

                break;

            case ShippingStatusEnum::CANCELED:
                $this->orderHistoryRepository->createOrUpdate([
                    'action' => 'cancel_shipment',
                    'description' => trans('plugins/ecommerce::shipping.shipping_canceled_by'),
                    'order_id' => $shipment->order_id,
                    'user_id' => 0,
                ]);

                break;
        }

        event(new ShippingStatusChanged($shipment, $previousShipment));

        return $response->setMessage(trans('plugins/ecommerce::shipping.update_shipping_status_success'));
    }

    public function postUpdateCodStatus(int|string $id, UpdateShipmentCodStatusRequest $request, BaseHttpResponse $response)
    {
        $shipment = $this->findOrFail($id);
        $shipment->cod_status = $request->input('status');
        $shipment->save();

        if ($shipment->cod_status == ShippingCodStatusEnum::COMPLETED) {
            OrderHelper::confirmPayment($shipment->order);
        }

        $this->shipmentHistoryRepository->createOrUpdate([
            'action' => 'update_cod_status',
            'description' => trans('plugins/ecommerce::shipping.updated_cod_status_by', [
                'status' => $shipment->cod_status->label(),
            ]),
            'shipment_id' => $id,
            'order_id' => $shipment->order_id,
            'user_id' => auth('customer')->id(),
            'user_type' => Customer::class,
        ]);

        return $response->setMessage(trans('plugins/ecommerce::shipping.update_cod_status_success'));
    }

    public function update(int|string $id, ShipmentRequest $request, BaseHttpResponse $response)
    {
        $shipment = $this->findOrFail($id);

        $shipment->fill($request->validated());

        $this->shipmentRepository->createOrUpdate($shipment);

        return $response
            ->setPreviousUrl(route('marketplace.vendor.shipments.index'))
            ->setMessage(trans('core/base::notices.update_success_message'));
    }

    public function destroy(int|string $id, BaseHttpResponse $response)
    {
        try {
            $shipment = $this->findOrFail($id);
            $this->shipmentRepository->delete($shipment);

            return $response->setMessage(trans('core/base::notices.delete_success_message'));
        } catch (Exception $exception) {
            return $response
                ->setError()
                ->setMessage($exception->getMessage());
        }
    }

    public function deletes(Request $request, BaseHttpResponse $response)
    {
        $ids = $request->input('ids');
        if (empty($ids)) {
            return $response
                ->setError()
                ->setMessage(trans('core/base::notices.no_select'));
        }

        foreach ($ids as $id) {
            $shipment = $this->findOrFail($id);
            $this->shipmentRepository->delete($shipment);
        }

        return $response->setMessage(trans('core/base::notices.delete_success_message'));
    }

    protected function findOrFail(int|string $id): Shipment|null
    {
        return $this->shipmentRepository
            ->getModel()
            ->where('id', $id)
            ->whereHas('order', function ($query) {
                $query->where('store_id', auth('customer')->user()->store->id);
            })
            ->firstOrFail();
    }
}
