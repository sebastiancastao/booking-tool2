<?php

namespace App\Http\Controllers;

use App\Services\GravityFormsService;
use App\Services\SmartMovingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class QuoteController extends Controller
{
    public function send(Request $request)
    {
        $payload = $request->validate([
            'widget_key' => 'nullable|string',
            'data' => 'required|array',
            'summary' => 'nullable|array',
            'summary.items' => 'nullable|array',
            'summary.total' => 'nullable|numeric',
            'summary.subtotal' => 'nullable|numeric',
            'summary.minimumJobPrice' => 'nullable|numeric',
            'summary.appliedMinimum' => 'nullable|boolean',
            'source_host' => 'nullable|string',
            'referrer' => 'nullable|string',
            'smart_moving_note' => 'nullable|string',
        ]);

        $resendKey = config('services.resend.key');
        if (!$resendKey) {
            return response()->json(['message' => 'Resend key not configured'], 500);
        }

        $fromAddress = config('mail.from.address') ?? 'no-reply@chalkleads.test';
        $fromName = config('mail.from.name') ?? config('app.name', 'Chalk Leads');
        $toAddress = 'service@furnituretaxi.site';

        $summary = $payload['summary'] ?? [];
        $items = $summary['items'] ?? [];
        $currency = '$';

        $htmlLines = [];
        $htmlLines[] = '<h2>New moving quote request</h2>';
        if (!empty($payload['widget_key'])) {
            $htmlLines[] = '<p><strong>Widget:</strong> ' . e($payload['widget_key']) . '</p>';
        }

        if (!empty($items)) {
            $htmlLines[] = '<h3>Cost Summary</h3>';
            $htmlLines[] = '<ul>';
            foreach ($items as $item) {
                $label = e($item['label'] ?? 'Item');
                $amount = isset($item['amount']) ? number_format(floatval($item['amount']), 2) : '0.00';
                $meta = !empty($item['meta']) ? ' <small>(' . e($item['meta']) . ')</small>' : '';
                $htmlLines[] = "<li><strong>{$label}:</strong> {$currency}{$amount}{$meta}</li>";
            }
            $htmlLines[] = '</ul>';
        }

        if (isset($summary['subtotal'])) {
            $htmlLines[] = '<p><strong>Subtotal:</strong> ' . $currency . number_format(floatval($summary['subtotal']), 2) . '</p>';
        }
        if (!empty($summary['appliedMinimum']) && isset($summary['minimumJobPrice'])) {
            $htmlLines[] = '<p><strong>Minimum job price applied:</strong> ' . $currency . number_format(floatval($summary['minimumJobPrice']), 2) . '</p>';
        }
        if (isset($summary['total'])) {
            $htmlLines[] = '<p><strong>Estimated total:</strong> ' . $currency . number_format(floatval($summary['total']), 2) . '</p>';
        }

        $htmlLines[] = '<h3>Submitted Data</h3>';
        $htmlLines[] = '<pre style="background:#f6f6f6;padding:12px;border-radius:8px;border:1px solid #e5e7eb;">' .
            e(json_encode($payload['data'], JSON_PRETTY_PRINT)) .
            '</pre>';

        $htmlBody = implode("\n", $htmlLines);

        $resendStart = microtime(true);
        Log::info('Resend email request started', [
            'widget_key' => $payload['widget_key'] ?? null,
            'summary_items' => count($items),
            'timestamp' => now()->toIso8601String(),
        ]);

        $response = Http::withToken($resendKey)
            ->acceptJson()
            ->timeout(15)
            ->withOptions(['verify' => false]) // disable SSL verify for local dev; ensure valid certs in prod
            ->post('https://api.resend.com/emails', [
                'from' => "{$fromName} <{$fromAddress}>",
                'to' => [$toAddress],
                'subject' => 'New moving quote request',
                'html' => $htmlBody,
            ]);

        $resendDurationMs = round((microtime(true) - $resendStart) * 1000);
        Log::info('Resend email response', [
            'status' => $response->status(),
            'duration_ms' => $resendDurationMs,
            'successful' => $response->successful(),
        ]);

        if (!$response->successful()) {
            Log::error('Resend email failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return response()->json(['message' => 'Unable to send email at this time'], 500);
        }

        $sourceHost = $this->detectSourceHost($request, $payload);
        $smartMovingHosts = collect(explode(',', (string) config('services.smart_moving.trigger_hosts', '')))
            ->map(fn($host) => strtolower(trim($host)))
            ->filter()
            ->values();
        $shouldUseSmartMoving = $sourceHost && $smartMovingHosts->contains($sourceHost);

        $gravityFormsSubmitted = false;
        $gravityFormsData = null;
        $gravityFormsError = null;

        $smartMovingSubmitted = false;
        $smartMovingData = null;
        $smartMovingError = null;

        if ($shouldUseSmartMoving) {
            Log::info('SmartMoving submission starting', [
                'widget_key' => $payload['widget_key'] ?? null,
                'source_host' => $sourceHost,
            ]);

            try {
                $smartMovingService = new SmartMovingService();
                $smartMovingData = $smartMovingService->submitLead($payload);
                $smartMovingSubmitted = true;

                Log::info('SmartMoving submission succeeded', [
                    'widget_key' => $payload['widget_key'] ?? null,
                    'source_host' => $sourceHost,
                    'response' => $smartMovingData,
                ]);
            } catch (\Exception $e) {
                $smartMovingError = $e->getMessage();
                Log::warning('SmartMoving submission failed (non-critical)', [
                    'error' => $smartMovingError,
                    'widget_key' => $payload['widget_key'] ?? null,
                    'source_host' => $sourceHost,
                ]);
            }
        }

        if (!$shouldUseSmartMoving) {
            Log::info('Gravity Forms submission starting', [
                'widget_key' => $payload['widget_key'] ?? null,
            ]);

            try {
                $gravityFormsService = new GravityFormsService();
                $gravityFormsData = $gravityFormsService->submitForm($payload);
                $gravityFormsSubmitted = true;

                Log::info('Gravity Forms submission succeeded', [
                    'widget_key' => $payload['widget_key'] ?? null,
                    'response' => $gravityFormsData,
                ]);
            } catch (\Exception $e) {
                // Log the error but don't fail the entire request
                // The email was already sent successfully
                $gravityFormsError = $e->getMessage();
                Log::warning('Gravity Forms submission failed (non-critical)', [
                    'error' => $gravityFormsError,
                    'widget_key' => $payload['widget_key'] ?? null,
                ]);
            }
        }

        return response()->json([
            'message' => 'Quote sent successfully',
            'gravity_forms_submitted' => $gravityFormsSubmitted,
            'gravity_forms_data' => $gravityFormsData,
            'gravity_forms_error' => $gravityFormsError,
            'smart_moving_submitted' => $smartMovingSubmitted,
            'smart_moving_data' => $smartMovingData,
            'smart_moving_error' => $smartMovingError,
        ]);
    }

    private function detectSourceHost(Request $request, array $payload): ?string
    {
        $candidates = [];

        if (!empty($payload['source_host'])) {
            $candidates[] = $payload['source_host'];
        }

        $originHeader = $request->headers->get('origin');
        if ($originHeader) {
            $candidates[] = $originHeader;
        }

        $refererHeader = $request->headers->get('referer') ?? $payload['referrer'] ?? null;
        if ($refererHeader) {
            $candidates[] = $refererHeader;
        }

        foreach ($candidates as $value) {
            $host = parse_url($value, PHP_URL_HOST) ?: $value;
            if (is_string($host) && $host !== '') {
                return strtolower($host);
            }
        }

        return null;
    }
}
