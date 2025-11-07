interface SiteAvatarProps {
    domain: string;
    size?: 'sm' | 'md' | 'lg';
}

const AVATAR_COLORS = ['bg-blue-500', 'bg-purple-500', 'bg-pink-500', 'bg-green-500', 'bg-yellow-500', 'bg-red-500', 'bg-indigo-500'];

const getSiteInitial = (domain: string): string => {
    return domain.charAt(0).toUpperCase();
};

const getSiteColor = (domain: string): string => {
    const index = domain.charCodeAt(0) % AVATAR_COLORS.length;
    return AVATAR_COLORS[index];
};

const SIZE_CLASSES = {
    sm: 'size-8 text-sm',
    md: 'size-10 text-lg',
    lg: 'size-12 text-xl',
};

export function SiteAvatar({ domain, size = 'md' }: SiteAvatarProps) {
    const initial = getSiteInitial(domain);
    const colorClass = getSiteColor(domain);
    const sizeClass = SIZE_CLASSES[size];

    return (
        <div className={`flex shrink-0 items-center justify-center rounded-lg ${colorClass} ${sizeClass} font-semibold text-white`}>
            <span>{initial}</span>
        </div>
    );
}
