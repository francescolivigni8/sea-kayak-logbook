<?php

namespace App\Support;

use App\Models\PaddleSession;

class PlanningForecastService
{
    public function __construct(
        private readonly StormglassWeatherService $stormglass,
        private readonly OpenMeteoMarineWeatherService $openMeteo,
    ) {}

    public function isConfigured(): bool
    {
        return $this->stormglass->isConfigured() || $this->openMeteo->isConfigured();
    }

    public function previewForecastBoard(PaddleSession $session): array
    {
        $stormglassResult = null;

        if ($this->stormglass->isConfigured()) {
            $stormglassResult = $this->withProvider(
                $this->stormglass->previewForecastBoard($session),
                'stormglass',
            );

            if (($stormglassResult['status'] ?? null) === 'filled') {
                return $stormglassResult;
            }
        }

        if ($this->openMeteo->isConfigured()) {
            $openMeteoResult = $this->withProvider(
                $this->openMeteo->previewForecastBoard($session),
                'open_meteo',
            );

            if ($stormglassResult !== null && ($openMeteoResult['status'] ?? null) === 'filled') {
                $openMeteoResult['fallbackFrom'] = [
                    'provider' => 'stormglass',
                    'status' => $stormglassResult['status'] ?? 'unknown',
                    'reason' => $stormglassResult['reason'] ?? null,
                    'httpStatus' => $stormglassResult['httpStatus'] ?? null,
                    'providerMessage' => $stormglassResult['providerMessage'] ?? null,
                ];
            }

            return $openMeteoResult;
        }

        return $stormglassResult ?? [
            'status' => 'skipped',
            'provider' => null,
            'reason' => 'No planning forecast provider is configured yet.',
            'filledFields' => 0,
            'fields' => [],
            'timeline' => [],
        ];
    }

    private function withProvider(array $result, string $provider): array
    {
        return [
            'provider' => $provider,
            ...$result,
        ];
    }
}
