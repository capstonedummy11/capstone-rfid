import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { pauseCameraPreviews } from '../../lib/cameraAccess';
import CameraCapture from '../CameraCapture.vue';

const wrappers: ReturnType<typeof mount>[] = [];
let resume: (() => void) | undefined;
const getUserMedia = vi.fn();

function cameraStream() {
    const stop = vi.fn();
    return { stream: { getTracks: () => [{ stop }] }, stop };
}

beforeEach(() => {
    vi.stubGlobal('isSecureContext', true);
    Object.defineProperty(navigator, 'mediaDevices', {
        configurable: true,
        value: { getUserMedia },
    });
    getUserMedia.mockReset();
    vi.spyOn(HTMLMediaElement.prototype, 'play').mockResolvedValue();
    vi.spyOn(HTMLMediaElement.prototype, 'pause').mockImplementation(() => {});
});

afterEach(() => {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
    resume?.();
    resume = undefined;
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

describe('camera handoff to liveness', () => {
    it('stops the preview before liveness and reopens it afterward', async () => {
        const first = cameraStream();
        const second = cameraStream();
        getUserMedia
            .mockResolvedValueOnce(first.stream)
            .mockResolvedValueOnce(second.stream);
        const wrapper = mount(CameraCapture);
        wrappers.push(wrapper);
        await flushPromises();
        expect(wrapper.text()).toContain('LIVE');

        resume = await pauseCameraPreviews();
        expect(first.stop).toHaveBeenCalledOnce();
        expect(wrapper.find('video').element.pause).toHaveBeenCalled();
        expect(wrapper.find('video').element.srcObject).toBeNull();
        resume();
        resume = undefined;
        await flushPromises();
        expect(getUserMedia).toHaveBeenCalledTimes(2);
        expect(wrapper.text()).toContain('LIVE');
    });

    it('waits for an outstanding camera request and stops its late stream', async () => {
        const late = cameraStream();
        let grant!: (stream: unknown) => void;
        getUserMedia.mockReturnValueOnce(
            new Promise((resolve) => {
                grant = resolve;
            }),
        );
        wrappers.push(mount(CameraCapture));
        let ready = false;
        const handoff = pauseCameraPreviews().then((release) => {
            resume = release;
            ready = true;
        });
        await flushPromises();
        expect(ready).toBe(false);
        grant(late.stream);
        await handoff;
        expect(late.stop).toHaveBeenCalledOnce();
    });

    it('does not open a preview mounted while liveness owns the camera', async () => {
        resume = await pauseCameraPreviews();
        wrappers.push(mount(CameraCapture));
        await flushPromises();
        expect(getUserMedia).not.toHaveBeenCalled();
    });

    it('stops a stream granted after the component unmounts', async () => {
        const late = cameraStream();
        let grant!: (stream: unknown) => void;
        getUserMedia.mockReturnValueOnce(
            new Promise((resolve) => {
                grant = resolve;
            }),
        );
        const wrapper = mount(CameraCapture);
        wrapper.unmount();
        grant(late.stream);
        await flushPromises();
        expect(late.stop).toHaveBeenCalledOnce();
    });

    it('explains HTTPS requirements without requesting the camera', async () => {
        vi.stubGlobal('isSecureContext', false);
        const wrapper = mount(CameraCapture);
        wrappers.push(wrapper);
        await flushPromises();
        expect(wrapper.text()).toContain('Camera access requires HTTPS');
        expect(getUserMedia).not.toHaveBeenCalled();
    });
});
