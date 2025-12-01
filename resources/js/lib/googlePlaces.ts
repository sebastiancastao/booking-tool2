export function createSessionToken(): string {
    if (typeof crypto !== 'undefined' && crypto.randomUUID) {
        return crypto.randomUUID();
    }
    return Math.random().toString(36).slice(2);
}

export async function getAddressPredictions(
    query: string,
    sessionToken?: string
): Promise<Array<{ description: string; place_id: string }>> {
    console.log('Getting address predictions for:', query);
    const token = sessionToken || createSessionToken();

    try {
        const params = new URLSearchParams({
            input: query,
            sessiontoken: token,
        });

        const res = await fetch(`/api/places/autocomplete?${params.toString()}`, {
            method: 'GET',
            credentials: 'same-origin',
        });

        if (!res.ok) {
            const errorText = await res.text();
            console.error('Places autocomplete failed:', res.status, errorText);
            throw new Error(`Places autocomplete request failed: ${res.status} ${errorText}`);
        }

        const data = await res.json();
        console.log('Places API response:', data);

        if (data.status !== 'OK' && data.status !== 'ZERO_RESULTS') {
            console.error('Places API error:', data.status, data.error_message);
            throw new Error(`Places API error: ${data.status} ${data.error_message || ''}`);
        }

        const predictions = (data.predictions || [])
            .map((prediction: { place_id?: string; description?: string }) => ({
                description: prediction.description || '',
                place_id: prediction.place_id || '',
            }))
            .filter((p: { place_id: string; description: string }) => p && p.place_id && p.description);

        console.log('Parsed predictions:', predictions);
        return predictions;
    } catch (error) {
        console.error('Error in getAddressPredictions:', error);
        throw error;
    }
}

export async function fetchPlaceDetails(
    placeId: string
): Promise<{ formatted_address?: string; postal_code?: string }> {
    console.log('Fetching place details for:', placeId);

    try {
        const params = new URLSearchParams({
            place_id: placeId,
        });

        const res = await fetch(`/api/places/details?${params.toString()}`, {
            method: 'GET',
            credentials: 'same-origin',
        });

        if (!res.ok) {
            const errorText = await res.text();
            console.error('Places details failed:', res.status, errorText);
            throw new Error(`Places details request failed: ${res.status}`);
        }

        const data = await res.json();
        console.log('Place details response:', data);

        if (data.status !== 'OK') {
            console.error('Places API error:', data.status, data.error_message);
            throw new Error(`Places API error: ${data.status} ${data.error_message || ''}`);
        }

        const result = data.result || {};
        let postalCode: string | undefined;
        const components = result.address_components || [];
        for (const component of components) {
            if (component.types && component.types.includes('postal_code')) {
                postalCode = component.long_name || component.short_name;
                break;
            }
        }

        return {
            formatted_address: result.formatted_address,
            postal_code: postalCode,
        };
    } catch (error) {
        console.error('Error in fetchPlaceDetails:', error);
        throw error;
    }
}
