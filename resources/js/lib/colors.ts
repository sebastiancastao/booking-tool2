type ParsedColor = {
    r: number;
    g: number;
    b: number;
    a: number;
};

const clamp = (value: number, min: number, max: number) => Math.min(max, Math.max(min, value));

const parseColorString = (color: string): ParsedColor | null => {
    const normalized = color.trim().toLowerCase();

    if (normalized === 'white') {
        return { r: 255, g: 255, b: 255, a: 1 };
    }

    if (normalized === 'black') {
        return { r: 0, g: 0, b: 0, a: 1 };
    }

    if (normalized.startsWith('#')) {
        const hex = normalized.slice(1);

        if (hex.length === 3) {
            const r = parseInt(hex[0] + hex[0], 16);
            const g = parseInt(hex[1] + hex[1], 16);
            const b = parseInt(hex[2] + hex[2], 16);
            return { r, g, b, a: 1 };
        }

        if (hex.length === 6 || hex.length === 8) {
            const r = parseInt(hex.slice(0, 2), 16);
            const g = parseInt(hex.slice(2, 4), 16);
            const b = parseInt(hex.slice(4, 6), 16);
            const a = hex.length === 8 ? parseInt(hex.slice(6, 8), 16) / 255 : 1;
            return { r, g, b, a: clamp(a, 0, 1) };
        }

        return null;
    }

    const rgbMatch = normalized.match(
        /^rgba?\(\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})(?:\s*,\s*([0-9.]+))?\s*\)$/
    );

    if (rgbMatch) {
        const r = clamp(parseInt(rgbMatch[1], 10), 0, 255);
        const g = clamp(parseInt(rgbMatch[2], 10), 0, 255);
        const b = clamp(parseInt(rgbMatch[3], 10), 0, 255);
        const a = rgbMatch[4] !== undefined ? clamp(parseFloat(rgbMatch[4]), 0, 1) : 1;

        return { r, g, b, a };
    }

    return null;
};

export const getAccessibleTextColor = (backgroundColor: string) => {
    const parsed = parseColorString(backgroundColor);

    if (!parsed) {
        return '#000000';
    }

    const alpha = clamp(parsed.a ?? 1, 0, 1);
    const blendChannel = (channel: number) => channel * alpha + 255 * (1 - alpha);
    const srgbToLinear = (value: number) => {
        const ratio = value / 255;
        return ratio <= 0.03928 ? ratio / 12.92 : Math.pow((ratio + 0.055) / 1.055, 2.4);
    };

    const luminance =
        0.2126 * srgbToLinear(blendChannel(parsed.r)) +
        0.7152 * srgbToLinear(blendChannel(parsed.g)) +
        0.0722 * srgbToLinear(blendChannel(parsed.b));

    return luminance > 0.6 ? '#000000' : '#ffffff';
};

