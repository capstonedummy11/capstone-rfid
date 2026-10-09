export const LIVENESS_PORTRAIT_MESSAGE =
    'Live face verification requires portrait orientation on iPad and other mobile devices. Rotate your device upright, turn off Rotation Lock if needed, and tap Verify Face again. Keep it upright until verification finishes.';

export function livenessOrientationMessage(): string | null {
    // iPad Safari's desktop mode identifies itself as Macintosh.
    const mobile =
        /Android|iPhone|iPad/i.test(navigator.userAgent) ||
        (/Macintosh/i.test(navigator.userAgent) &&
            navigator.maxTouchPoints > 1);
    const landscape =
        typeof window.matchMedia === 'function'
            ? window.matchMedia('(orientation: landscape)').matches
            : window.innerWidth > window.innerHeight;

    return mobile && landscape ? LIVENESS_PORTRAIT_MESSAGE : null;
}
