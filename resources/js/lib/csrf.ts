export const getCsrfTokenFromCookie = (): string | null => {
    const cookie = document.cookie.split('; ').find((row) => row.startsWith('XSRF-TOKEN='));

    if (!cookie) {
        return null;
    }

    const value = cookie.split('=')[1];

    try {
        return decodeURIComponent(value);
    } catch {
        return null;
    }
};
