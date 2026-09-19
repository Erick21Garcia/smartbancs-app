<?php

namespace App\Services;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\Redis;

class MetricsService
{
    private static ?CollectorRegistry $registry = null;

    public static function registry(): CollectorRegistry
    {
        if (self::$registry === null) {
            Redis::setDefaultOptions([
                'host' => env('REDIS_HOST', 'redis'),
                'port' => (int) env('REDIS_PORT', 6379),
            ]);
            self::$registry = new CollectorRegistry(new Redis());
        }

        return self::$registry;
    }

    public static function incrementCounter(string $name, string $help, array $labels = [], array $labelValues = []): void
    {
        self::registry()
            ->getOrRegisterCounter('smartbancs', $name, $help, $labels)
            ->inc($labelValues);
    }

    public static function observeHistogram(string $name, string $help, float $value, array $labels = [], array $labelValues = []): void
    {
        self::registry()
            ->getOrRegisterHistogram('smartbancs', $name, $help, $labels)
            ->observe($value, $labelValues);
    }
}