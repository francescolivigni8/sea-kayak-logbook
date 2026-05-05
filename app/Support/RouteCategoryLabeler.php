<?php

namespace App\Support;

use Illuminate\Support\Str;

class RouteCategoryLabeler
{
    public function standard(?string $category): string
    {
        return match ($category) {
            'benchmark' => 'Benchmark',
            'training' => 'Training',
            'journey' => 'Journey',
            'navigation' => 'Navigation',
            'rescue-practice' => 'Rescue practice',
            'expedition' => 'Expedition',
            default => ucfirst(str_replace('-', ' ', (string) $category)),
        };
    }

    public function notes(?string $category): string
    {
        return match ($category) {
            'training' => 'Training',
            'benchmark' => 'Benchmark',
            'navigation' => 'Navigation',
            'rescue-practice' => 'Rescue',
            'expedition' => 'Expedition',
            default => 'Journey',
        };
    }

    public function sessionForm(?string $category): string
    {
        return match ($category) {
            'benchmark' => 'Benchmark',
            'training' => 'Training',
            'journey' => 'Journey',
            'navigation' => 'Navigation',
            'rescue-practice' => 'Rescue practice',
            'expedition' => 'Expedition',
            default => Str::headline((string) $category ?: 'Journey'),
        };
    }
}
