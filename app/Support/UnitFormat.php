<?php

namespace App\Support;

class UnitFormat
{
    public const KM_PER_NAUTICAL_MILE = 1.852;

    public const KNOTS_PER_METERS_PER_SECOND = 1.943844;

    public const KMH_PER_METERS_PER_SECOND = 3.6;

    public const METERS_PER_SECOND_PER_KNOT = 0.514444;

    /**
     * @param  array{distance:string,speed:string,wind:string,current:string,temperature:string}  $preferences
     */
    public function __construct(
        private readonly array $preferences,
    ) {}

    /**
     * @param  array<string, mixed>|null  $settings
     */
    public static function fromSettings(?array $settings): self
    {
        return new self(UnitPreferences::fromSettings($settings));
    }

    /**
     * @param  array{distance:string,speed:string,wind:string,current:string,temperature:string}  $preferences
     */
    public static function fromPreferences(array $preferences): self
    {
        return new self(UnitPreferences::sanitize($preferences));
    }

    /**
     * @return array{distance:string,speed:string,wind:string,current:string,temperature:string}
     */
    public function preferences(): array
    {
        return $this->preferences;
    }

    public function distanceLabel(): string
    {
        return $this->preferences['distance'] === 'nm' ? 'nm' : 'km';
    }

    public function speedLabel(): string
    {
        return $this->preferences['speed'] === 'kt' ? 'kt' : 'km/h';
    }

    public function windLabel(): string
    {
        return match ($this->preferences['wind']) {
            'ms' => 'm/s',
            'kt' => 'kt',
            default => 'km/h',
        };
    }

    public function currentLabel(): string
    {
        return match ($this->preferences['current']) {
            'ms' => 'm/s',
            'kt' => 'kt',
            default => 'km/h',
        };
    }

    public function temperatureLabel(): string
    {
        return $this->preferences['temperature'] === 'f' ? 'F' : 'C';
    }

    public function formatDistanceKm(?float $valueKm, int $digits = 1, string $empty = '-'): string
    {
        if ($valueKm === null) {
            return $empty;
        }

        return number_format($this->convertDistanceKm($valueKm), $digits).' '.$this->distanceLabel();
    }

    public function formatSpeedKnots(?float $valueKnots, int $digits = 1, string $empty = '-'): string
    {
        if ($valueKnots === null) {
            return $empty;
        }

        return number_format($this->convertSpeedKnots($valueKnots), $digits).' '.$this->speedLabel();
    }

    public function formatSpeedKmh(?float $valueKmh, int $digits = 1, string $empty = '-'): string
    {
        if ($valueKmh === null) {
            return $empty;
        }

        return number_format($this->convertSpeedKmh($valueKmh), $digits).' '.$this->speedLabel();
    }

    public function formatWindMs(?float $valueMs, ?int $digits = null, string $empty = '-'): string
    {
        if ($valueMs === null) {
            return $empty;
        }

        return number_format($this->convertWindMs($valueMs), $digits ?? $this->windDigits()).' '.$this->windLabel();
    }

    public function formatCurrentKnots(?float $valueKnots, ?int $digits = null, string $empty = '-'): string
    {
        if ($valueKnots === null) {
            return $empty;
        }

        return number_format($this->convertCurrentKnots($valueKnots), $digits ?? $this->currentDigits()).' '.$this->currentLabel();
    }

    public function formatTemperatureC(?float $valueC, int $digits = 1, string $empty = '-'): string
    {
        if ($valueC === null) {
            return $empty;
        }

        return number_format($this->convertTemperatureC($valueC), $digits).' '.$this->temperatureLabel();
    }

    public function convertDistanceKm(float $valueKm): float
    {
        return $this->preferences['distance'] === 'nm'
            ? $valueKm / self::KM_PER_NAUTICAL_MILE
            : $valueKm;
    }

    public function convertSpeedKnots(float $valueKnots): float
    {
        return $this->preferences['speed'] === 'kt'
            ? $valueKnots
            : $valueKnots * self::KM_PER_NAUTICAL_MILE;
    }

    public function convertSpeedKmh(float $valueKmh): float
    {
        return $this->preferences['speed'] === 'kt'
            ? $valueKmh / self::KM_PER_NAUTICAL_MILE
            : $valueKmh;
    }

    public function convertWindMs(float $valueMs): float
    {
        return match ($this->preferences['wind']) {
            'ms' => $valueMs,
            'kt' => $valueMs * self::KNOTS_PER_METERS_PER_SECOND,
            default => $valueMs * self::KMH_PER_METERS_PER_SECOND,
        };
    }

    public function convertCurrentKnots(float $valueKnots): float
    {
        return match ($this->preferences['current']) {
            'kt' => $valueKnots,
            'ms' => $valueKnots * self::METERS_PER_SECOND_PER_KNOT,
            default => $valueKnots * self::KM_PER_NAUTICAL_MILE,
        };
    }

    public function convertTemperatureC(float $valueC): float
    {
        return $this->preferences['temperature'] === 'f'
            ? $valueC * (9 / 5) + 32
            : $valueC;
    }

    private function windDigits(): int
    {
        return $this->preferences['wind'] === 'kmh' ? 0 : 1;
    }

    private function currentDigits(): int
    {
        return 1;
    }
}
