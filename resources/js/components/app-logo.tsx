import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <AppLogoIcon className="size-8 rounded-md" />
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate font-heading leading-tight font-extrabold tracking-wide">
                    MEBRANDING
                </span>
            </div>
        </>
    );
}
