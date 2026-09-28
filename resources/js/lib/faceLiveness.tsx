import { FaceLivenessDetector } from '@aws-amplify/ui-react-liveness';
import '@aws-amplify/ui-react/styles.css';
import { Amplify } from 'aws-amplify';
import React from 'react';
import { createRoot } from 'react-dom/client';

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

const csrfToken = () =>
    document
        .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.getAttribute('content') ?? '';

async function requestJson(url: string, body: object) {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(body),
    });
    const payload = await response.json().catch(() => ({}));

    return { response, payload };
}

function LivenessDialog({
    session,
    finish,
}: {
    session: SessionResponse;
    finish: (token?: string, error?: Error) => void;
}) {
    const [message, setMessage] = React.useState('');
    const completing = React.useRef(false);

    const complete = async () => {
        if (completing.current) return;
        completing.current = true;
        setMessage('Confirming liveness securely...');

        const { response, payload } = await requestJson(
            `/face-liveness/sessions/${encodeURIComponent(session.session_id)}/result`,
            {},
        );

        if (!response.ok || !payload?.token) {
            finish(
                undefined,
                new FaceLivenessError(
                    payload?.message ?? 'Liveness verification did not pass.',
                ),
            );
            return;
        }

        finish(payload.token);
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

export async function runFaceLiveness({
    purpose,
    subjectKey,
}: {
    purpose: LivenessPurpose;
    subjectKey: string | number;
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
    Amplify.configure({
        Auth: {
            Cognito: {
                identityPoolId: session.identity_pool_id,
                allowGuestAccess: true,
            },
        },
    });
    const host = document.createElement('div');
    document.body.appendChild(host);
    const root = createRoot(host);

    return new Promise<string>((resolve, reject) => {
        let finished = false;
        const finish = (token?: string, error?: Error) => {
            if (finished) return;
            finished = true;
            root.unmount();
            host.remove();
            if (token) resolve(token);
            else
                reject(
                    error ??
                        new FaceLivenessError('Liveness verification failed.'),
                );
        };

        root.render(<LivenessDialog session={session} finish={finish} />);
    });
}
