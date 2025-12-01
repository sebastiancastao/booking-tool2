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

export function createSessionToken(): any | null {
    const google = window.google;
    if (google?.maps?.places) {
        return new google.maps.places.AutocompleteSessionToken();
    }
    return null;
}

export async function getAddressPredictions(
    query: string,
    sessionToken?: any
): Promise<Array<{ description: string; place_id: string }>> {
    const google = await loadGooglePlaces();

    return new Promise((resolve, reject) => {
        const service = new google.maps.places.AutocompleteService();
        service.getPlacePredictions(
            {
                input: query,
                types: ['address'],
                sessionToken,
            },
            (predictions: any, status: string) => {
                if (
                    status !== google.maps.places.PlacesServiceStatus.OK &&
                    status !== google.maps.places.PlacesServiceStatus.ZERO_RESULTS
                ) {
                    reject(new Error(`Places autocomplete failed: ${status}`));
                    return;
                }
                resolve(
                    (predictions || []).map((prediction: any) => ({
                        description: prediction.description,
                        place_id: prediction.place_id,
                    }))
                );
            }
        );
    });
}

export async function fetchPlaceDetails(
    placeId: string,
    sessionToken?: any
): Promise<{ formatted_address?: string; postal_code?: string }> {
    const google = await loadGooglePlaces();

    return new Promise((resolve, reject) => {
        const service = new google.maps.places.PlacesService(document.createElement('div'));
        service.getDetails(
            {
                placeId,
                fields: ['formatted_address', 'address_components'],
                sessionToken,
            },
            (result: any, status: string) => {
                if (status !== google.maps.places.PlacesServiceStatus.OK) {
                    reject(new Error(`Places details failed: ${status}`));
                    return;
                }

                let postalCode: string | undefined;
                const components = result?.address_components || [];
                for (const component of components) {
                    if (component.types && component.types.includes('postal_code')) {
                        postalCode = component.long_name;
                        break;
                    }
                }

                resolve({
                    formatted_address: result?.formatted_address,
                    postal_code: postalCode,
                });
            }
        );
    });
}
