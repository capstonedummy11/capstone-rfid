// AWS onError returns { state, error }, rather than an Error directly.
import { LIVENESS_PORTRAIT_MESSAGE } from './faceLivenessOrientation';

export function livenessErrorMessage(value: unknown): string {
    const envelope =
        value && typeof value === 'object'
            ? (value as Record<string, unknown>)
            : null;
    const underlying = envelope?.error ?? value;
    const error =
        underlying && typeof underlying === 'object'
            ? (underlying as Record<string, unknown>)
            : null;
    const state = typeof envelope?.state === 'string' ? envelope.state : '';
    const name = typeof error?.name === 'string' ? error.name : '';
    if (state === 'MOBILE_LANDSCAPE_ERROR') return LIVENESS_PORTRAIT_MESSAGE;
    if (name === 'NotAllowedError') {
        return 'Camera access is blocked. Allow camera access in your browser settings, then try again.';
    }
    if (name === 'NotReadableError') {
        return 'The camera is in use. Close other apps or tabs using it, then try again.';
    }
    const messages: Record<string, string> = {
        CAMERA_ACCESS_ERROR:
            'Unable to access the camera. Check camera permissions, then try again.',
        DEFAULT_CAMERA_NOT_FOUND_ERROR:
            'No camera was found. Connect a camera or use another device.',
        CAMERA_FRAMERATE_ERROR:
            'The camera cannot capture video smoothly enough. Close other apps and try again, or use another device.',
        FACE_DISTANCE_ERROR: 'Move your face into the guide and try again.',
        MULTIPLE_FACES_ERROR:
            'Only one person should be in view. Ask others to step away, then try again.',
        TIMEOUT:
            'Verification timed out. Keep your face in the guide and try again.',
        FRESHNESS_TIMEOUT:
            'Verification could not finish. Use even lighting, keep your face in the guide, and try again.',
        CONNECTION_TIMEOUT:
            'The connection timed out. Check your internet connection and try again.',
        SERVER_ERROR:
            'Face verification is temporarily unavailable. Please try again shortly.',
    };
    return (
        messages[state] ??
        'Face verification could not be completed. Please try again.'
    );
}

export function reportLivenessError(value: unknown): string {
    console.warn('Face verification failed', livenessErrorDetails(value));
    return livenessErrorMessage(value);
}

export function livenessErrorDetails(value: unknown): string {
    const seen = new WeakSet<object>();
    const details = JSON.stringify(
        value,
        (key, item: unknown) => {
            // Never display credential material attached to a provider error.
            if (
                /credential|authorization|cookie|secret|password|token|access.?key|identity.?id/i.test(
                    key,
                )
            ) {
                return '[redacted]';
            }
            if (item && typeof item === 'object') {
                if (seen.has(item)) return '[circular]';
                seen.add(item);
                if (
                    item instanceof Error ||
                    ('name' in item && 'message' in item)
                ) {
                    return Object.fromEntries(
                        Array.from(
                            new Set([
                                'name',
                                'message',
                                'stack',
                                ...Object.getOwnPropertyNames(item),
                            ]),
                        ).map((property) => [
                            property,
                            (item as unknown as Record<string, unknown>)[
                                property
                            ],
                        ]),
                    );
                }
            }
            return typeof item === 'bigint' ? String(item) : item;
        },
        2,
    );

    return details ?? 'No diagnostic details available.';
}
