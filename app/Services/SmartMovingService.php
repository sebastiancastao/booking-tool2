<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmartMovingService
{
    private string $baseUrl;
    private ?string $apiKey;
    private ?string $companyId;
    private string $leadSource;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.smart_moving.base_url'), '/');
        $this->apiKey = config('services.smart_moving.api_key');
        $this->companyId = config('services.smart_moving.company_id');
        $this->leadSource = config('services.smart_moving.lead_source', 'Chalk Leads Widget');
    }

    /**
     * Submit lead data to SmartMoving.
     *
     * @param array $formData
     * @return array
     * @throws \Exception
     */
    public function submitLead(array $formData): array
    {
        if (!$this->baseUrl || !$this->apiKey) {
            throw new \Exception('SmartMoving API not configured.');
        }

        $payload = $this->mapPayload($formData);
        $start = microtime(true);

        Log::info('SmartMoving request payload prepared', [
            'base_url' => $this->baseUrl,
            'has_company_id' => (bool) $this->companyId,
            'lead_source' => $this->leadSource,
            'payload_keys' => array_keys($payload),
        ]);

        try {
            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->timeout(20)
                ->withOptions(['verify' => false])
                ->post($this->baseUrl, $payload);

            $durationMs = round((microtime(true) - $start) * 1000);
            $body = $response->json();

            Log::info('SmartMoving API response', [
                'status' => $response->status(),
                'duration_ms' => $durationMs,
                'successful' => $response->successful(),
                'response_keys' => is_array($body) ? array_keys($body) : [],
            ]);

            if (!$response->successful()) {
                throw new \Exception('SmartMoving API request failed: ' . $response->status() . ' - ' . $response->body());
            }

            if (!is_array($body)) {
                throw new \Exception('SmartMoving API request failed: invalid response payload');
            }

            return $body;
        } catch (\Exception $e) {
            Log::error('SmartMoving submission error', [
                'message' => $e->getMessage(),
                'form_data_keys' => array_keys($formData),
            ]);
            throw $e;
        }
    }

    private function mapPayload(array $formData): array
    {
        $data = isset($formData['data']) && is_array($formData['data'])
            ? $formData['data']
            : $formData;

        $fullName = $this->getValue($data, ['contact-name', 'name', 'full_name', 'fullName']) ?? '';
        $nameParts = array_values(array_filter(explode(' ', trim((string) $fullName)), fn($p) => $p !== ''));
        $firstName = $nameParts[0] ?? $fullName ?? null;
        $lastName = isset($nameParts[1]) ? implode(' ', array_slice($nameParts, 1)) : 'Customer';

        $emailRaw = $this->getValue($data, ['contact-email', 'email', 'customer-email', 'customer_email']);
        $email = $emailRaw ? trim((string) $emailRaw) : null;

        $rawPhone = $this->getValue($data, ['contact-phone', 'phone', 'phone-number', 'phone_number']);
        $digitsPhone = $rawPhone ? preg_replace('/\D+/', '', (string) $rawPhone) : null;
        $normalizedPhone = $digitsPhone && strlen($digitsPhone) >= 10
            ? substr($digitsPhone, -10)
            : $rawPhone;

        $originAddress = $this->getValue($data, ['origin-location', 'origin', 'from-address', 'pickup-address']);
        $destinationAddress = $this->getValue($data, ['target-location', 'destination', 'to-address', 'dropoff-address']);

        $fromZip = $this->extractZip($this->getValue($data, ['fromZip', 'from-zip', 'origin-zip', 'pickup-zip']));
        $toZip = $this->extractZip($this->getValue($data, ['toZip', 'to-zip', 'target-zip', 'dropoff-zip']));

        $distance = $data['distance-calculation'] ?? $data['distance_calculation'] ?? null;
        $miles = is_array($distance) ? ($distance['miles'] ?? null) : null;
        $moveDate = $this->normalizeDate(
            $this->getValue($data, [
                'moveDate', 'move-date', 'move_date',
                'date-selection', 'date_selection',
                'pickup-date', 'pickup_date',
                'service-date', 'service_date',
                'preferred-date', 'preferred_date',
            ])
        );

        $moveSize = $this->getValue($data, ['moveSize', 'project-scope', 'service-selection', 'service-type', 'location-type']);
        $explicitNote = $formData['smart_moving_note'] ?? ($data['smart_moving_note'] ?? null);
        $serializedData = json_encode($data, JSON_PRETTY_PRINT);
        $notesSections = [];
        $affiliateName = null;

        if ($explicitNote !== null && $explicitNote !== '') {
            $notesSections[] = is_string($explicitNote)
                ? trim($explicitNote)
                : (json_encode($explicitNote, JSON_PRETTY_PRINT) ?: '');
            $affiliateName = 'discount web form';
        }
        if ($serializedData !== false) {
            $notesSections[] = $serializedData;
        }

        $notes = trim(implode("\n\n", array_filter($notesSections, fn($value) => is_string($value) && trim($value) !== '')));

        $payload = [
            'lead_source' => $this->leadSource,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $normalizedPhone,
            'from_address' => $originAddress,
            'to_address' => $destinationAddress,
            'from_zip' => $fromZip,
            'to_zip' => $toZip,
            'move_date' => $moveDate,
            'move_size' => $moveSize,
            'estimated_miles' => $miles,
            'widget_key' => $formData['widget_key'] ?? null,
            'notes' => $notes,
            'affiliateName' => $affiliateName,
        ];

        if ($this->companyId) {
            $payload['company_id'] = $this->companyId;
        }

        return array_filter($payload, fn($value) => $value !== null && $value !== '');
    }

    private function getValue(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && $data[$key] !== null && $data[$key] !== '') {
                return $data[$key];
            }
        }
        return null;
    }

    private function extractZip($value): ?string
    {
        if (!$value || !is_string($value)) {
            return null;
        }
        if (preg_match('/\b(\d{5})(?:-\d{4})?\b/', $value, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function normalizeDate($value): ?string
    {
        if (!$value || !is_string($value)) {
            return null;
        }
        $ts = strtotime($value);
        if ($ts === false) {
            return null;
        }
        return date('Y-m-d', $ts);
    }
}
