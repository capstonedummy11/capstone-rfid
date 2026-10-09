// AWS onError returns { state, error }, rather than an Error directly.
import { LIVENESS_PORTRAIT_MESSAGE } from './faceLivenessOrientation';

export function livenessErrorMessage(
    value: unknown,
    diagnosticMode = true,
): string {
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
    const message =
        state === 'MOBILE_LANDSCAPE_ERROR'
            ? LIVENESS_PORTRAIT_MESSAGE
            : typeof error?.message === 'string' && error.message
              ? error.message
              : typeof underlying === 'string' && underlying
                ? underlying
                : 'The liveness camera could not complete verification.';
    const name = typeof error?.name === 'string' ? error.name : '';
    const summary = [state, name, message].filter(Boolean).join(': ');

    if (!diagnosticMode) return summary;

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

    return details ? `${summary}\n${details}` : summary;
}
