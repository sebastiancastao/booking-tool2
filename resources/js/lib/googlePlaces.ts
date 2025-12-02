// Mapbox-based geocoding helpers for autocomplete, place details, and distance.
// Requires a public Mapbox token (pk...) provided via VITE_MAPBOX_TOKEN at build time
// or injected at runtime on window.__MAPBOX_TOKEN__.

declare global {
    interface Window {
        __MAPBOX_TOKEN__?: string;
    }
}

const MAPBOX_TOKEN =
    import.meta.env.VITE_MAPBOX_TOKEN ||
    (typeof window !== 'undefined' ? window.__MAPBOX_TOKEN__ : undefined);

if (!MAPBOX_TOKEN) {
    console.warn('Missing VITE_MAPBOX_TOKEN. Mapbox geocoding will fail.');
}

// Bias to Georgia, USA (when users omit ZIP codes)
const GEORGIA_CENTER = { latitude: 32.1656, longitude: -82.9001 };
const GEORGIA_PROXIMITY = `${GEORGIA_CENTER.longitude},${GEORGIA_CENTER.latitude}`;
const GEORGIA_COUNTRY = 'US';

type MapboxFeature = {
    id: string;
    place_name: string;
    center: [number, number]; // [lng, lat]
    context?: Array<{ id?: string; text?: string }>;
    properties?: Record<string, any>;
};

const toRadians = (deg: number) => (deg * Math.PI) / 180;
const haversineMiles = (a: { lat: number; lng: number }, b: { lat: number; lng: number }) => {
    const R = 3958.8;
    const dLat = toRadians(b.lat - a.lat);
    const dLon = toRadians(b.lng - a.lng);
    const lat1 = toRadians(a.lat);
    const lat2 = toRadians(b.lat);
    const h =
        Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.sin(dLon / 2) * Math.sin(dLon / 2) * Math.cos(lat1) * Math.cos(lat2);
    return 2 * R * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h));
};

const safeEncode = (value: any): string => {
    try {
        return btoa(unescape(encodeURIComponent(JSON.stringify(value))));
    } catch {
        return '';
    }
};

const safeDecode = (value: string): any | null => {
    try {
        return JSON.parse(decodeURIComponent(escape(atob(value))));
    } catch {
        return null;
    }
};

const findPostcode = (feature?: MapboxFeature): string | undefined => {
    if (!feature) return undefined;
    if (feature.properties?.postcode) return feature.properties.postcode;
    const ctx = feature.context || [];
    for (const c of ctx) {
        if (c.id && c.id.startsWith('postcode') && c.text) {
            return c.text;
        }
    }
    return undefined;
};

const forwardGeocode = async (query: string, limit = 5): Promise<MapboxFeature[]> => {
    if (!MAPBOX_TOKEN) throw new Error('Missing Mapbox token');
    const url = new URL(`https://api.mapbox.com/geocoding/v5/mapbox.places/${encodeURIComponent(query)}.json`);
    url.searchParams.set('access_token', MAPBOX_TOKEN);
    url.searchParams.set('autocomplete', 'true');
    url.searchParams.set('limit', String(limit));
    url.searchParams.set('country', GEORGIA_COUNTRY);
    url.searchParams.set('proximity', GEORGIA_PROXIMITY);
    url.searchParams.set('types', 'address,place,postcode');

    const res = await fetch(url.toString());
    if (!res.ok) throw new Error(`Mapbox forward geocode failed: ${res.status}`);
    const data = await res.json();
    return data.features || [];
};

const reversePostal = async (lat: number, lng: number): Promise<string | undefined> => {
    if (!MAPBOX_TOKEN) return undefined;
    const url = new URL(`https://api.mapbox.com/geocoding/v5/mapbox.places/${lng},${lat}.json`);
    url.searchParams.set('access_token', MAPBOX_TOKEN);
    url.searchParams.set('types', 'postcode,address');
    url.searchParams.set('limit', '1');

    const res = await fetch(url.toString());
    if (!res.ok) return undefined;
    const data = await res.json();
    const feat = data.features?.[0];
    return findPostcode(feat) || feat?.place_name?.match(/\b(\d{5})(?:-\d{4})?\b/)?.[1];
};

export function createSessionToken(): string {
    if (typeof crypto !== 'undefined' && crypto.randomUUID) return crypto.randomUUID();
    return Math.random().toString(36).slice(2);
}

// No-op shim to keep widget code paths intact (replaces Google loader)
export function loadGooglePlaces(): Promise<null> {
    return Promise.resolve(null);
}

export async function getAddressPredictions(
    query: string,
    _sessionToken?: string
): Promise<Array<{ description: string; place_id: string }>> {
    const features = await forwardGeocode(query, 5);
    return features
        .map((feature) => ({
            description: feature.place_name,
            place_id: safeEncode({
                id: feature.id,
                place_name: feature.place_name,
                center: feature.center,
                postcode: findPostcode(feature),
            }),
        }))
        .filter((p) => p.description && p.place_id);
}

export async function fetchPlaceDetails(
    placeId: string
): Promise<{ formatted_address?: string; postal_code?: string; location?: { lat: number; lng: number } }> {
    const decoded = safeDecode(placeId);
    if (decoded?.place_name && decoded?.center) {
        const [lng, lat] = decoded.center as [number, number];
        const postal =
            decoded.postcode ||
            (await reversePostal(lat, lng)) ||
            decoded.place_name.match(/\b(\d{5})(?:-\d{4})?\b/)?.[1];
        return {
            formatted_address: decoded.place_name,
            postal_code: postal,
            location: { lat, lng },
        };
    }

    const feats = await forwardGeocode(placeId, 1);
    const feat = feats[0];
    if (!feat) return {};
    const [lng, lat] = feat.center;
    const postal =
        findPostcode(feat) || feat.place_name.match(/\b(\d{5})(?:-\d{4})?\b/)?.[1] || (await reversePostal(lat, lng));
    return {
        formatted_address: feat.place_name,
        postal_code: postal,
        location: { lat, lng },
    };
}

export async function geocodeDistance(origin: string, destination: string): Promise<{
    origin: { lat: number; lng: number; formatted_address?: string; place_id?: string; postal_code?: string };
    destination: { lat: number; lng: number; formatted_address?: string; place_id?: string; postal_code?: string };
    miles: number;
}> {
    const [originFeat, destFeat] = await Promise.all([forwardGeocode(origin, 1), forwardGeocode(destination, 1)]);
    const o = originFeat[0];
    const d = destFeat[0];
    if (!o || !d) {
        throw new Error('Unable to geocode origin or destination');
    }

    const [oLng, oLat] = o.center;
    const [dLng, dLat] = d.center;

    const originPostal = findPostcode(o) || (await reversePostal(oLat, oLng));
    const destPostal = findPostcode(d) || (await reversePostal(dLat, dLng));

    const originData = {
        lat: oLat,
        lng: oLng,
        formatted_address: o.place_name,
        place_id: safeEncode({ id: o.id, place_name: o.place_name, center: o.center, postcode: originPostal }),
        postal_code: originPostal,
    };
    const destData = {
        lat: dLat,
        lng: dLng,
        formatted_address: d.place_name,
        place_id: safeEncode({ id: d.id, place_name: d.place_name, center: d.center, postcode: destPostal }),
        postal_code: destPostal,
    };

    const miles = haversineMiles({ lat: oLat, lng: oLng }, { lat: dLat, lng: dLng });

    return {
        origin: originData,
        destination: destData,
        miles,
    };
}
