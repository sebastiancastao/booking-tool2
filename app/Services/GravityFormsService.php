<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GravityFormsService
{
    private string $baseUrl;
    private string $publicKey;
    private string $privateKey;
    private string $formId;

    public function __construct()
    {
        $this->baseUrl = config('services.gravity_forms.base_url', 'https://www.atlantafurnituremovers.com/gravityformsapi');
        $this->publicKey = config('services.gravity_forms.public_key', '0b7fbd1824');
        $this->privateKey = config('services.gravity_forms.private_key', '27842c3fdf765bd');
        $this->formId = config('services.gravity_forms.form_id', '3');
    }

    /**
     * Build authenticated URL for Gravity Forms API
     * Uses signature-based authentication (HMAC-SHA1)
     */
    private function buildAuthenticatedUrl(string $endpoint, string $method = 'GET'): array
    {
        $expires = time() + 3600; // 1 hour from now
        $fullUrl = $this->baseUrl . $endpoint;

        // Create signature string: "{public_key}:{method}:{url}:{expires}"
        $stringToSign = "{$this->publicKey}:{$method}:{$fullUrl}:{$expires}";

        // Generate HMAC-SHA1 signature
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->privateKey, true));

        // URL encode the signature
        $encodedSignature = rawurlencode($signature);

        // Append auth parameters to URL
        $authenticatedUrl = "{$fullUrl}?api_key={$this->publicKey}&signature={$encodedSignature}&expires={$expires}";

        return [
            'url' => $authenticatedUrl,
            'expires' => $expires,
        ];
    }

    /**
     * Submit form data to Gravity Forms
     *
     * @param array $formData The form data to submit
     * @return array Response from Gravity Forms API
     * @throws \Exception If submission fails
     */
    public function submitForm(array $formData): array
    {
        $endpoint = "/forms/{$this->formId}/submissions";

        // Build authenticated URL
        $auth = $this->buildAuthenticatedUrl($endpoint, 'POST');

        // Map form data to Gravity Forms field structure
        $fieldData = $this->mapFormDataToGravityFields($formData);

        try {
            $response = Http::asJson() // Gravity Forms expects JSON body
                ->timeout(15)
                ->withOptions(['verify' => false]) // Disable SSL verify for local dev; use valid certs in prod
                ->post($auth['url'], $fieldData);

            $result = $response->json();
            if (!$result || !is_array($result)) {
                $rawBody = $response->body();
                Log::error('Gravity Forms submission failed - empty or invalid JSON response', [
                    'status' => $response->status(),
                    'body' => $rawBody,
                    'form_data' => $formData,
                    'mapped_fields' => $fieldData,
                ]);
                throw new \Exception('Gravity Forms API request failed: invalid response payload - ' . $rawBody);
            }

            $apiStatus = $result['status'] ?? $response->status();

            // Treat Gravity Forms API-level errors as failures even if HTTP 200
            $isValid = $result['response']['is_valid'] ?? null;
            $validationMessages = $result['response']['validation_messages'] ?? null;
            if ($apiStatus >= 400 || $isValid === false) {
                $errorBody = $result['response'] ?? $response->body();
                Log::error('Gravity Forms submission failed', [
                    'status' => $apiStatus,
                    'body' => $errorBody,
                    'validation' => $validationMessages,
                    'form_data' => $formData,
                    'mapped_fields' => $fieldData,
                ]);

                $errorMessage = is_string($errorBody) ? $errorBody : json_encode($errorBody);
                if ($validationMessages) {
                    $errorMessage .= ' | Validation: ' . json_encode($validationMessages);
                }

                throw new \Exception("Gravity Forms API request failed: {$apiStatus} - {$errorMessage}");
            }

            Log::info('Gravity Forms submission successful', [
                'response' => $result,
                'mapped_fields' => $fieldData,
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Gravity Forms submission error', [
                'message' => $e->getMessage(),
                'form_data' => $formData,
            ]);
            throw $e;
        }
    }

    /**
     * Map form data to Gravity Forms field IDs
     * Update these field IDs to match your Gravity Forms setup
     */
    private function mapFormDataToGravityFields(array $formData): array
    {
        // Extract contact info from nested data if present
        if (isset($formData['data']) && is_array($formData['data'])) {
            $data = $formData['data'];
        } else {
            $data = $formData;
        }

        $getValue = function (array $keys) use ($data) {
            foreach ($keys as $key) {
                if (isset($data[$key]) && $data[$key] !== null && $data[$key] !== '') {
                    return $data[$key];
                }
            }
            return null;
        };

        $normalizeDate = function ($value): ?string {
            if (!$value || !is_string($value)) {
                return null;
            }
            $ts = strtotime($value);
            if ($ts === false) {
                return null;
            }
            return date('m/d/Y', $ts); // match Gravity Forms default m/d/Y
        };

        $extractZip = function (?string $text): ?string {
            if (!$text || !is_string($text)) {
                return null;
            }
            if (preg_match('/\\b(\\d{5})(?:-\\d{4})?\\b/', $text, $matches)) {
                return $matches[1];
            }
            return null;
        };

        $originAddress = $getValue(['fromZip', 'from-zip', 'origin-zip', 'origin-location', 'origin-location-field', 'origin']);
        $targetAddress = $getValue(['toZip', 'to-zip', 'target-zip', 'target-location', 'target-location-field', 'destination']);

        // Field mapping to actual Gravity Forms IDs for Form 3 (advanced name + email + phone + zips + date + size)
        $fullName = $getValue(['contact-name', 'name', 'full_name', 'fullName']) ?? '';
        $nameParts = array_values(array_filter(explode(' ', trim((string) $fullName)), fn($p) => $p !== ''));
        $firstName = $nameParts[0] ?? $fullName ?? null;
        $lastName = isset($nameParts[1]) ? implode(' ', array_slice($nameParts, 1)) : 'Customer';

        $emailRaw = $getValue(['contact-email', 'email', 'customer-email', 'customer_email']);
        $email = $emailRaw ? trim((string) $emailRaw) : null;
        $rawPhone = $getValue(['contact-phone', 'phone', 'phone-number', 'phone_number']);
        $digitsPhone = $rawPhone ? preg_replace('/\\D+/', '', (string) $rawPhone) : null;
        $normalizedPhone = $digitsPhone && strlen($digitsPhone) >= 10
            ? substr($digitsPhone, -10)
            : $rawPhone;
        $fromZip = $getValue(['fromZip', 'origin-zip', 'zip-from', 'origin-zip-code']) ?? $extractZip($originAddress);
        $toZip = $getValue(['toZip', 'target-zip', 'zip-to', 'destination-zip']) ?? $extractZip($targetAddress);

        $rawDate = $getValue([
            'moveDate', 'move-date', 'move_date',
            'date-selection', 'date_selection',
            'pickup-date', 'pickup_date',
            'service-date', 'service_date',
            'preferred-date', 'preferred_date',
        ]);
        $moveDate = $normalizeDate($rawDate) ?? date('m/d/Y'); // fallback to today to satisfy required field

        $moveSizeRaw = $getValue(['moveSize', 'project-scope', 'service-selection', 'service-type', 'location-type']);
        $normalizeMoveSize = function ($value) {
            if (!$value || !is_string($value)) {
                return $value;
            }
            $map = [
                'studio' => 'Studio Apartment',
                '1 bedroom' => '1 Bedroom Apartment',
                '2 bedroom' => '2 Bedroom House',
                '3 bedroom' => '3 Bedroom House',
                '4 bedroom' => '4 Bedroom House',
                '5 bedroom' => '5 Bedroom House',
            ];
            $lower = strtolower($value);
            foreach ($map as $needle => $replacement) {
                if (str_contains($lower, $needle)) {
                    return $replacement;
                }
            }
            return $value;
        };
        $moveSize = $normalizeMoveSize($moveSizeRaw);

        $gravityFieldsData = [
            'input_1' => $fullName,
            'input_1.3' => $firstName,
            'input_1.6' => $lastName,
            'input_3' => $email,
            'input_4' => $normalizedPhone,
            'input_8' => $fromZip,
            'input_9' => $toZip,
            'input_5' => $moveDate,
            'input_6' => $moveSize,
        ];

        // Convert booleans to Yes/No strings for Gravity Forms
        foreach ($gravityFieldsData as $key => $value) {
            if (is_bool($value)) {
                $gravityFieldsData[$key] = $value ? 'Yes' : 'No';
            }
        }

        // Remove null/empty values so we only send populated fields
        $gravityFieldsData = array_filter(
            $gravityFieldsData,
            fn($value) => $value !== null && $value !== ''
        );

        // Provide both "input_1" and numeric "1" keys for compatibility with Gravity Forms API variations
        $inputValues = [];
        foreach ($gravityFieldsData as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $inputValues[$key] = $value;
            if (str_starts_with($key, 'input_')) {
                $numericKey = substr($key, 6);
                if ($numericKey !== '' && !isset($inputValues[$numericKey])) {
                    $inputValues[$numericKey] = $value;
                }
                if (str_contains($key, '.')) {
                    $numericKey = str_replace('input_', '', $key);
                    if (!isset($inputValues[$numericKey])) {
                        $inputValues[$numericKey] = $value;
                    }
                }
            }
        }

        $payload = [
            'input_values' => $inputValues,
            'source_url' => config('app.url'),
        ];

        if (empty($payload['input_values'])) {
            Log::warning('Gravity Forms submission skipped - no mapped fields found', [
                'form_data_keys' => array_keys($data),
            ]);
        }

        return $payload;
    }

    /**
     * Test the Gravity Forms connection
     */
    public function testConnection(): bool
    {
        try {
            $endpoint = "/forms/{$this->formId}";
            $auth = $this->buildAuthenticatedUrl($endpoint, 'GET');

            $response = Http::timeout(10)
                ->withOptions(['verify' => false])
                ->get($auth['url']);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Gravity Forms connection test failed', [
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
