const MAPBOX_TOKEN = import.meta.env.VITE_MAPBOX_TOKEN;

type Coordinates = {
    lat: number;
    lng: number;
};

type GeocodeResult = Coordinates & {
    placeName: string;
};

const MAPBOX_GEOCODE_BASE = 'https://api.mapbox.com/geocoding/v5/mapbox.places';

function haversineMiles(a: Coordinates, b: Coordinates): number {
    const toRad = (deg: number) => (deg * Math.PI) / 180;
    const R = 3958.8; // Earth radius in miles

    const dLat = toRad(b.lat - a.lat);
    const dLon = toRad(b.lng - a.lng);

    const lat1 = toRad(a.lat);
    const lat2 = toRad(b.lat);

    const h =
        Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.sin(dLon / 2) * Math.sin(dLon / 2) * Math.cos(lat1) * Math.cos(lat2);

    return 2 * R * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h));
}

async function fetchGeocode(address: string): Promise<GeocodeResult> {
    if (!MAPBOX_TOKEN) {
        throw new Error('Missing Mapbox token. Set VITE_MAPBOX_TOKEN in your environment.');
    }

    const url = `${MAPBOX_GEOCODE_BASE}/${encodeURIComponent(address)}.json?access_token=${MAPBOX_TOKEN}&limit=1`;
    const response = await fetch(url);

    if (!response.ok) {
        throw new Error('Mapbox geocoding request failed');
    }

    const data = await response.json();
    const feature = data.features?.[0];

    if (!feature || !feature.center) {
        throw new Error('No results found for that address');
    }

    const [lng, lat] = feature.center;

    return {
        lat,
        lng,
        placeName: feature.place_name,
    };
}

export async function geocodeAddress(address: string): Promise<GeocodeResult> {
    return fetchGeocode(address);
}

export async function geocodeDistance(origin: string, destination: string): Promise<{
    origin: GeocodeResult;
    destination: GeocodeResult;
    miles: number;
}> {
    const [originResult, destinationResult] = await Promise.all([
        fetchGeocode(origin),
        fetchGeocode(destination),
    ]);

    const miles = haversineMiles(originResult, destinationResult);

    return {
        origin: originResult,
        destination: destinationResult,
        miles,
    };
}
