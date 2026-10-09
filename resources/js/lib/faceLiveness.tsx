// FEATURE:face-liveness - UI para sa face liveness.
import { FaceLivenessDetector } from '@aws-amplify/ui-react-liveness';
import '@aws-amplify/ui-react/styles.css';
import { Amplify } from 'aws-amplify';
import React from 'react';
import { createRoot } from 'react-dom/client';
import { cameraAvailabilityMessage, pauseCameraPreviews } from './cameraAccess';

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
    diagnosticMode: boolean,
) => {
    const message =
        typeof payload?.message === 'string'
            ? payload.message
            : 'Liveness verification did not pass.';

    if (!diagnosticMode) return message;

    const details = [`HTTP ${httpStatus}`];

    if (typeof payload?.status === 'string') {
        details.push(`AWS status ${payload.status}`);
    }

    if (typeof payload?.confidence === 'number') {
        details.push(`confidence ${payload.confidence.toFixed(2)}`);
    }

    if (typeof payload?.threshold === 'number') {
        details.push(`required ${payload.threshold.toFixed(2)}`);
    }

    if (typeof payload?.reference_image_received === 'boolean') {
        details.push(
            `reference image ${payload.reference_image_received ? 'received' : 'missing'}`,
        );
    }

    return `${message} Test details: ${details.join(', ')}.`;
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
    diagnosticMode,
}: {
    session: SessionResponse;
    finish: (token?: string, error?: Error) => void;
    diagnosticMode: boolean;
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
                        livenessFailureMessage(
                            payload,
                            response.status,
                            diagnosticMode,
                        ),
                    ),
                );
                return;
            }

            finish(payload.token);
        } catch {
            finish(
                undefined,
                new FaceLivenessError(
                    'Could not retrieve the liveness result. Please check your connection and try again.',
                ),
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
                    onError={(error) =>
                        finish(
                            undefined,
                            new FaceLivenessError(
                                error?.message ??
                                    'The liveness camera could not complete verification.',
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
    diagnosticMode = false,
}: {
    purpose: LivenessPurpose;
    subjectKey: string | number;
    diagnosticMode?: boolean;
}): Promise<string | null> {
    const { response, payload } = await requestJson('/face-liveness/sessions', {
        purpose,
        subject_key: String(subjectKey),
    });

    if (response.status === 409 && payload?.enabled === false) {
        return null;
    }

    if (!response.ok) {
        throw new FaceLivenessError(
            payload?.message ?? 'AWS Face Liveness is unavailable.',
        );
    }

    const session = payload as SessionResponse;
    const unavailable = cameraAvailabilityMessage();
    if (unavailable) throw new FaceLivenessError(unavailable);
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
            };

            root.render(
                <LivenessDialog
                    session={session}
                    finish={finish}
                    diagnosticMode={diagnosticMode}
                />,
            );
        });
    } finally {
        resumePreviews();
    }
}
