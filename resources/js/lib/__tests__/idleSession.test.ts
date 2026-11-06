import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    broadcastIdleLogout,
    idleActivityStorageKey,
    IDLE_LOGOUT_STORAGE_KEY,
} from '@/lib/idleSession';

describe('idle session storage', () => {
    beforeEach(() => {
        localStorage.clear();
        vi.restoreAllMocks();
    });

    it('uses a different activity clock for every authenticated login', () => {
        const firstAccountKey = idleActivityStorageKey('login-uuid-one');
        const secondAccountKey = idleActivityStorageKey('login-uuid-two');

        localStorage.setItem(firstAccountKey, '1000');

        expect(secondAccountKey).not.toBe(firstAccountKey);
        expect(localStorage.getItem(secondAccountKey)).toBeNull();
    });

    it('publishes logout for the other open tabs', () => {
        vi.spyOn(Date, 'now').mockReturnValue(123456);

        broadcastIdleLogout();

        expect(localStorage.getItem(IDLE_LOGOUT_STORAGE_KEY)).toBe('123456');
    });
});
