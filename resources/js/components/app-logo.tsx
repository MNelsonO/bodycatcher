import { usePage } from '@inertiajs/react';

export default function AppLogo() {
    const { brand, name } = usePage().props;
    const label = brand?.name || name;
    const src = brand?.logo_url || '/brand/kemet-mark.svg';

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-md">
                <img src={src} alt={label} className="size-8 object-contain" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    {label}
                </span>
            </div>
        </>
    );
}
