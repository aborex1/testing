<?php

namespace Botble\Location;

use Botble\Base\Enums\BaseStatusEnum;
use Botble\Base\Models\BaseQueryBuilder;
use Botble\Base\Supports\PclZip as Zip;
use Botble\Location\Events\DownloadedCities;
use Botble\Location\Events\DownloadedCountry;
use Botble\Location\Events\DownloadedStates;
use Botble\Location\Models\City;
use Botble\Location\Models\Country;
use Botble\Location\Models\State;
use Botble\Location\Repositories\Interfaces\CityInterface;
use Botble\Location\Repositories\Interfaces\StateInterface;
use Carbon\Carbon;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Throwable;
use ZipArchive;

class Location
{
    public function __construct(protected StateInterface $stateRepository, protected CityInterface $cityRepository)
    {
    }

    public function getStates(): array
    {
        $states = $this->stateRepository->advancedGet([
            'condition' => [
                'status' => BaseStatusEnum::PUBLISHED,
            ],
            'order_by' => ['order' => 'ASC', 'name' => 'ASC'],
        ]);

        return $states->pluck('name', 'id')->all();
    }

    public function getCitiesByState(int|string|null $stateId): array
    {
        if (! $stateId) {
            return [];
        }

        $cities = $this->cityRepository->advancedGet([
            'condition' => [
                'status' => BaseStatusEnum::PUBLISHED,
                'state_id' => $stateId,
            ],
            'order_by' => ['order' => 'ASC', 'name' => 'ASC'],
        ]);

        return $cities->pluck('name', 'id')->all();
    }

    public function getCityById(int|string|null $cityId): ?City
    {
        if (! $cityId) {
            return null;
        }

        return $this->cityRepository->getFirstBy([
            'id' => $cityId,
            'status' => BaseStatusEnum::PUBLISHED,
        ]);
    }

    public function getCityNameById(int|string|null $cityId): string|null
    {
        if (! $cityId) {
            return null;
        }

        $city = $this->getCityById($cityId);

        return $city?->name;
    }

    public function getStateNameById(int|string|null $stateId): string|null
    {
        if (! $stateId) {
            return null;
        }

        $state = $this->stateRepository->getFirstBy([
            'id' => $stateId,
            'status' => BaseStatusEnum::PUBLISHED,
        ]);

        return $state ? $state->name : null;
    }

    public function isSupported(string|object $model): bool
    {
        if (! $model) {
            return false;
        }

        if (is_object($model)) {
            $model = get_class($model);
        }

        return in_array($model, $this->supportedModels());
    }

    public function supportedModels(): array
    {
        return array_keys($this->getSupported());
    }

    public function getSupported(string|object $model = null): array
    {
        if (! $model) {
            return config('plugins.location.general.supported', []);
        }

        if (is_object($model)) {
            $model = get_class($model);
        }

        return Arr::get(config('plugins.location.general.supported', []), $model, []);
    }

    public function registerModule(string $model, array $keys = []): bool
    {
        $keys = array_filter(
            array_merge([
                'country' => 'country_id',
                'state' => 'state_id',
                'city' => 'city_id',
            ], $keys)
        );

        config([
            'plugins.location.general.supported' => array_merge($this->getSupported(), [$model => $keys]),
        ]);

        return true;
    }

    public function getRemoteAvailableLocations(): array
    {
        try {
            $info = Http::withoutVerifying()
                ->asJson()
                ->acceptJson()
                ->get('https://api.github.com/repos/botble/locations/git/trees/master');

            if (! $info->ok()) {
                return ['us', 'ca', 'vn'];
            }

            $info = $info->json();

            $availableLocations = [];

            foreach ($info['tree'] as $tree) {
                if (in_array($tree['path'], ['.gitignore', 'README.md'])) {
                    continue;
                }

                $availableLocations[] = $tree['path'];
            }
        } catch (Throwable) {
            $availableLocations = ['us', 'ca', 'vn'];
        }

        return $availableLocations;
    }

    public function downloadRemoteLocation(string $countryCode): array
    {
        $repository = 'https://github.com/botble/locations';

        $destination = storage_path('app/location-files.zip');

        $availableLocations = $this->getRemoteAvailableLocations();

        if (! in_array($countryCode, $availableLocations)) {
            return [
                'error' => true,
                'message' => sprintf('This country locations data is not available on %s', $repository),
            ];
        }

        try {
            $response = Http::withoutVerifying()
                ->sink(Utils::tryFopen($destination, 'w'))
                ->get($repository . '/archive/refs/heads/master.zip');

            if (! $response->ok()) {
                return [
                    'error' => true,
                    'message' => $response->reason(),
                ];
            }
        } catch (Throwable $exception) {
            return [
                'error' => true,
                'message' => $exception->getMessage(),
            ];
        }

        if (class_exists('ZipArchive', false)) {
            $zip = new ZipArchive();
            $res = $zip->open($destination);
            if ($res === true) {
                $zip->extractTo(storage_path('app'));
                $zip->close();
            } else {
                return [
                    'error' => true,
                    'message' => 'Extract location files failed!',
                ];
            }
        } else {
            $archive = new Zip($destination);
            $archive->extract(PCLZIP_OPT_PATH, storage_path('app'));
        }

        if (File::exists($destination)) {
            File::delete($destination);
        }

        $dataPath = storage_path('app/locations-master/' . $countryCode);

        if (! File::isDirectory($dataPath)) {
            abort(404);
        }

        $country = file_get_contents($dataPath . '/country.json');
        $country = json_decode($country, true);

        $country = Country::query()->create($country);

        if ($country) {
            event(new DownloadedCountry());
        }

        $states = file_get_contents($dataPath . '/states.json');
        $states = json_decode($states, true);

        $statesForInserting = [];

        foreach ($states as $state) {
            $state['country_id'] = $country->id;

            $statesForInserting[] = array_merge($state, [
                'country_id' => $country->id,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        if (! empty($statesForInserting)) {
            State::query()->insertOrIgnore($statesForInserting);

            event(new DownloadedStates());
        }

        $cities = file_get_contents($dataPath . '/cities.json');
        $cities = json_decode($cities, true);

        $citiesForInserting = [];

        foreach ($cities as $item) {
            $stateId = State::query()->where('name', $item['name'])->value('id');

            if (! $stateId) {
                continue;
            }

            foreach ($item['cities'] as $cityName) {
                $citiesForInserting[] = [
                    'name' => $cityName,
                    'state_id' => $stateId,
                    'country_id' => $country->id,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];
            }
        }

        if (! empty($citiesForInserting)) {
            City::query()->insertOrIgnore($citiesForInserting);

            event(new DownloadedCities());
        }

        File::deleteDirectory(storage_path('app/locations-master'));

        return [
            'error' => false,
            'message' => trans('plugins/location::bulk-import.imported_successfully'),
        ];
    }

    public function filter($model, int|string $cityId = null, string $location = null)
    {
        $className = get_class($model);
        if ($className == BaseQueryBuilder::class) {
            $className = get_class($model->getModel());
        }

        if ($this->isSupported($className)) {
            if ($cityId) {
                $model = $model->where('city_id', $cityId);
            } elseif ($location) {
                $locationData = explode(',', $location);

                if (count($locationData) > 1) {
                    $model = $model
                        ->whereHas('city', function ($query) use ($locationData) {
                            $query->where('name', 'LIKE', '%' . trim($locationData[0]) . '%');
                        })
                        ->whereHas('state', function ($query) use ($locationData) {
                            $query->where('name', 'LIKE', '%' . trim($locationData[1]) . '%');
                        });
                } else {
                    $model = $model
                        ->where(function (Builder $query) use ($location) {
                            $query->whereHas('city', function ($q) use ($location) {
                                $q->where('name', 'LIKE', '%' . $location . '%');
                            })->orWhereHas('state', function ($q) use ($location) {
                                $q->where('name', 'LIKE', '%' . $location . '%');
                            });
                        });
                }
            }
        }

        return $model;
    }
}
