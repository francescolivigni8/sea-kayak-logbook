export type DistanceUnit = 'km' | 'nm';
export type SpeedUnit = 'kmh' | 'kt';
export type WindUnit = 'ms' | 'kmh' | 'kt';
export type CurrentUnit = 'kt' | 'kmh' | 'ms';
export type TemperatureUnit = 'c' | 'f';

export interface UnitPreferences {
    distance: DistanceUnit;
    speed: SpeedUnit;
    wind: WindUnit;
    current: CurrentUnit;
    temperature: TemperatureUnit;
}

export const defaultUnitPreferences: UnitPreferences = {
    distance: 'km',
    speed: 'kmh',
    wind: 'kmh',
    current: 'kmh',
    temperature: 'c',
};

export const KM_PER_NAUTICAL_MILE = 1.852;
export const KNOTS_PER_METERS_PER_SECOND = 1.943844;
export const KMH_PER_METERS_PER_SECOND = 3.6;
export const METERS_PER_SECOND_PER_KNOT = 0.514444;

export function resolveUnitPreferences(
    value?: Partial<UnitPreferences> | null,
): UnitPreferences {
    return {
        distance: isDistanceUnit(value?.distance)
            ? value.distance
            : defaultUnitPreferences.distance,
        speed: isSpeedUnit(value?.speed)
            ? value.speed
            : defaultUnitPreferences.speed,
        wind: isWindUnit(value?.wind)
            ? value.wind
            : defaultUnitPreferences.wind,
        current: isCurrentUnit(value?.current)
            ? value.current
            : defaultUnitPreferences.current,
        temperature: isTemperatureUnit(value?.temperature)
            ? value.temperature
            : defaultUnitPreferences.temperature,
    };
}

export function distanceUnitLabel(unit: DistanceUnit): string {
    return unit === 'nm' ? 'nm' : 'km';
}

export function speedUnitLabel(unit: SpeedUnit): string {
    return unit === 'kt' ? 'kt' : 'km/h';
}

export function windUnitLabel(unit: WindUnit): string {
    return unit === 'ms' ? 'm/s' : unit === 'kt' ? 'kt' : 'km/h';
}

export function currentUnitLabel(unit: CurrentUnit): string {
    return unit === 'ms' ? 'm/s' : unit === 'kt' ? 'kt' : 'km/h';
}

export function temperatureUnitLabel(unit: TemperatureUnit): string {
    return unit === 'f' ? 'F' : 'C';
}

export function convertDistanceKm(
    valueKm: number,
    preferences: UnitPreferences,
): number {
    return preferences.distance === 'nm'
        ? valueKm / KM_PER_NAUTICAL_MILE
        : valueKm;
}

export function formatDistanceKm(
    valueKm: number | null | undefined,
    preferences: UnitPreferences,
    digits = 1,
): string {
    if (valueKm === null || valueKm === undefined) {
        return '—';
    }

    return `${convertDistanceKm(valueKm, preferences).toFixed(digits)} ${distanceUnitLabel(preferences.distance)}`;
}

export function convertDistanceToKm(
    value: number,
    preferences: UnitPreferences,
): number {
    return preferences.distance === 'nm'
        ? value * KM_PER_NAUTICAL_MILE
        : value;
}

export function convertSpeedKnots(
    valueKnots: number,
    preferences: UnitPreferences,
): number {
    return preferences.speed === 'kt'
        ? valueKnots
        : valueKnots * KM_PER_NAUTICAL_MILE;
}

export function convertSpeedKmh(
    valueKmh: number,
    preferences: UnitPreferences,
): number {
    return preferences.speed === 'kt'
        ? valueKmh / KM_PER_NAUTICAL_MILE
        : valueKmh;
}

export function formatSpeedKnots(
    valueKnots: number | null | undefined,
    preferences: UnitPreferences,
    digits = preferences.speed === 'kt' ? 1 : 1,
): string {
    if (valueKnots === null || valueKnots === undefined) {
        return '—';
    }

    return `${convertSpeedKnots(valueKnots, preferences).toFixed(digits)} ${speedUnitLabel(preferences.speed)}`;
}

export function convertSpeedToKnots(
    value: number,
    preferences: UnitPreferences,
): number {
    return preferences.speed === 'kt' ? value : value / KM_PER_NAUTICAL_MILE;
}

export function formatSpeedKmh(
    valueKmh: number | null | undefined,
    preferences: UnitPreferences,
    digits = 1,
): string {
    if (valueKmh === null || valueKmh === undefined) {
        return '—';
    }

    return `${convertSpeedKmh(valueKmh, preferences).toFixed(digits)} ${speedUnitLabel(preferences.speed)}`;
}

export function convertWindMs(
    valueMs: number,
    preferences: UnitPreferences,
): number {
    if (preferences.wind === 'ms') {
        return valueMs;
    }

    return preferences.wind === 'kt'
        ? valueMs * KNOTS_PER_METERS_PER_SECOND
        : valueMs * KMH_PER_METERS_PER_SECOND;
}

export function formatWindMs(
    valueMs: number | null | undefined,
    preferences: UnitPreferences,
    digits = preferences.wind === 'kmh' ? 0 : 1,
): string {
    if (valueMs === null || valueMs === undefined) {
        return '—';
    }

    return `${convertWindMs(valueMs, preferences).toFixed(digits)} ${windUnitLabel(preferences.wind)}`;
}

export function convertWindToMs(
    value: number,
    preferences: UnitPreferences,
): number {
    if (preferences.wind === 'ms') {
        return value;
    }

    return preferences.wind === 'kt'
        ? value / KNOTS_PER_METERS_PER_SECOND
        : value / KMH_PER_METERS_PER_SECOND;
}

export function formatWindValue(
    valueMs: number | null | undefined,
    preferences: UnitPreferences,
    digits = preferences.wind === 'kmh' ? 0 : 1,
): string {
    if (valueMs === null || valueMs === undefined) {
        return '—';
    }

    return convertWindMs(valueMs, preferences).toFixed(digits);
}

export function convertCurrentKnots(
    valueKnots: number,
    preferences: UnitPreferences,
): number {
    if (preferences.current === 'kt') {
        return valueKnots;
    }

    return preferences.current === 'ms'
        ? valueKnots * METERS_PER_SECOND_PER_KNOT
        : valueKnots * KM_PER_NAUTICAL_MILE;
}

export function formatCurrentKnots(
    valueKnots: number | null | undefined,
    preferences: UnitPreferences,
    digits = preferences.current === 'ms' ? 1 : 1,
): string {
    if (valueKnots === null || valueKnots === undefined) {
        return '—';
    }

    return `${convertCurrentKnots(valueKnots, preferences).toFixed(digits)} ${currentUnitLabel(preferences.current)}`;
}

export function convertCurrentToKnots(
    value: number,
    preferences: UnitPreferences,
): number {
    if (preferences.current === 'kt') {
        return value;
    }

    return preferences.current === 'ms'
        ? value / METERS_PER_SECOND_PER_KNOT
        : value / KM_PER_NAUTICAL_MILE;
}

export function formatCurrentValue(
    valueKnots: number | null | undefined,
    preferences: UnitPreferences,
    digits = preferences.current === 'ms' ? 1 : 1,
): string {
    if (valueKnots === null || valueKnots === undefined) {
        return '—';
    }

    return convertCurrentKnots(valueKnots, preferences).toFixed(digits);
}

export function convertTemperatureC(
    valueC: number,
    preferences: UnitPreferences,
): number {
    return preferences.temperature === 'f'
        ? valueC * (9 / 5) + 32
        : valueC;
}

export function formatTemperatureC(
    valueC: number | null | undefined,
    preferences: UnitPreferences,
    digits = 1,
): string {
    if (valueC === null || valueC === undefined) {
        return '—';
    }

    return `${convertTemperatureC(valueC, preferences).toFixed(digits)} ${temperatureUnitLabel(preferences.temperature)}`;
}

export function convertTemperatureToC(
    value: number,
    preferences: UnitPreferences,
): number {
    return preferences.temperature === 'f'
        ? (value - 32) * (5 / 9)
        : value;
}

export function formatTemperatureValue(
    valueC: number | null | undefined,
    preferences: UnitPreferences,
    digits = 1,
): string {
    if (valueC === null || valueC === undefined) {
        return '—';
    }

    return convertTemperatureC(valueC, preferences).toFixed(digits);
}

function isDistanceUnit(value: unknown): value is DistanceUnit {
    return value === 'km' || value === 'nm';
}

function isSpeedUnit(value: unknown): value is SpeedUnit {
    return value === 'kmh' || value === 'kt';
}

function isWindUnit(value: unknown): value is WindUnit {
    return value === 'ms' || value === 'kmh' || value === 'kt';
}

function isCurrentUnit(value: unknown): value is CurrentUnit {
    return value === 'kt' || value === 'kmh' || value === 'ms';
}

function isTemperatureUnit(value: unknown): value is TemperatureUnit {
    return value === 'c' || value === 'f';
}
