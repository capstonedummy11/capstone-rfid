export const IDLE_LOGOUT_STORAGE_KEY = 'capstone-rfid:idle-logout';

export function idleActivityStorageKey(sessionKey: string | number): string {
    return `capstone-rfid:idle-activity:${String(sessionKey)}`;
}

export function broadcastIdleLogout(): void {
    localStorage.setItem(IDLE_LOGOUT_STORAGE_KEY, String(Date.now()));
}

export function xsrfToken(): string {
    const raw = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    return raw ? decodeURIComponent(raw) : '';
}

export function redirectOnExpiredSession(
    response: Response,
    loginUrl: string,
): boolean {
    if (![401, 419].includes(response.status)) return false;

    broadcastIdleLogout();
    window.location.replace(loginUrl);

    return true;
}
