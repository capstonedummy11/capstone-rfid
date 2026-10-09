// FEATURE:face-liveness - UI para sa face liveness.
import { FaceLivenessDetector } from '@aws-amplify/ui-react-liveness';
import '@aws-amplify/ui-react/styles.css';
import { Amplify } from 'aws-amplify';
import React from 'react';
import { createRoot } from 'react-dom/client';
import { cameraAvailabilityMessage, pauseCameraPreviews } from './cameraAccess';
import {
    livenessErrorDetails,
    reportLivenessError,
} from './faceLivenessErrors';
import { livenessOrientationMessage } from './faceLivenessOrientation';

type LivenessPurpose =
    | 'attendance_student'
    | 'attendance_instructor'
    | 'instructor_login'
    | 'online_class_student';

type SessionResponse = {
    session_id: string;
    region: string;
    identity_pool_id: string;
};

export class FaceLivenessError extends Error {}

// @function livenessFailureMessage: Kinukuha ang liveness failure message result para sa face Liveness.
// @useIn livenessFailureMessage: resources/js/lib/faceLiveness.tsx:110
const livenessFailureMessage = (
    payload: Record<string, unknown>,
    httpStatus: number,
) => {
    console.warn(
        'Face verification request failed',
        livenessErrorDetails({ httpStatus, ...payload }),
    );
    if (httpStatus === 401 || httpStatus === 419)
        return 'Your session has expired. Refresh the page and sign in again.';
    if (httpStatus === 429)
        return 'Too many attempts. Wait a moment before trying again.';
    if (httpStatus >= 500 || httpStatus === 409)
        return 'Face verification is temporarily unavailable. Please try again shortly.';
    return 'Face verification was unsuccessful. Please try again and follow the camera prompts.';
};

// @function xsrfToken: Kinukuha ang xsrf token result para sa face Liveness.
// @useIn xsrfToken: resources/js/lib/faceLiveness.tsx:75
const xsrfToken = () => {
    const encodedToken = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.split('=')
        .slice(1)
        .join('=');

    return encodedToken ? decodeURIComponent(encodedToken) : '';
};

// @function requestJson: Kinukuha ang request json result para sa face Liveness.
// @useIn requestJson: resources/js/lib/faceLiveness.tsx:101
async function requestJson(url: string, body: object) {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: JSON.stringify(body),
    });
    const payload = await response.json().catch(() => ({}));

    return { response, payload };
}

// @function LivenessDialog: Kinukuha ang liveness dialog result para sa face Liveness.
// @useIn LivenessDialog: resources/js/lib/faceLiveness.tsx:285
function LivenessDialog({
    session,
    finish,
}: {
    session: SessionResponse;
    finish: (token?: string, error?: Error) => void;
}) {
    const [message, setMessage] = React.useState('');
    const completing = React.useRef(false);

    // @function complete: Kinukuha ang complete result para sa face Liveness.
    // @useIn complete: resources/js/lib/faceLiveness.tsx:174
    const complete = async () => {
        if (completing.current) return;
        completing.current = true;
        setMessage('Confirming liveness securely...');

        try {
            const { response, payload } = await requestJson(
                `/face-liveness/sessions/${encodeURIComponent(session.session_id)}/result`,
                {},
            );

            if (!response.ok || !payload?.token) {
                finish(
                    undefined,
                    new FaceLivenessError(
                        livenessFailureMessage(payload, response.status),
                    ),
                );
                return;
            }

            finish(payload.token);
        } catch (error) {
            finish(
                undefined,
                new FaceLivenessError(reportLivenessError(error)),
            );
        }
    };

    return (
        <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="face-liveness-title"
            style={{
                position: 'fixed',
                inset: 0,
                zIndex: 100000,
                display: 'grid',
                placeItems: 'center',
                padding: '1rem',
                background: 'rgba(15, 23, 42, 0.78)',
            }}
        >
            <div
                style={{
                    width: 'min(100%, 42rem)',
                    maxHeight: '95vh',
                    overflow: 'auto',
                    borderRadius: '0.75rem',
                    background: '#fff',
                    padding: '1rem',
                    boxShadow: '0 24px 60px rgba(0,0,0,.28)',
                }}
            >
                <div style={{ marginBottom: '0.75rem' }}>
                    <h2
                        id="face-liveness-title"
                        style={{
                            margin: 0,
                            fontSize: '1.125rem',
                            fontWeight: 700,
                        }}
                    >
                        Live face verification
                    </h2>
                    <p
                        style={{
                            margin: '0.25rem 0 0',
                            color: '#475569',
                            fontSize: '0.875rem',
                        }}
                    >
                        Follow the camera prompts. A still photo or replayed
                        video will not be accepted.
                    </p>
                </div>
                <FaceLivenessDetector
                    sessionId={session.session_id}
                    region={session.region}
                    onAnalysisComplete={complete}
                    onUserCancel={() =>
                        finish(
                            undefined,
                            new FaceLivenessError(
                                'Liveness verification was cancelled.',
                            ),
                        )
                    }
                    onError={(error, deviceInfo) =>
                        finish(
                            undefined,
                            new FaceLivenessError(
                                reportLivenessError({
                                    ...error,
                                    device: deviceInfo,
                                    browser: navigator.userAgent,
                                    viewport: {
                                        width: window.innerWidth,
                                        height: window.innerHeight,
                                    },
                                }),
                            ),
                        )
                    }
                />
                {message ? (
                    <p
                        role="status"
                        style={{
                            margin: '0.75rem 0 0',
                            textAlign: 'center',
                            color: '#0f766e',
                        }}
                    >
                        {message}
                    </p>
                ) : null}
                <button
                    type="button"
                    onClick={() =>
                        finish(
                            undefined,
                            new FaceLivenessError(
                                'Liveness verification was cancelled.',
                            ),
                        )
                    }
                    style={{
                        marginTop: '0.75rem',
                        width: '100%',
                        border: '1px solid #cbd5e1',
                        borderRadius: '0.5rem',
                        padding: '0.625rem',
                        background: '#fff',
                        color: '#334155',
                    }}
                >
                    Cancel
                </button>
            </div>
        </div>
    );
}

// @function runFaceLiveness: Pinapatakbo ang face liveness sa face Liveness flow.
// @useIn runFaceLiveness: resources/js/pages/StudentParent/OnlineClasses.vue
export async function runFaceLiveness({
    purpose,
    subjectKey,
}: {
    purpose: LivenessPurpose;
    subjectKey: string | number;
}): Promise<string | null> {
    try {
        const { response, payload } = await requestJson(
            '/face-liveness/sessions',
            {
                purpose,
                subject_key: String(subjectKey),
            },
        );

        if (response.status === 409 && payload?.enabled === false) {
            return null;
        }

        if (!response.ok) {
            throw new FaceLivenessError(
                livenessFailureMessage(payload, response.status),
            );
        }

        const session = payload as SessionResponse;
        const unavailable = cameraAvailabilityMessage();
        if (unavailable) throw new FaceLivenessError(unavailable);
        const orientationMessage = livenessOrientationMessage();
        if (orientationMessage) throw new FaceLivenessError(orientationMessage);
        Amplify.configure({
            Auth: {
                Cognito: {
                    identityPoolId: session.identity_pool_id,
                    allowGuestAccess: true,
                },
            },
        });
        const resumePreviews = await pauseCameraPreviews();
        try {
            const host = document.createElement('div');
            document.body.appendChild(host);
            const root = createRoot(host);

            return await new Promise<string>((resolve, reject) => {
                let finished = false;
                // @function finish: Kinukuha ang finish result para sa face Liveness.
                // @useIn finish: resources/js/lib/faceLiveness.tsx:107
                const finish = (token?: string, error?: Error) => {
                    if (finished) return;
                    finished = true;
                    // SDK callbacks can run inside a React effect. Finish after it
                    // returns, and release the detector camera before Vue resumes
                    // its preview, avoiding overlapping owners on iPad Safari.
                    queueMicrotask(() => {
                        host.querySelectorAll('video').forEach((video) => {
                            video.pause();
                            const stream = video.srcObject;
                            if (stream instanceof MediaStream) {
                                stream
                                    .getTracks()
                                    .forEach((track) => track.stop());
                            }
                            video.srcObject = null;
                        });
                        root.unmount();
                        host.remove();
                        if (token) resolve(token);
                        else
                            reject(
                                error ??
                                    new FaceLivenessError(
                                        'Liveness verification failed.',
                                    ),
                            );
                    });
                };

                root.render(
                    <LivenessDialog session={session} finish={finish} />,
                );
            });
        } finally {
            resumePreviews();
        }
    } catch (error) {
        if (error instanceof FaceLivenessError) throw error;
        throw new FaceLivenessError(reportLivenessError(error));
    }
}
