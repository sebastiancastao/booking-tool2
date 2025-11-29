<?php

namespace App\Http\Controllers;

use App\Models\Widget;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WidgetEmbedController extends Controller
{
    public function show(Request $request, string $widgetKey)
    {
        $widget = Widget::where('widget_key', $widgetKey)->firstOrFail();

        if (! $widget->isPublished()) {
            abort(404);
        }

        $config = $widget->getConfigurationArray();

        $response = Inertia::render('widgets/embed', [
            'widget' => [
                'id' => $widget->id,
                'name' => $widget->name,
                'widget_key' => $widget->widget_key,
            ],
            'config' => $config,
        ]);

        return $response->toResponse($request)->withHeaders([
            'X-Frame-Options' => 'ALLOWALL',
            'Content-Security-Policy' => 'frame-ancestors *',
        ]);
    }
}
