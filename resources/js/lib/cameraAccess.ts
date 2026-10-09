type PreviewListener = (paused: boolean) => void | Promise<void>;

const previews = new Set<PreviewListener>();
let paused = false;

export function registerCameraPreview(listener: PreviewListener) {
    previews.add(listener);
    void listener(paused);
    return () => previews.delete(listener);
}

// Release preview streams before AWS opens its own camera on iPad/Safari.
export async function pauseCameraPreviews() {
    if (paused) throw new Error('A live face verification is already running.');
    paused = true;
    const resume = () => {
        paused = false;
        previews.forEach((listener) => void listener(false));
    };
    try {
        await Promise.all([...previews].map((listener) => listener(true)));
    } catch (error) {
        resume();
        throw error;
    }
    return resume;
}

export function cameraAvailabilityMessage(): string | null {
    if (!window.isSecureContext) {
        return 'Camera access requires HTTPS. Open this site using its secure HTTPS address.';
    }
    if (!navigator.mediaDevices?.getUserMedia) {
        return 'Camera access is unavailable in this browser. Open this site in an updated Safari or Chrome browser.';
    }
    return null;
}
