export const LIVENESS_PORTRAIT_MESSAGE =
    'Rotate your device upright to continue face verification. If the screen does not rotate, turn off Rotation Lock. Keep it upright until verification finishes.';

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
