import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const mocks = vi.hoisted(() => ({
    detector: vi.fn<
        (props: {
            onError: (error: unknown) => void;
            onUserCancel: () => void;
        }) => null
    >(() => null),
    resume: vi.fn(),
    pause: vi.fn(),
}));

vi.mock('@aws-amplify/ui-react-liveness', () => ({
    FaceLivenessDetector: mocks.detector,
}));
vi.mock('aws-amplify', () => ({ Amplify: { configure: vi.fn() } }));
vi.mock('../cameraAccess', () => ({
    cameraAvailabilityMessage: () => null,
    pauseCameraPreviews: mocks.pause,
}));

import { runFaceLiveness } from '../faceLiveness';

beforeEach(() => {
    mocks.pause.mockResolvedValue(mocks.resume);
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue({
            ok: true,
            status: 200,
            json: async () => ({
                session_id: 'test-session',
                region: 'us-east-1',
                identity_pool_id: 'test-pool',
            }),
        }),
    );
    vi.spyOn(HTMLMediaElement.prototype, 'pause').mockImplementation(() => {});
});

afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
    document.body.innerHTML = '';
});

describe('liveness camera lifecycle', () => {
    it('shows the nested Safari error and releases the SDK camera before resuming the preview', async () => {
        const stop = vi.fn();
        class TestStream {
            getTracks() {
                return [{ stop }];
            }
        }
        vi.stubGlobal('MediaStream', TestStream);

        const verification = runFaceLiveness({
            purpose: 'instructor_login',
            subjectKey: 1,
        });
        // Attach the rejection handler before triggering the SDK callback.
        const rejected = expect(verification).rejects.toThrow(
            'CAMERA_ACCESS_ERROR: NotReadableError: Camera is in use',
        );
        await vi.waitFor(() => expect(mocks.detector).toHaveBeenCalled());
        const props = mocks.detector.mock.calls[0][0] as unknown as {
            onError: (error: unknown) => void;
        };
        const video = document.createElement('video');
        video.srcObject = new TestStream() as unknown as MediaStream;
        document.querySelector('[role="dialog"]')!.appendChild(video);

        mocks.resume.mockImplementationOnce(() => {
            expect(stop).toHaveBeenCalledOnce();
            expect(video.srcObject).toBeNull();
            expect(document.querySelector('[role="dialog"]')).toBeNull();
        });
        props.onError({
            state: 'CAMERA_ACCESS_ERROR',
            error: new DOMException('Camera is in use', 'NotReadableError'),
        });
        await rejected;
        expect(mocks.resume).toHaveBeenCalledOnce();
    });

    it('restores camera previews when the user cancels', async () => {
        const verification = runFaceLiveness({
            purpose: 'instructor_login',
            subjectKey: 1,
        });
        const rejected = expect(verification).rejects.toThrow('cancelled');
        await vi.waitFor(() => expect(mocks.detector).toHaveBeenCalled());
        const props = mocks.detector.mock.calls[0][0] as unknown as {
            onUserCancel: () => void;
        };
        props.onUserCancel();
        await rejected;
        expect(mocks.resume).toHaveBeenCalledOnce();
        expect(document.querySelector('[role="dialog"]')).toBeNull();
    });
});
