import { Head } from '@inertiajs/react';
import { WidgetRenderer } from '@/components/widget/widget-renderer';

interface EmbeddedWidgetProps {
    widget: {
        id: number;
        name: string;
        widget_key: string;
    };
    config: any;
}

export default function WidgetEmbed({ widget, config }: EmbeddedWidgetProps) {
    return (
        <>
            <Head title={`${widget.name || 'Widget'} Embed`} />
            <div className="min-h-screen bg-white text-gray-950">
                <WidgetRenderer config={config} />
            </div>
        </>
    );
}
