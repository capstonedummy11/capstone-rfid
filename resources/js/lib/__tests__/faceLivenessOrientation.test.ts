import { afterEach, describe, expect, it, vi } from 'vitest';
import { livenessOrientationMessage } from '../faceLivenessOrientation';

afterEach(() => vi.unstubAllGlobals());

function device(userAgent: string, touchPoints: number, landscape: boolean) {
    vi.stubGlobal('navigator', { userAgent, maxTouchPoints: touchPoints });
    vi.stubGlobal(
        'matchMedia',
        vi.fn(() => ({ matches: landscape })),
    );
}

describe('liveness orientation on iPad Safari', () => {
    it('recognizes an iPad using its desktop Macintosh user agent', () => {
        device(
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 Version/27.0 Safari/605.1.15',
            5,
            true,
        );
        expect(livenessOrientationMessage()).toContain(
            'Rotate your device upright',
        );
    });

    it('allows portrait on the same iPad', () => {
        device('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', 5, false);
        expect(livenessOrientationMessage()).toBeNull();
    });

    it('allows landscape on desktop Macs', () => {
        device('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', 0, true);
        expect(livenessOrientationMessage()).toBeNull();
    });
});
