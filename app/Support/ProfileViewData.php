<?php

namespace App\Support;

use App\Models\Profile;

class ProfileViewData
{
    public function base(Profile $profile): array
    {
        return [
            'name' => $profile->name,
            'slug' => $profile->slug,
            'homeWater' => $profile->home_water,
            'timezone' => $profile->timezone,
        ];
    }

    public function planning(Profile $profile): array
    {
        $settings = $profile->settings ?? [];

        return [
            ...$this->base($profile),
            'planningUnitSystem' => in_array(data_get($settings, 'planning_unit_system'), ['metric', 'marine'], true)
                ? data_get($settings, 'planning_unit_system')
                : 'metric',
            'defaultMapView' => $this->defaultMapView($profile),
            'hasCustomDefaultMapView' => is_array(data_get($settings, 'default_map_view')),
        ];
    }

    public function defaultMapView(Profile $profile): array
    {
        return [
            'lat' => (float) data_get($profile->settings, 'default_map_view.lat', 64.1670),
            'lng' => (float) data_get($profile->settings, 'default_map_view.lng', -21.8210),
            'zoom' => (int) data_get($profile->settings, 'default_map_view.zoom', 10),
        ];
    }
}
