export const getCsrfTokenFromCookie = (): string | null => {
    // Try XSRF-TOKEN cookie first (Laravel's default)
    let cookie = document.cookie.split('; ').find((row) => row.startsWith('XSRF-TOKEN='));

    if (!cookie) {
        // Fallback to X-XSRF-TOKEN
        cookie = document.cookie.split('; ').find((row) => row.startsWith('X-XSRF-TOKEN='));
    }

    if (!cookie) {
        return null;
    }

    const value = cookie.split('=')[1];

    if (!value) {
        return null;
    }

    try {
        return decodeURIComponent(value);
    } catch {
        return null;
    }
};
