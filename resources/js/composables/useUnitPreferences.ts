import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    currentUnitLabel,
    distanceUnitLabel,
    resolveUnitPreferences,
    speedUnitLabel,
    temperatureUnitLabel,
    windUnitLabel,
    type UnitPreferences,
} from '@/lib/units';

export function useUnitPreferences() {
    const page = usePage();

    const unitPreferences = computed(() =>
        resolveUnitPreferences(
            (page.props.unitPreferences as Partial<UnitPreferences> | undefined) ??
                undefined,
        ),
    );

    return {
        unitPreferences,
        distanceUnitLabel: computed(() =>
            distanceUnitLabel(unitPreferences.value.distance),
        ),
        speedUnitLabel: computed(() =>
            speedUnitLabel(unitPreferences.value.speed),
        ),
        windUnitLabel: computed(() => windUnitLabel(unitPreferences.value.wind)),
        currentUnitLabel: computed(() =>
            currentUnitLabel(unitPreferences.value.current),
        ),
        temperatureUnitLabel: computed(() =>
            temperatureUnitLabel(unitPreferences.value.temperature),
        ),
    };
}
