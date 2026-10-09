import { describe, expect, it } from 'vitest';
import { livenessErrorMessage } from '../faceLivenessErrors';

describe('AWS liveness error diagnostics', () => {
    it('reads the wrapped Safari camera exception instead of the generic fallback', () => {
        const error = new DOMException(
            'The camera is already in use.',
            'NotReadableError',
        );
        const message = livenessErrorMessage({
            state: 'CAMERA_ACCESS_ERROR',
            error,
        });
        expect(message).toContain(
            'CAMERA_ACCESS_ERROR: NotReadableError: The camera is already in use.',
        );
        expect(message).toContain('"name": "NotReadableError"');
        expect(message).toContain('"message": "The camera is already in use."');
    });

    it('keeps provider errors and their underlying causes', () => {
        const cause = new Error('Failed to fetch the face model');
        const error = new Error('Model initialization failed', { cause });
        const message = livenessErrorMessage({ state: 'RUNTIME_ERROR', error });
        expect(message).toContain('Model initialization failed');
        expect(message).toContain('Failed to fetch the face model');
        expect(message).toContain('"stack"');
    });

    it('redacts credential material while retaining useful AWS metadata', () => {
        const message = livenessErrorMessage({
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
            livenessErrorMessage({ state: 'CAMERA_ACCESS_ERROR', error }),
        ).toContain('[circular]');
    });

    it('can omit stack traces without hiding the actual cause', () => {
        expect(
            livenessErrorMessage(
                {
                    state: 'CAMERA_ACCESS_ERROR',
                    error: new Error('Permission denied'),
                },
                false,
            ),
        ).toBe('CAMERA_ACCESS_ERROR: Error: Permission denied');
    });
});
