<?php

namespace Botble\Marketplace\Listeners;

use Botble\Base\Enums\BaseStatusEnum;
use Botble\Marketplace\Repositories\Interfaces\StoreInterface;
use Botble\Theme\Events\RenderingSiteMapEvent;
use SiteMapManager;

class RenderingSiteMapListener
{
    public function __construct(protected StoreInterface $storeRepository)
    {
    }

    public function handle(RenderingSiteMapEvent $event): void
    {
        if ($key = $event->key) {
            switch ($key) {
                case 'stores':
                    $stores = $this->storeRepository
                        ->getModel()
                        ->with('slugable')
                        ->where('status', BaseStatusEnum::PUBLISHED)
                        ->orderBy('created_at', 'desc')
                        ->select(['id', 'name', 'updated_at'])
                        ->get();

                    foreach ($stores as $store) {
                        if (! $store->slugable) {
                            continue;
                        }

                        SiteMapManager::add($store->url, $store->updated_at, '0.8');
                    }

                    break;
                case 'pages':
                    SiteMapManager::add(route('public.stores'), null, '1', 'monthly');

                    break;
            }
        } else {
            SiteMapManager::addSitemap(SiteMapManager::route('stores'), null);
        }
    }
}
