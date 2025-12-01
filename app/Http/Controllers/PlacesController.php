<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PlacesController extends Controller
{
    public function __construct()
    {
        // Add CORS headers for cross-origin requests from embedded widgets
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Accept, X-Requested-With');
        header('Access-Control-Allow-Credentials: true');
    }

    public function autocomplete(Request $request)
    {
        $request->validate([
            'input' => 'required|string|min:3',
            'sessiontoken' => 'nullable|string',
        ]);

        $apiKey = config('services.google.maps_api_key');

        if (!$apiKey) {
            return response()->json([
                'error' => 'Google Maps API key not configured'
            ], 500);
        }

        try {
            $params = [
                'input' => $request->input,
                'key' => $apiKey,
                'sessiontoken' => $request->sessiontoken ?? '',
                'types' => 'address',
            ];

            // Add country restriction if provided
            if ($request->has('components')) {
                $params['components'] = $request->components;
            }

            // Add location bias if provided (for Georgia bias)
            if ($request->has('location')) {
                $params['location'] = $request->location;
            }
            if ($request->has('radius')) {
                $params['radius'] = $request->radius;
            }

            $response = Http::timeout(10)
                ->withOptions([
                    'verify' => false, // Disable SSL verification for local development
                ])
                ->get('https://maps.googleapis.com/maps/api/place/autocomplete/json', $params);

            if (!$response->successful()) {
                return response()->json([
                    'status' => 'REQUEST_DENIED',
                    'error_message' => 'Failed to fetch predictions'
                ], 500);
            }

            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'REQUEST_DENIED',
                'error_message' => $e->getMessage()
            ], 500);
        }
    }

    public function details(Request $request)
    {
        $request->validate([
            'place_id' => 'required|string',
        ]);

        $apiKey = config('services.google.maps_api_key');

        if (!$apiKey) {
            return response()->json([
                'error' => 'Google Maps API key not configured'
            ], 500);
        }

        try {
            $response = Http::timeout(10)
                ->withOptions([
                    'verify' => false, // Disable SSL verification for local development
                ])
                ->get('https://maps.googleapis.com/maps/api/place/details/json', [
                    'place_id' => $request->place_id,
                    'key' => $apiKey,
                    'fields' => 'formatted_address,address_components',
                ]);

            if (!$response->successful()) {
                return response()->json([
                    'status' => 'REQUEST_DENIED',
                    'error_message' => 'Failed to fetch place details'
                ], 500);
            }

            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'REQUEST_DENIED',
                'error_message' => $e->getMessage()
            ], 500);
        }
    }
}
