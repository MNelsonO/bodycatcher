import { usePage } from '@inertiajs/react';

type Props = {
    className?: string;
    markClassName?: string;
    showWordmark?: boolean;
    inverted?: boolean;
};

export default function BrandLogo({
    className = '',
    markClassName = 'h-12 w-12',
    showWordmark = true,
    inverted = false,
}: Props) {
    const { brand, name } = usePage().props;
    const label = brand?.name || name || 'Kemet Tech';
    const src = brand?.logo_url || '/brand/kemet-mark.svg';

    return (
        <span className={`inline-flex items-center gap-3 ${className}`}>
            <img
                src={src}
                alt={label}
                className={`${markClassName} rounded-xl object-contain ${
                    inverted ? 'brightness-0 invert' : ''
                }`}
            />
            {showWordmark ? (
                <span
                    className={`text-left leading-tight ${
                        inverted ? 'text-white' : 'text-primary'
                    }`}
                >
                    <span className="block text-base font-semibold tracking-tight">
                        {label}
                    </span>
                    {brand?.tagline ? (
                        <span
                            className={`block text-xs font-medium ${
                                inverted ? 'text-white/80' : 'text-muted-foreground'
                            }`}
                        >
                            {brand.tagline}
                        </span>
                    ) : null}
                </span>
            ) : null}
        </span>
    );
}
