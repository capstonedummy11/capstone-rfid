import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    livenessErrorDetails,
    livenessErrorMessage,
    reportLivenessError,
} from '../faceLivenessErrors';

afterEach(() => vi.restoreAllMocks());

describe('AWS liveness error diagnostics', () => {
    it('explains landscape failures even when AWS does not include an underlying error', () => {
        const message = livenessErrorMessage({
            state: 'MOBILE_LANDSCAPE_ERROR',
        });
        expect(message).toContain('Rotate your device upright');
        expect(message).toContain('Rotation Lock');
        expect(message).not.toContain(
            'The liveness camera could not complete verification',
        );
        expect(message).not.toContain('MOBILE_LANDSCAPE_ERROR');
        expect(message).not.toContain('{');
    });
    it('reads the wrapped Safari camera exception instead of the generic fallback', () => {
        const error = new DOMException(
            'The camera is already in use.',
            'NotReadableError',
        );
        const message = livenessErrorMessage({
            state: 'CAMERA_ACCESS_ERROR',
            error,
        });
        expect(message).toBe(
            'The camera is in use. Close other apps or tabs using it, then try again.',
        );
        expect(message).not.toContain('NotReadableError');
    });

    it('keeps provider errors and their underlying causes', () => {
        const cause = new Error('Failed to fetch the face model');
        const error = new Error('Model initialization failed', { cause });
        const message = livenessErrorDetails({ state: 'RUNTIME_ERROR', error });
        expect(message).toContain('Model initialization failed');
        expect(message).toContain('Failed to fetch the face model');
        expect(message).toContain('"stack"');
    });

    it('redacts credential material while retaining useful AWS metadata', () => {
        const message = livenessErrorDetails({
            state: 'SERVER_ERROR',
            error: Object.assign(new Error('Access denied'), {
                credentials: { accessKeyId: 'private-key' },
                sessionToken: 'private-token',
                $metadata: { httpStatusCode: 403, requestId: 'request-123' },
            }),
        });
        expect(message).toContain('request-123');
        expect(message).not.toContain('private-key');
        expect(message).not.toContain('private-token');
    });

    it('handles circular SDK errors', () => {
        const error = Object.assign(new Error('Camera failed'), { cause: {} });
        error.cause = error;
        expect(
            livenessErrorDetails({ state: 'CAMERA_ACCESS_ERROR', error }),
        ).toContain('[circular]');
    });

    it('shows recovery guidance without exposing technical details', () => {
        expect(
            livenessErrorMessage({
                state: 'CAMERA_ACCESS_ERROR',
                error: new Error('Permission denied'),
            }),
        ).toBe(
            'Unable to access the camera. Check camera permissions, then try again.',
        );
    });

    it('keeps an unknown provider error out of the user message', () => {
        expect(
            livenessErrorMessage({
                state: 'RUNTIME_ERROR',
                error: new Error('Internal AWS configuration: example-secret'),
            }),
        ).toBe('Face verification could not be completed. Please try again.');
    });

    it('logs redacted diagnostics while returning a friendly message', () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});
        const message = reportLivenessError({
            state: 'SERVER_ERROR',
            error: new Error('Provider failure'),
            credentials: 'private-credentials',
        });
        expect(message).toBe(
            'Face verification is temporarily unavailable. Please try again shortly.',
        );
        expect(warn).toHaveBeenCalledOnce();
        expect(String(warn.mock.calls[0][1])).toContain('Provider failure');
        expect(String(warn.mock.calls[0][1])).not.toContain(
            'private-credentials',
        );
    });
});
