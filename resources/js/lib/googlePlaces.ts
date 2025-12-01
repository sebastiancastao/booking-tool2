declare global {
    interface Window {
        google?: any;
    }
}

let loaderPromise: Promise<any> | null = null;

function getGoogleKey(): string | undefined {
    return import.meta.env.VITE_GOOGLE_MAPS_API_KEY || import.meta.env.VITE_GOOGLE_PLACES_API_KEY;
}

export function loadGooglePlaces(): Promise<any> {
    if (typeof window === 'undefined') {
        return Promise.reject(new Error('Google Places requires a browser environment.'));
    }

    const existing = window.google;
    if (existing?.maps?.places) {
        return Promise.resolve(existing);
    }

    if (loaderPromise) {
        return loaderPromise;
    }

    const key = getGoogleKey();
    if (!key) {
        return Promise.reject(new Error('Missing Google Maps Places API key (VITE_GOOGLE_MAPS_API_KEY).'));
    }

    loaderPromise = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=${key}&libraries=places&language=en`;
        script.async = true;
        script.defer = true;
        script.onload = () => {
            const google = window.google;
            if (google?.maps?.places) {
                resolve(google);
            } else {
                reject(new Error('Google Maps Places failed to load.'));
            }
        };
        script.onerror = () => reject(new Error('Unable to load Google Maps Places script.'));
        document.head.appendChild(script);
    });

    return loaderPromise;
}

export function createSessionToken(): string {
    if (typeof crypto !== 'undefined' && crypto.randomUUID) {
        return crypto.randomUUID();
    }
    return Math.random().toString(36).slice(2);
}

const PLACES_AUTOCOMPLETE_URL = 'https://places.googleapis.com/v1/places:autocomplete';
const PLACES_DETAILS_URL = 'https://places.googleapis.com/v1/places/';

export async function getAddressPredictions(
    query: string,
    sessionToken?: string
): Promise<Array<{ description: string; place_id: string }>> {
    const key = getGoogleKey();
    if (!key) throw new Error('Missing Google Maps Places API key.');

    const token = sessionToken || createSessionToken();
    const res = await fetch(PLACES_AUTOCOMPLETE_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Goog-Api-Key': key,
            'X-Goog-FieldMask': 'place_prediction.place_id,place_prediction.text',
        },
        body: JSON.stringify({
            input: query,
            sessionToken: token,
            types: ['address'],
        }),
    });

    if (!res.ok) {
        throw new Error('Places autocomplete request failed');
    }
    const data = await res.json();
    const predictions = data.placePredictions || data.place_predictions || [];

    return predictions
        .map((prediction: any) => ({
            description:
                prediction?.text?.text ||
                prediction?.structured_formatting?.main_text ||
                prediction?.description ||
                '',
            place_id: prediction?.placeId || prediction?.place_id,
        }))
        .filter((p: any) => p.place_id && p.description);
}

export async function fetchPlaceDetails(
    placeId: string,
    sessionToken?: string
): Promise<{ formatted_address?: string; postal_code?: string }> {
    const key = getGoogleKey();
    if (!key) throw new Error('Missing Google Maps Places API key.');
    const token = sessionToken || createSessionToken();

    const res = await fetch(`${PLACES_DETAILS_URL}${encodeURIComponent(placeId)}?sessionToken=${token}`, {
        method: 'GET',
        headers: {
            'X-Goog-Api-Key': key,
            'X-Goog-FieldMask': 'formattedAddress,addressComponents',
        },
    });

    if (!res.ok) {
        throw new Error('Places details request failed');
    }
    const data = await res.json();

    let postalCode: string | undefined;
    const components = data.addressComponents || data.address_components || [];
    for (const component of components) {
        if (component.types && component.types.includes('postal_code')) {
            postalCode = component.long_name || component.longName || component.shortName || component.short_name;
            break;
        }
    }

    return {
        formatted_address: data.formattedAddress || data.formatted_address,
        postal_code: postalCode,
    };
}
