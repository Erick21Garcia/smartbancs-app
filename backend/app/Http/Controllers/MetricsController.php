<?php

namespace App\Http\Controllers;

use App\Services\MetricsService;
use Prometheus\RenderTextFormat;

class MetricsController extends Controller
{
    public function index()
    {
        $renderer = new RenderTextFormat();
        $result = $renderer->render(MetricsService::registry()->getMetricFamilySamples());

        return response($result, 200)
            ->header('Content-Type', RenderTextFormat::MIME_TYPE);
    }
}