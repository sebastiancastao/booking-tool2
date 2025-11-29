import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { ArrowLeft, Eye, Code, Smartphone, Monitor, ExternalLink } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { WidgetRenderer } from '@/components/widget/widget-renderer';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

interface WidgetPreviewProps {
    widget: {
        id: number;
        name: string;
        widget_key: string;
        status: string;
    };
    config: any;
}

export default function WidgetPreview({ widget, config }: WidgetPreviewProps) {
    const [viewMode, setViewMode] = useState<'desktop' | 'mobile'>('desktop');
    const [showCode, setShowCode] = useState(false);
    const [iframeEmbedUrl, setIframeEmbedUrl] = useState('');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: widget.name, href: `/widgets/${widget.id}/edit` },
        { title: 'Preview', href: `/widgets/${widget.id}/preview` },
    ];

    const handleSubmit = (data: Record<string, any>) => {
        console.log('Widget submitted with data:', data);
        alert('Widget submission captured!\n\nThis is a preview. In production, this data would be sent to your leads dashboard.\n\nForm Data: ' + JSON.stringify(data, null, 2));
    };

    const embedCode = `<script>
(function() {
    var script = document.createElement('script');
    script.src = '${window.location.origin}/widget.js';
    script.setAttribute('data-widget-key', '${widget.widget_key}');
    script.setAttribute('data-domain', window.location.hostname);
    document.head.appendChild(script);
})();
</script>`;

    const copyEmbedCode = () => {
        navigator.clipboard.writeText(embedCode);
        alert('Embed code copied to clipboard!');
    };

    useEffect(() => {
        if (typeof window !== 'undefined') {
            setIframeEmbedUrl(`${window.location.origin}/widgets/${widget.widget_key}/embed`);
        }
    }, [widget.widget_key]);

    const iframeEmbedCode = iframeEmbedUrl
        ? `<iframe src="${iframeEmbedUrl}" width="420" height="720" frameborder="0" sandbox="allow-forms allow-scripts allow-same-origin"></iframe>`
        : '';

    const copyIframeEmbedCode = () => {
        if (!iframeEmbedCode) return;
        navigator.clipboard.writeText(iframeEmbedCode);
        alert('Iframe embed code copied to clipboard!');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Preview: ${widget.name}`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => router.visit(`/widgets/${widget.id}/edit`)}
                        >
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to Editor
                        </Button>
                        <div>
                            <h1 className="text-3xl font-bold text-gray-900">{widget.name}</h1>
                            <p className="text-sm text-gray-600 mt-1">
                                Interactive preview of your widget
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        {/* View mode toggle */}
                        <div className="flex items-center gap-2 bg-gray-100 p-1 rounded-lg">
                            <button
                                onClick={() => setViewMode('desktop')}
                                className={`px-3 py-2 rounded-md transition-all flex items-center gap-2 ${
                                    viewMode === 'desktop'
                                        ? 'bg-white shadow-sm text-gray-900'
                                        : 'text-gray-600 hover:text-gray-900'
                                }`}
                            >
                                <Monitor className="h-4 w-4" />
                                Desktop
                            </button>
                            <button
                                onClick={() => setViewMode('mobile')}
                                className={`px-3 py-2 rounded-md transition-all flex items-center gap-2 ${
                                    viewMode === 'mobile'
                                        ? 'bg-white shadow-sm text-gray-900'
                                        : 'text-gray-600 hover:text-gray-900'
                                }`}
                            >
                                <Smartphone className="h-4 w-4" />
                                Mobile
                            </button>
                        </div>

                        <Button
                            variant={showCode ? 'default' : 'outline'}
                            onClick={() => setShowCode(!showCode)}
                        >
                            <Code className="mr-2 h-4 w-4" />
                            {showCode ? 'Hide Code' : 'Show Embed Code'}
                        </Button>
                    </div>
                </div>

                {/* Status banner */}
                {widget.status !== 'published' && (
                    <Card className="bg-yellow-50 border-yellow-200 p-4">
                        <div className="flex items-start gap-3">
                            <Eye className="h-5 w-5 text-yellow-600 mt-0.5" />
                            <div>
                                <h3 className="font-semibold text-yellow-900">
                                    Preview Mode - Widget Not Published
                                </h3>
                                <p className="text-sm text-yellow-800 mt-1">
                                    This widget is currently in <strong>{widget.status}</strong> mode.
                                    Publish it from the editor to make it available on your website.
                                </p>
                            </div>
                        </div>
                    </Card>
                )}

                {/* Embed code panel */}
                {showCode && (
                    <Card className="p-6 bg-gray-50">
                        <div className="space-y-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <h3 className="font-semibold text-lg text-gray-900">
                                        Embed Code
                                    </h3>
                                    <p className="text-sm text-gray-600 mt-1">
                                        Copy and paste this code into your website's HTML
                                    </p>
                                </div>
                                <Button onClick={copyEmbedCode} size="sm">
                                    Copy Code
                                </Button>
                            </div>
                            <pre className="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto text-sm">
                                <code>{embedCode}</code>
                            </pre>
                            <div className="flex items-start gap-2 text-sm text-gray-600">
                                <ExternalLink className="h-4 w-4 mt-0.5" />
                                <p>
                                    Place this code in the <code className="bg-gray-200 px-1 rounded">&lt;head&gt;</code> or
                                    before the closing <code className="bg-gray-200 px-1 rounded">&lt;/body&gt;</code> tag
                                    of your website.
                                </p>
                            </div>
                        </div>
                    </Card>
                )}

                {/* Widget preview */}
                <Card className="p-6">
                    <div className="flex flex-col items-center">
                        <div className="mb-4 text-center">
                            <h2 className="text-lg font-semibold text-gray-900">
                                Live Widget Preview
                            </h2>
                            <p className="text-sm text-gray-600 mt-1">
                                Interact with your widget exactly as your customers will see it
                            </p>
                        </div>

                        {/* Preview container with responsive sizing */}
                        <div
                            className={`transition-all duration-300 ${
                                viewMode === 'mobile' ? 'max-w-md' : 'w-full'
                            }`}
                        >
                            <div className="rounded-xl overflow-hidden border-4 border-gray-200 shadow-lg">
                                <WidgetRenderer config={config} onSubmit={handleSubmit} />
                            </div>
                        </div>

                        {/* Widget info */}
                        <div className="mt-6 text-center text-sm text-gray-500">
                            <p>Widget ID: {widget.widget_key}</p>
                            <p className="mt-1">
                                {config.step_order?.length || 0} steps configured
                            </p>
                        </div>
                    </div>
                </Card>

            <Card className="p-6 bg-gray-50 border border-gray-200">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h3 className="font-semibold text-lg text-gray-900">Iframe embed</h3>
                        <p className="text-sm text-gray-600 mt-1">
                            Drop this iframe into any page to load your widget preview while keeping the session and CSRF cookies intact.
                        </p>
                    </div>
                    <Button onClick={copyIframeEmbedCode} size="sm" disabled={!iframeEmbedCode}>
                        Copy iframe
                    </Button>
                </div>
                <div className="mt-4">
                    <pre className="bg-gray-900 text-gray-100 text-sm rounded-lg overflow-x-auto p-4">
                        <code>{iframeEmbedCode || 'Widget embed URL will appear once the preview loads.'}</code>
                    </pre>
                    <p className="text-xs text-gray-500 mt-2">
                        Use `sandbox="allow-forms allow-scripts allow-same-origin"` to keep the iframe isolated.
                    </p>
                </div>
            </Card>

                {/* Configuration summary */}
                <Card className="p-6">
                    <h3 className="font-semibold text-lg text-gray-900 mb-4">
                        Widget Configuration
                    </h3>
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div className="space-y-1">
                            <p className="text-sm text-gray-600">Status</p>
                            <p className="font-medium capitalize">{widget.status}</p>
                        </div>
                        <div className="space-y-1">
                            <p className="text-sm text-gray-600">Steps</p>
                            <p className="font-medium">{config.step_order?.length || 0} steps</p>
                        </div>
                        <div className="space-y-1">
                            <p className="text-sm text-gray-600">Primary Color</p>
                            <div className="flex items-center gap-2">
                                <div
                                    className="w-6 h-6 rounded border border-gray-300"
                                    style={{
                                        backgroundColor: config.branding?.primary_color || '#F4C443',
                                    }}
                                />
                                <p className="font-medium text-sm">
                                    {config.branding?.primary_color || '#F4C443'}
                                </p>
                            </div>
                        </div>
                        <div className="space-y-1">
                            <p className="text-sm text-gray-600">Company</p>
                            <p className="font-medium">
                                {config.branding?.company_name || 'Not set'}
                            </p>
                        </div>
                    </div>
                </Card>

                {/* Help text */}
                <Card className="p-6 bg-blue-50 border-blue-200">
                    <div className="flex items-start gap-3">
                        <Eye className="h-5 w-5 text-blue-600 mt-0.5" />
                        <div className="flex-1">
                            <h3 className="font-semibold text-blue-900">Testing Your Widget</h3>
                            <ul className="text-sm text-blue-800 mt-2 space-y-1 list-disc list-inside">
                                <li>Click through each step to see how customers will experience your widget</li>
                                <li>Try submitting the form - you'll see what data is captured</li>
                                <li>Test on both desktop and mobile views</li>
                                <li>Make changes in the editor and refresh to see updates</li>
                            </ul>
                        </div>
                    </div>
                </Card>
            </div>
        </AppLayout>
    );
}
