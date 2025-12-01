import { motion, AnimatePresence } from 'framer-motion';
import { useEffect, useState } from 'react';
import {
    ArrowLeft,
    ArrowRight,
    Check,
    Truck,
    Home,
    Package,
    Calendar,
    MapPin,
    Mail,
    Phone,
    User,
    Briefcase,
    Heart,
    Building,
    Building2,
    Star,
    Users,
    ArrowUpDown,
    ArrowUp,
    ArrowDown,
    Navigation,
    Shield,
    Wrench,
    Archive,
    AlertTriangle,
    Sunrise,
    Sun,
    Sunset,
    X,
    MessageCircle,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    getAddressPredictions,
    fetchPlaceDetails,
    createSessionToken,
    geocodeDistance,
    loadGooglePlaces,
} from '@/lib/googlePlaces';

interface WidgetConfig {
    widget_id: string;
    steps_data: Record<string, StepData>;
    step_order: string[];
    branding: {
        primary_color?: string;
        secondary_color?: string;
        company_name?: string;
        logo_url?: string;
        font_family?: string;
    };
    pricing?: Record<string, any>;
    estimation_settings?: {
        tax_rate?: number;
        service_area_miles?: number;
        minimum_job_price?: number;
        show_price_ranges?: boolean;
        currency?: string;
        currency_symbol?: string;
    };
}

interface StepData {
    id: string;
    title: string;
    subtitle?: string;
    prompt?: {
        message: string;
        type: string;
    };
    options: OptionData[];
    buttons?: {
        primary?: {
            text: string;
            action: string;
        };
        secondary?: {
            text: string;
            action: string;
        };
    };
    layout?: {
        type: string;
        columns?: number;
        centered?: boolean;
    };
    validation?: {
        required?: boolean;
        field?: string;
    };
}

interface OptionData {
    id: string;
    value: string;
    title: string;
    description?: string;
    icon?: string;
    type?: string;
    estimation?: any;
}

interface WidgetRendererProps {
    config: WidgetConfig;
    onSubmit?: (data: Record<string, any>) => void;
}

const iconMap: Record<string, any> = {
    Truck,
    Users,
    Home,
    Package,
    Calendar,
    MapPin,
    Mail,
    Phone,
    User,
    Briefcase,
    Heart,
    Building,
    Building2,
    Star,
    ArrowUpDown,
    ArrowUp,
    ArrowDown,
    Navigation,
    Shield,
    Wrench,
    Archive,
    AlertTriangle,
    Sunrise,
    Sun,
    Sunset,
    X,
    MessageCircle,
};

export function WidgetRenderer({ config, onSubmit }: WidgetRendererProps) {
    const distanceStepKey = Object.keys(config.steps_data || {}).find(
        (key) => config.steps_data[key]?.layout?.type === 'route-calculation'
    );

    const stepOrder = Array.isArray(config.step_order)
        ? config.step_order.filter((key) => config.steps_data[key]?.layout?.type !== 'route-calculation')
        : [];
    const [currentStepIndex, setCurrentStepIndex] = useState(0);
    const [formData, setFormData] = useState<Record<string, any>>({});
    const [selectedOptions, setSelectedOptions] = useState<Record<string, any>>({});
    const [submitStatus, setSubmitStatus] = useState<{
        state: 'idle' | 'sending' | 'success' | 'error';
        message?: string;
        gravityFormsSubmitted?: boolean;
    }>({ state: 'idle' });
    const [originAddress, setOriginAddress] = useState('');
    const [destinationAddress, setDestinationAddress] = useState('');
    const [distanceResult, setDistanceResult] = useState<{ miles: number; estimatedCost?: number } | null>(null);
    const [distanceStatus, setDistanceStatus] = useState<'idle' | 'loading' | 'error' | 'success'>('idle');
    const [distanceError, setDistanceError] = useState<string | null>(null);
    const [lastDistanceInputs, setLastDistanceInputs] = useState<{ origin: string; destination: string } | null>(null);
    const [phoneError, setPhoneError] = useState<string | null>(null);
    const [placesReady, setPlacesReady] = useState(false);
    const [placesLoading, setPlacesLoading] = useState(false);
    const [placesError, setPlacesError] = useState<string | null>(null);
    const [placesSession, setPlacesSession] = useState<string | null>(null);
    const [originPredictions, setOriginPredictions] = useState<Array<{ description: string; place_id: string }>>([]);
    const [destinationPredictions, setDestinationPredictions] = useState<
        Array<{ description: string; place_id: string }>
    >([]);

    // Steps that should remain optional when enforcing required progression
    const optionalStepTitles = [
        'Pickup Location Challenges',
        'Destination Challenges',
        'Any additional services?',
        'Select Moving Supplies',
    ].map((title) => title.toLowerCase());

    const isStepOptional = (step?: StepData) => {
        if (!step?.title) return false;
        return optionalStepTitles.includes(step.title.toLowerCase());
    };

    const isStepSatisfied = () => {
        if (!currentStep) return false;
        if (currentStep.layout?.type === 'route-calculation') {
            return !!distanceResult;
        }
        const selection = selectedOptions[currentStepKey];
        if (selection === undefined || selection === null) {
            return false;
        }
        if (typeof selection === 'string') {
            return selection.trim() !== '';
        }
        return true;
    };

    const totalSteps = stepOrder.length;
    const currentStepKey = stepOrder[currentStepIndex];
    const currentStep = currentStepKey ? config.steps_data[currentStepKey] : undefined;
    const isLastStep = currentStepIndex === totalSteps - 1;
    const primaryColor = config.branding?.primary_color || '#F4C443';

    const extractZipFromText = (text?: string | null) => {
        if (!text) {
            return null;
        }

        const match = text.match(/\b(\d{5})(?:-\d{4})?\b/);
        return match ? match[1] : null;
    };

    const isPhoneValueValid = (value?: string | null) => {
        if (!value) {
            return false;
        }
        const digits = value.replace(/\D/g, '');
        return digits.length >= 10;
    };

    const getCookieValue = (name: string) => {
        const match = document.cookie
            .split(';')
            .map((c) => c.trim())
            .find((c) => c.startsWith(`${name}=`));
        return match ? match.split('=')[1] : null;
    };

    const ensureCsrfCookie = async () => {
        const xsrf = getCookieValue('XSRF-TOKEN');
        if (!xsrf) {
            try {
                await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
            } catch (err) {
                console.error('Unable to fetch CSRF cookie', err);
            }
        }
    };

    const ensurePlacesLoaded = async (): Promise<boolean> => {
        if (placesReady) return true;
        if (placesLoading) return false;

        setPlacesLoading(true);
        try {
            // Using backend proxy, no need to load Google Places JS library
            setPlacesReady(true);
            setPlacesError(null);
            setPlacesSession((prev) => prev ?? createSessionToken());
            return true;
        } catch (err: any) {
            console.error('Places initialization failed', err);
            setPlacesReady(false);
            setPlacesError(err?.message || 'Address autocomplete unavailable.');
            return false;
        } finally {
            setPlacesLoading(false);
        }
    };

    const updatePredictions = async (value: string, type: 'origin' | 'destination') => {
        const trimmed = value.trim();
        if (trimmed.length < 3) {
            if (type === 'origin') setOriginPredictions([]);
            else setDestinationPredictions([]);
            return;
        }

        const canUsePlaces = placesReady || (await ensurePlacesLoaded());
        if (!canUsePlaces) {
            if (type === 'origin') setOriginPredictions([]);
            else setDestinationPredictions([]);
            return;
        }

        try {
            const token = placesSession || createSessionToken();
            if (!placesSession && token) {
                setPlacesSession(token);
            }
            const predictions = await getAddressPredictions(trimmed, token || undefined);
            if (type === 'origin') setOriginPredictions(predictions);
            else setDestinationPredictions(predictions);
        } catch (err: any) {
            console.error('Places predictions failed', err);
            setPlacesError(err?.message || 'Unable to fetch address suggestions.');
        }
    };

    const handleAddressInputChange = (value: string, type: 'origin' | 'destination', stepKey?: string) => {
        setPlacesError(null);
        const targetStepKey = stepKey || currentStepKey;
        if (type === 'origin') {
            setOriginAddress(value);
            setFormData((prev) => ({
                ...prev,
                'origin-location': value,
                'origin-location-field': value,
                origin: value,
            }));
            setSelectedOptions((prev) => ({ ...prev, [targetStepKey]: value }));
        } else {
            setDestinationAddress(value);
            setFormData((prev) => ({
                ...prev,
                'target-location': value,
                'target-location-field': value,
                destination: value,
            }));
            setSelectedOptions((prev) => ({ ...prev, [targetStepKey]: value }));
        }
        void updatePredictions(value, type);
    };

    const applyPredictionSelection = async (
        prediction: { description: string; place_id: string },
        type: 'origin' | 'destination',
        stepKey?: string
    ) => {
        if (!prediction?.description) return;

        const targetStepKey = stepKey || currentStepKey;
        let formatted = prediction.description;
        let postalCode: string | null = null;

        try {
            const details = await fetchPlaceDetails(prediction.place_id);
            formatted = details?.formatted_address || formatted;
            postalCode = details?.postal_code || extractZipFromText(details?.formatted_address) || null;
            console.log('Address details:', {
                formatted_address: details?.formatted_address,
                postal_code: details?.postal_code,
                extracted_zip: extractZipFromText(details?.formatted_address),
                final_postal_code: postalCode
            });
        } catch (err) {
            console.error('Place details lookup failed', err);
            // Try to extract ZIP from description as last resort
            postalCode = extractZipFromText(prediction.description) || null;
        }

        if (type === 'origin') {
            setOriginAddress(formatted);
            setOriginPredictions([]);
            setFormData((prev) => {
                const next = {
                    ...prev,
                    'origin-location': formatted,
                    'origin-location-field': formatted,
                    origin: formatted,
                };
                if (postalCode) {
                    next['fromZip'] = postalCode;
                    next['from-zip'] = postalCode;
                    next['origin-zip'] = postalCode;
                }
                return next;
            });
            setSelectedOptions((prev) => ({ ...prev, [targetStepKey]: formatted }));
        } else {
            setDestinationAddress(formatted);
            setDestinationPredictions([]);
            setFormData((prev) => {
                const next = {
                    ...prev,
                    'target-location': formatted,
                    'target-location-field': formatted,
                    destination: formatted,
                };
                if (postalCode) {
                    next['toZip'] = postalCode;
                    next['target-zip'] = postalCode;
                    next['destination-zip'] = postalCode;
                }
                return next;
            });
            setSelectedOptions((prev) => ({ ...prev, [targetStepKey]: formatted }));
        }
    };

    const renderPredictionList = (
        predictions: Array<{ description: string; place_id: string }>,
        type: 'origin' | 'destination'
    ) => {
        if (!predictions.length) return null;
        return (
            <div className="absolute z-10 mt-1 w-full rounded-lg border border-gray-200 bg-white shadow">
                {predictions.map((prediction) => (
                    <button
                        key={prediction.place_id}
                        type="button"
                        onClick={() => applyPredictionSelection(prediction, type)}
                        className="block w-full px-3 py-2 text-left text-sm hover:bg-gray-50"
                    >
                        {prediction.description}
                    </button>
                ))}
            </div>
        );
    };

    const renderAddressField = (
        type: 'origin' | 'destination',
        label: string,
        placeholder: string,
        stepKey?: string
    ) => {
        const value = type === 'origin' ? originAddress : destinationAddress;
        const predictions = type === 'origin' ? originPredictions : destinationPredictions;
        const inputId = `${type}-address`;

        return (
            <div className="space-y-2 relative w-full max-w-xl">
                <Label htmlFor={inputId}>{label}</Label>
                <Input
                    id={inputId}
                    value={value}
                    onChange={(e) => handleAddressInputChange(e.target.value, type, stepKey)}
                    onFocus={() => void ensurePlacesLoaded()}
                    placeholder={placeholder}
                    autoComplete="off"
                />
                {renderPredictionList(predictions, type)}
            </div>
        );
    };

    const handleOptionSelect = (optionValue: string) => {
        setSelectedOptions({ ...selectedOptions, [currentStepKey]: optionValue });
        setFormData({ ...formData, [currentStepKey]: optionValue });
    };

    const handleNext = () => {
        if (currentStepIndex < totalSteps - 1) {
            setCurrentStepIndex(currentStepIndex + 1);
        }
    };

    const handleBack = () => {
        if (currentStepIndex > 0) {
            setCurrentStepIndex(currentStepIndex - 1);
        }
    };

    const handleSubmit = async () => {
        const normalizeToString = (value: any) => {
            if (value === null || value === undefined) {
                return '';
            }
            if (typeof value === 'string') {
                return value.trim();
            }
            return String(value).trim();
        };

        const phoneValue = normalizeToString(formData['contact-phone'] ?? formData['phone']);
        const startAddressValue = normalizeToString(
            formData['origin-location'] ?? formData['origin'] ?? originAddress
        );
        const missingFields: string[] = [];

        const validPhone = isPhoneValueValid(phoneValue);
        if (!validPhone) {
            missingFields.push('phone number');
            setPhoneError('Phone must contain at least 10 digits');
        } else {
            setPhoneError(null);
        }
        if (!startAddressValue) {
            missingFields.push('starting address');
        }

        if (missingFields.length > 0) {
            setSubmitStatus({
                state: 'error',
                message: `Please provide your ${missingFields.join(' and ')} before submitting.`,
                gravityFormsSubmitted: false,
            });
            return;
        }

        if (onSubmit) {
            onSubmit(formData);
        }

        setSubmitStatus({ state: 'sending' });

        await ensureCsrfCookie();

        const csrf = (document.querySelector('meta[name=\"csrf-token\"]') as HTMLMetaElement | null)?.content;
        const xsrf = getCookieValue('XSRF-TOKEN');

        const headers: Record<string, string> = {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };
        if (csrf) headers['X-CSRF-TOKEN'] = csrf;
        if (xsrf) headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrf);

        // Use Ziggy route helper when available to hit the Laravel backend origin even in Vite/preview mode
        const quotesUrl =
            typeof window !== 'undefined' && (window as any).route
                ? (window as any).route('quotes.send')
                : '/quotes/send';

        try {
            const summary = computeCostSummary();
            const response = await fetch(quotesUrl, {
                method: 'POST',
                headers,
                credentials: 'include',
                body: JSON.stringify({
                    widget_key: config.widget_id,
                    data: formData,
                    summary,
                }),
            });

            if (!response.ok) {
                console.error('Quote email failed to send', await response.text());
                setSubmitStatus({
                    state: 'error',
                    message: 'Quote failed to send. Please try again.',
                    gravityFormsSubmitted: false,
                });
                return;
            }

            const result = await response.json().catch(() => null);
            if (result?.gravity_forms_submitted) {
                setSubmitStatus({
                    state: 'success',
                    message: 'Quote sent and synced to Gravity Forms.',
                    gravityFormsSubmitted: true,
                });
            } else {
                setSubmitStatus({
                    state: 'error',
                    message: result?.gravity_forms_error
                        ? `Email sent, Gravity Forms failed: ${result.gravity_forms_error}`
                        : 'Email sent, but Gravity Forms submission failed.',
                    gravityFormsSubmitted: false,
                });
                console.error('Gravity Forms submission failed', result);
            }
        } catch (error) {
            console.error('Quote email failed to send', error);
            setSubmitStatus({
                state: 'error',
                message: 'Quote failed to send. Please try again.',
                gravityFormsSubmitted: false,
            });
        }
    };

    const handleInputChange = (field: string, value: string, stepKey: string = currentStepKey) => {
        const nextFormData = { ...formData, [field]: value };

        // also mirror the value under the step key for address steps to simplify distance calculation
        if (stepKey === 'origin-location') {
            nextFormData['origin-location'] = value;
            setOriginAddress(value);
        }
        if (stepKey === 'target-location') {
            nextFormData['target-location'] = value;
            setDestinationAddress(value);
        }

        setFormData(nextFormData);
        setSelectedOptions({ ...selectedOptions, [stepKey]: value });

        if (/phone/i.test(field)) {
            setPhoneError(
                value && !isPhoneValueValid(value) ? 'Phone must contain at least 10 digits' : null
            );
        }
    };

    const renderIcon = (iconName?: string) => {
        if (!iconName) return null;
        const IconComponent = iconMap[iconName];
        if (!IconComponent) return null;
        return <IconComponent className="h-8 w-8" />;
    };

    const computeCostSummary = () => {
        const items: { label: string; amount: number; meta?: string }[] = [];
        let total = 0;
        const minimumJobPrice = Number(config.estimation_settings?.minimum_job_price ?? 0);

        const distanceKey = distanceStepKey || 'distance-calculation';
        const distanceData = formData[distanceKey];

        // include visible steps in order
        for (const stepKey of stepOrder) {
            const step = config.steps_data[stepKey];
            const selectedValue = selectedOptions[stepKey];
            if (!step || !selectedValue) continue;

            // Option-based costs
            const stepOptions = Array.isArray(step.options) ? step.options : [];
            const option = stepOptions.find(
                (opt) => opt.value === selectedValue || opt.id === selectedValue
            );
            const est = option?.estimation;
            const pricingValue = typeof est?.pricing_value === 'number' ? est.pricing_value : undefined;
            const basePrice = typeof est?.base_price === 'number' ? est.base_price : undefined;
            const amount = pricingValue ?? basePrice;

            if (amount != null && !Number.isNaN(amount)) {
                items.push({
                    label: option?.title || step.title || stepKey,
                    amount: Number(amount),
                    meta: option?.description,
                });
                total += Number(amount);
            }
        }

        // distance cost even if the step is hidden
        if (distanceData?.estimated_cost != null) {
            items.push({
                label: 'Travel distance',
                amount: Number(distanceData.estimated_cost) || 0,
                meta: distanceData.miles ? `${distanceData.miles.toFixed(1)} miles` : undefined,
            });
            total += Number(distanceData.estimated_cost) || 0;
        }

        const appliedMinimum = total < minimumJobPrice && minimumJobPrice > 0;
        const finalTotal = appliedMinimum ? minimumJobPrice : total;

        return {
            items,
            total: finalTotal,
            appliedMinimum,
            minimumJobPrice,
            subtotal: total,
        };
    };

    const renderOptions = () => {
        if (!currentStep) return null;
        const options = Array.isArray(currentStep.options) ? currentStep.options : [];

        if (options.length === 0) {
            // Provide sensible fallbacks for form-like steps even without options configured
            if (currentStepKey === 'contact-info') {
                    const contactFields = [
                        { id: 'contact-name', title: 'Full Name', type: 'text', description: 'Enter your full name' },
                        { id: 'contact-email', title: 'Email', type: 'email', description: 'you@example.com' },
                        { id: 'contact-phone', title: 'Phone', type: 'tel', description: '(555) 123-4567' },
                    ];

                return (
                    <div className="space-y-4 w-full max-w-md">
                        {contactFields.map((field) => (
                            <div key={field.id} className="space-y-2">
                                <Label htmlFor={field.id}>{field.title}</Label>
                                <Input
                                    id={field.id}
                                    type={
                                        field.type === 'email'
                                            ? 'email'
                                            : field.type === 'tel'
                                                ? 'tel'
                                                : 'text'
                                    }
                                    placeholder={field.description}
                                    value={formData[field.id] || ''}
                                    onChange={(e) => handleInputChange(field.id, e.target.value, currentStepKey)}
                                    className="w-full"
                                />
                                {field.type === 'tel' && phoneError && (
                                    <p className="text-xs text-red-600">
                                        {phoneError}
                                    </p>
                                )}
                            </div>
                        ))}
                    </div>
                );
            }

            if (currentStepKey === 'origin-location') {
                return renderAddressField(
                    'origin',
                    currentStep.title || 'Where are you moving from?',
                    'Street, City',
                    currentStepKey
                );
            }

            if (currentStepKey === 'target-location') {
                return renderAddressField(
                    'destination',
                    currentStep.title || 'Where are you moving to?',
                    'Street, City',
                    currentStepKey
                );
            }

            if (currentStep.layout?.type === 'form') {
                const fallbackField = {
                    id: `${currentStepKey}-field`,
                    title: currentStep.title || 'Details',
                    description: currentStep.subtitle || 'Enter details',
                    type: 'text',
                };

                return (
                    <div className="space-y-4 w-full max-w-md">
                        <div className="space-y-2">
                            <Label htmlFor={fallbackField.id}>{fallbackField.title}</Label>
                            <Input
                                id={fallbackField.id}
                                type={fallbackField.type === 'email' ? 'email' : 'text'}
                                placeholder={fallbackField.description}
                                value={formData[fallbackField.id] || ''}
                                onChange={(e) => handleInputChange(fallbackField.id, e.target.value)}
                                className="w-full"
                            />
                        </div>
                    </div>
                );
            }

            return null;
        }

        if (currentStep.layout?.type === 'route-calculation') {
            const distanceSettings = options.find((opt) => opt.type === 'distance_calculation');
            const costPerMile = Number(distanceSettings?.estimation?.cost_per_mile ?? 0);
            const minimumDistance = Number(distanceSettings?.estimation?.minimum_distance ?? 0);
            const currencySymbol = config.estimation_settings?.currency_symbol || '$';
            const billableHint =
                minimumDistance > 0
                    ? `Minimum billable distance: ${minimumDistance} mile${minimumDistance === 1 ? '' : 's'}`
                    : null;

            return (
                <div className="w-full max-w-xl space-y-4">
                    <div className="space-y-2 relative">
                        <Label htmlFor="origin-address">Starting address</Label>
                        <Input
                            id="origin-address"
                            value={originAddress}
                            onChange={(e) => handleAddressInputChange(e.target.value, 'origin')}
                            placeholder="123 Main St, City, State"
                            autoComplete="off"
                        />
                        {renderPredictionList(originPredictions, 'origin')}
                    </div>

                    <div className="space-y-2 relative">
                        <Label htmlFor="destination-address">Destination address</Label>
                        <Input
                            id="destination-address"
                            value={destinationAddress}
                            onChange={(e) => handleAddressInputChange(e.target.value, 'destination')}
                            placeholder="456 Oak Ave, City, State"
                            autoComplete="off"
                        />
                        {renderPredictionList(destinationPredictions, 'destination')}
                    </div>

                    <Button
                        onClick={handleDistanceCalculate}
                        disabled={
                            distanceStatus === 'loading' ||
                            !originAddress.trim() ||
                            !destinationAddress.trim()
                        }
                        className="flex items-center gap-2"
                        style={{ backgroundColor: primaryColor }}
                    >
                        {distanceStatus === 'loading' ? 'Calculating...' : 'Calculate distance'}
                        <MapPin className="h-4 w-4" />
                    </Button>

                    {placesError && (
                        <div className="text-sm text-red-600">{placesError}</div>
                    )}
                    {distanceError && (
                        <div className="text-sm text-red-600">{distanceError}</div>
                    )}

                    {distanceResult && (
                        <div className="p-4 border rounded-lg bg-gray-50 space-y-2">
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-gray-600">Distance</span>
                                <span className="font-semibold">
                                    {distanceResult.miles.toFixed(1)} miles
                                </span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-gray-600">Estimated cost</span>
                                <span className="font-semibold">
                                    {currencySymbol}
                                    {(distanceResult.estimatedCost ?? 0).toFixed(2)}
                                </span>
                            </div>
                        </div>
                    )}

                    <div className="text-xs text-gray-500 space-y-1">
                        <div>Rate: {currencySymbol}{costPerMile.toFixed(2)} per mile</div>
                        {billableHint && <div>{billableHint}</div>}
                    </div>
                </div>
            );
        }

        // Check if this is a contact info step
        if (currentStepKey === 'contact-info' || currentStep.layout?.type === 'form') {
            const formFields =
                options.length > 0
                    ? options
                    : [
                          {
                              id: `${currentStepKey}-field`,
                              title: currentStep.title || 'Details',
                              description: currentStep.subtitle || 'Enter details',
                              type: 'text',
                          },
                      ];

            return (
                <div className="space-y-4 w-full max-w-md">
                    {formFields.map((option) => (
                        <div key={option.id} className="space-y-2">
                            <Label htmlFor={option.id}>{option.title}</Label>
                            <Input
                                id={option.id}
                                type={option.type === 'email' ? 'email' : 'text'}
                                placeholder={option.description}
                                value={formData[option.id] || ''}
                                onChange={(e) => handleInputChange(option.id, e.target.value)}
                                className="w-full"
                            />
                        </div>
                    ))}
                </div>
            );
        }

        // Regular option buttons
        const gridCols = currentStep.layout?.columns || 1;
        const gridClass = gridCols === 2 ? 'grid-cols-2' : 'grid-cols-1';

        return (
            <div className={`grid ${gridClass} gap-4 w-full max-w-2xl`}>
                {options.map((option) => (
                    <motion.button
                        key={option.id}
                        onClick={() => handleOptionSelect(option.value)}
                        className={`
                            relative p-6 rounded-xl border-2 transition-all text-left
                            ${selectedOptions[currentStepKey] === option.value
                                ? 'border-current bg-current/10'
                                : 'border-gray-200 hover:border-current hover:bg-gray-50'
                            }
                        `}
                        style={{
                            borderColor:
                                selectedOptions[currentStepKey] === option.value
                                    ? primaryColor
                                    : undefined,
                            color:
                                selectedOptions[currentStepKey] === option.value
                                    ? primaryColor
                                    : undefined,
                        }}
                        whileHover={{ scale: 1.02 }}
                        whileTap={{ scale: 0.98 }}
                    >
                        <div className="flex items-start gap-4">
                            {option.icon && (
                                <div
                                    className="flex-shrink-0 rounded-lg p-3"
                                    style={{
                                        backgroundColor:
                                            selectedOptions[currentStepKey] === option.value
                                                ? `${primaryColor}20`
                                                : '#f3f4f6',
                                    }}
                                >
                                    {renderIcon(option.icon)}
                                </div>
                            )}
                            <div className="flex-1">
                                <div className="font-semibold text-lg mb-1">{option.title}</div>
                                {option.description && (
                                    <div className="text-sm text-gray-600">{option.description}</div>
                                )}
                                {option.estimation?.base_price && (
                                    <div className="mt-2 text-sm font-medium">
                                        Starting at {config.estimation_settings?.currency_symbol || '$'}
                                        {option.estimation.base_price}
                                    </div>
                                )}
                            </div>
                            {selectedOptions[currentStepKey] === option.value && (
                                <motion.div
                                    initial={{ scale: 0 }}
                                    animate={{ scale: 1 }}
                                    className="absolute top-3 right-3"
                                >
                                    <div
                                        className="rounded-full p-1"
                                        style={{ backgroundColor: primaryColor }}
                                    >
                                        <Check className="h-4 w-4 text-white" />
                                    </div>
                                </motion.div>
                            )}
                        </div>
                    </motion.button>
                ))}
            </div>
        );
    };

    const handleDistanceCalculate = async (origin?: string, destination?: string) => {
        const step = distanceStepKey ? config.steps_data[distanceStepKey] : null;
        const options = step && Array.isArray(step.options) ? step.options : [];
        const distanceSettings = options.find((opt) => opt.type === 'distance_calculation');
        const costPerMile = Number(distanceSettings?.estimation?.cost_per_mile ?? 0);
        const minimumDistance = Number(distanceSettings?.estimation?.minimum_distance ?? 0);

        setDistanceStatus('loading');
        setDistanceError(null);

        try {
            const start = (origin ?? originAddress).trim();
            const end = (destination ?? destinationAddress).trim();
            const result = await geocodeDistance(start, end);
            const miles = result.miles;
            const billableMiles = Math.max(miles, minimumDistance);
            const estimatedCost = Number((billableMiles * costPerMile).toFixed(2));
            const resolvedOrigin = result.origin?.formatted_address || start;
            const resolvedDestination = result.destination?.formatted_address || end;
            const fromZipCode =
                result.origin?.postal_code || extractZipFromText(resolvedOrigin) || extractZipFromText(start);
            const toZipCode =
                result.destination?.postal_code || extractZipFromText(resolvedDestination) || extractZipFromText(end);

            setDistanceResult({ miles, estimatedCost });
            const distanceKey = distanceStepKey || 'distance-calculation';

            setFormData((prev) => {
                const next = {
                    ...prev,
                    [distanceKey]: {
                        origin: resolvedOrigin,
                        destination: resolvedDestination,
                        miles,
                        estimated_cost: estimatedCost,
                        cost_per_mile: costPerMile,
                    },
                    'origin-location': resolvedOrigin,
                    'origin-location-field': resolvedOrigin,
                    origin: resolvedOrigin,
                    'target-location': resolvedDestination,
                    'target-location-field': resolvedDestination,
                    destination: resolvedDestination,
                };

                const zipFields: Record<string, string | null> = {
                    fromZip: fromZipCode,
                    'from-zip': fromZipCode,
                    'origin-zip': fromZipCode,
                    toZip: toZipCode,
                    'target-zip': toZipCode,
                    'destination-zip': toZipCode,
                };

                Object.entries(zipFields).forEach(([key, value]) => {
                    if (value) {
                        next[key] = value;
                    }
                });

                return next;
            });
            setSelectedOptions((prev) => ({ ...prev, [distanceKey]: 'calculated' }));
            setDistanceStatus('success');
            setLastDistanceInputs({ origin: start, destination: end });
        } catch (error: any) {
            setDistanceStatus('error');
            setDistanceError(error?.message || 'Unable to calculate distance right now.');
        }
    };

    useEffect(() => {
        if (currentStep?.layout?.type === 'route-calculation') {
            void ensurePlacesLoaded();
        }
    }, [currentStep?.layout?.type, currentStepKey]);

    useEffect(() => {
        // keep local address state in sync with form data if it already exists
        if (formData['origin-location'] && formData['origin-location'] !== originAddress) {
            setOriginAddress(formData['origin-location']);
        }
        if (formData['target-location'] && formData['target-location'] !== destinationAddress) {
            setDestinationAddress(formData['target-location']);
        }
    }, [formData, originAddress, destinationAddress]);

    if (!currentStep) {
        return (
            <div className="min-h-screen bg-white text-gray-950 flex items-center justify-center p-8">
                <Card className="w-full max-w-xl shadow-2xl bg-white border border-gray-200 text-gray-950 p-6">
                    <div className="text-center space-y-2">
                        <h2 className="text-xl font-semibold text-gray-950">No steps configured</h2>
                        <p className="text-sm text-gray-600">Please add steps to the widget to begin.</p>
                    </div>
                </Card>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-white text-gray-950 flex items-center justify-center p-4">
            <Card className="w-full max-w-4xl shadow-2xl bg-white border border-gray-200 text-gray-950">
                {/* Header */}
                <div
                    className="px-8 py-6 border-b"
                    style={{ borderColor: `${primaryColor}30` }}
                >
                    <div className="flex items-center justify-between">
                        <div>
                            <h2 className="text-2xl font-bold text-gray-950">
                                {config.branding?.company_name || 'Widget Preview'}
                            </h2>
                            <p className="text-sm text-gray-600 mt-1">
                                Step {currentStepIndex + 1} of {totalSteps}
                            </p>
                        </div>
                        {config.branding?.logo_url && (
                            <img
                                src={config.branding.logo_url}
                                alt="Company Logo"
                                className="h-12"
                            />
                        )}
                    </div>

                    {/* Progress bar */}
                    <div className="mt-4 h-2 bg-gray-200 rounded-full overflow-hidden">
                        <motion.div
                            className="h-full rounded-full"
                            style={{ backgroundColor: primaryColor }}
                            initial={{ width: 0 }}
                            animate={{
                                width: totalSteps > 0 ? `${((currentStepIndex + 1) / totalSteps) * 100}%` : '0%',
                            }}
                            transition={{ duration: 0.3 }}
                        />
                    </div>
                </div>

                {/* Content */}
                <div className="px-8 py-12">
                    {submitStatus.state !== 'idle' && (
                        <div
                            className={`mb-6 text-sm ${
                                submitStatus.state === 'success'
                                    ? 'text-green-700 bg-green-50 border border-green-200'
                                    : 'text-red-700 bg-red-50 border border-red-200'
                            } px-4 py-3 rounded-lg`}
                        >
                            {submitStatus.message}
                        </div>
                    )}

                    <AnimatePresence mode="wait">
                        <motion.div
                            key={currentStepKey}
                            initial={{ opacity: 0, x: 20 }}
                            animate={{ opacity: 1, x: 0 }}
                            exit={{ opacity: 0, x: -20 }}
                            transition={{ duration: 0.3 }}
                            className="flex flex-col items-center"
                        >
                            {/* Step title and subtitle */}
                            <div className="text-center mb-8 max-w-2xl">
                                <h3 className="text-3xl font-bold text-gray-950 mb-2">
                                    {currentStep.title}
                                </h3>
                                {currentStep.subtitle && (
                                    <p className="text-lg text-gray-600">{currentStep.subtitle}</p>
                                )}
                                {currentStep.prompt?.message && (
                                    <p className="text-md text-gray-500 mt-2">
                                        {currentStep.prompt.message}
                                    </p>
                                )}
                            </div>

                            {/* Step options */}
                            {renderOptions()}

                            {currentStepKey === 'review-quote' && (
                                <div className="w-full max-w-2xl mt-6">
                                    <Card className="border border-gray-200 bg-white text-gray-950">
                                        <div className="p-6 space-y-4">
                                            <div className="flex items-center justify-between">
                                                <h4 className="text-xl font-semibold text-gray-950">
                                                    Review your moving quote
                                                </h4>
                                                <span className="text-sm text-gray-600">
                                                    Estimated costs based on your selections
                                                </span>
                                            </div>

                                            {(() => {
                                                const summary = computeCostSummary();
                                                const currency = config.estimation_settings?.currency_symbol || '$';

                                                if (!summary.items.length) {
                                                    return (
                                                        <div className="text-sm text-gray-600">
                                                            No billable items yet. Complete the steps to see your estimated cost.
                                                        </div>
                                                    );
                                                }

                                                return (
                                                    <div className="space-y-3">
                                                        <div className="divide-y divide-gray-200 border border-gray-200 rounded-lg">
                                                            {summary.items.map((item, idx) => (
                                                                <div key={idx} className="flex items-start justify-between px-4 py-3">
                                                                    <div>
                                                                        <div className="font-medium text-gray-900">{item.label}</div>
                                                                        {item.meta && (
                                                                            <div className="text-xs text-gray-500">{item.meta}</div>
                                                                        )}
                                                                    </div>
                                                                    <div className="font-semibold text-gray-900">
                                                                        {currency}{item.amount.toFixed(2)}
                                                                    </div>
                                                                </div>
                                                            ))}
                                                        </div>

                                                        <div className="flex items-center justify-between pt-2">
                                                            <div className="text-sm text-gray-600">Subtotal</div>
                                                            <div className="font-semibold text-gray-900">
                                                                {currency}{summary.subtotal.toFixed(2)}
                                                            </div>
                                                        </div>

                                                        {summary.appliedMinimum && (
                                                            <div className="flex items-center justify-between text-sm text-gray-700">
                                                                <span>Minimum job price</span>
                                                                <span className="font-semibold">
                                                                    {currency}{summary.minimumJobPrice.toFixed(2)}
                                                                </span>
                                                            </div>
                                                        )}

                                                        <div className="flex items-center justify-between pt-2 border-t border-gray-200">
                                                            <div className="text-base font-semibold text-gray-950">Estimated total</div>
                                                            <div className="text-base font-semibold text-gray-950">
                                                                {currency}{summary.total.toFixed(2)}
                                                            </div>
                                                        </div>
                                                    </div>
                                                );
                                            })()}
                                        </div>
                                    </Card>
                                </div>
                            )}
                        </motion.div>
                    </AnimatePresence>
                </div>

                {/* Footer with navigation */}
                <div className="px-8 py-6 border-t bg-gray-50 flex items-center justify-between">
                    <Button
                        variant="outline"
                        onClick={handleBack}
                        disabled={currentStepIndex === 0}
                        className="flex items-center gap-2 text-white"
                        style={{ color: '#ffffff', borderColor: primaryColor }}
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Back
                    </Button>

                    {isLastStep ? (
                        <Button
                            onClick={handleSubmit}
                            className="flex items-center gap-2"
                            style={{ backgroundColor: primaryColor }}
                        >
                            {currentStep.buttons?.primary?.text || 'Submit'}
                            <Check className="h-4 w-4" />
                        </Button>
                    ) : (
                        <Button
                            onClick={handleNext}
                            className="flex items-center gap-2"
                            style={{ backgroundColor: primaryColor }}
                            disabled={!isStepOptional(currentStep) && !isStepSatisfied()}
                        >
                            {currentStep.buttons?.primary?.text || 'Continue'}
                            <ArrowRight className="h-4 w-4" />
                        </Button>
                    )}
                </div>
            </Card>
        </div>
    );
}
