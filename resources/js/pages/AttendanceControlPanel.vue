<script setup>
defineOptions({
    layout: null,
});

import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import Swal from 'sweetalert2';
import CameraCapture from '@/components/CameraCapture.vue';

const props = defineProps({
    rooms: {
        type: Array,
        default: () => [],
    },
    borrowItemsCatalog: {
        type: Array,
        default: () => [],
    },
    borrowItemsByRfid: {
        type: Object,
        default: () => ({}),
    },
    featureSettings: {
        type: Object,
        default: () => ({
            borrowing_enabled: false,
            inventory_enabled: false,
        }),
    },
    demoInstructorRfids: {
        type: Array,
        default: () => [],
    },
    demoStudentRfids: {
        type: Array,
        default: () => [],
    },
    emergencyTypes: {
        type: Array,
        default: () => [],
    },
    emergencyHotlines: {
        type: Array,
        default: () => [],
    },
    studentToastSeconds: {
        type: Number,
        default: 15,
    },
    studentInfoVisibleSeconds: {
        type: Number,
        default: 10,
    },
    panelRoom: {
        type: String,
        default: '',
    },
});

// --- Panel gate / access control ---
const panelUnlocked = ref(false);
const selectedRoom = ref('');

const systemModeStyles = {
    attendance: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    borrowing: 'border-amber-200 bg-amber-50 text-amber-700',
    idle: 'border-slate-200 bg-slate-100 text-slate-600',
};

const currentDate = ref('');
const currentTime = ref('');
const sessionActive = ref(false);
const currentMode = ref('idle');
const isListening = ref(true);
const scanPulse = ref(false);
const lastScanned = ref('');
const activeProfessor = ref(null);
const activeStudent = ref(null);
const forceStudentCheckoutNext = ref(false);
const defaultTapHeadline = 'Tap RFID';
const tapHeadline = ref(defaultTapHeadline);
const lastAction = ref(
    'Waiting for an instructor RFID tap to begin attendance recording.',
);
const attendanceRecords = ref([]);
const scanHistory = ref([
    {
        id: 'boot',
        title: 'Scanner ready',
        subtitle: 'The panel is listening for RFID input.',
        time: new Date().toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
        }),
        tone: 'neutral',
    },
]);

let timeTicker = null;
let rfidBuffer = '';
let lastKeyTime = 0;
let scanFinalizeTimer = null;
let activeStudentTimer = null;
let tapHeadlineTimer = null;
let demoStudentCursor = 0;
let captureResetTimer = null;
let panelStatusTicker = null;

const cameraRef = ref(null);
const capturedPhotoUrl = ref(null);
const panelFeatureSettings = ref({ ...(props.featureSettings ?? {}) });

const PANEL_RUNTIME_KEY = 'panelRuntime';

const SCAN_TIMEOUT = 300;
const AUTO_FINALIZE_DELAY = 120;
const SCAN_TERMINATORS = new Set(['Enter', 'NumpadEnter', 'Tab']);
const studentToastSeconds = Number(props.studentToastSeconds ?? 15);
const STUDENT_TOAST_MS =
    Number.isFinite(studentToastSeconds) && studentToastSeconds > 0
        ? studentToastSeconds * 1000
        : 15000;
const studentInfoVisibleSeconds = Number(props.studentInfoVisibleSeconds ?? 10);
const STUDENT_INFO_VISIBLE_MS =
    Number.isFinite(studentInfoVisibleSeconds) && studentInfoVisibleSeconds > 0
        ? studentInfoVisibleSeconds * 1000
        : 10000;
const borrowingEnabled = computed(() =>
    Boolean(panelFeatureSettings.value?.borrowing_enabled),
);
const modeLabel = computed(() => {
    if (currentMode.value === 'attendance') return 'Attendance Mode';
    if (currentMode.value === 'borrowing' && borrowingEnabled.value)
        return 'Borrowing Mode';
    return 'Idle Mode';
});

const modeClass = computed(
    () => systemModeStyles[currentMode.value] || systemModeStyles.idle,
);

const attendanceCount = computed(() => attendanceRecords.value.length);

const activeProfessorSchedule = computed(() => {
    const professor = activeProfessor.value;
    if (!professor) return '---';

    const explicitSchedule = String(professor.schedule ?? '').trim();
    if (
        explicitSchedule &&
        explicitSchedule.toLowerCase() !== 'valid schedule'
    ) {
        return explicitSchedule;
    }

    const start = String(professor.schedule_time_start ?? '').trim();
    const end = String(professor.schedule_time_end ?? '').trim();
    const days = String(professor.schedule_days ?? '').trim();

    if (start && end) {
        return `${days ? `${days} ` : ''}${start} - ${end}`;
    }

    return '---';
});

const attendancePercentage = computed(() => {
    if (!activeProfessor.value) return 0;
    return 0;
});

const statusHeadline = computed(() => {
    if (!sessionActive.value) return 'Tap RFID Card';
    if (currentMode.value === 'borrowing' && borrowingEnabled.value)
        return 'Borrower verification is active';
    return 'Attendance recording is live';
});

const statusSubline = computed(() => {
    if (!sessionActive.value) return 'Professor tap starts the session.';
    if (currentMode.value === 'borrowing' && borrowingEnabled.value)
        return 'Tap the professor card again to resume attendance or end the session.';
    return 'Students can now tap their RFID cards to be marked present.';
});

const actionButtonLabel = computed(() => {
    if (!sessionActive.value) return 'Demo Instructor Tap';
    if (currentMode.value === 'borrowing' && borrowingEnabled.value)
        return 'Demo Borrower Tap';
    return 'Demo Student Tap';
});

const emergencyGroups = computed(() => {
    const groups = {};
    (props.emergencyTypes ?? []).forEach((type) => {
        const category = String(type?.category ?? 'general');
        if (!groups[category]) groups[category] = [];
        groups[category].push(type);
    });
    return groups;
});

const demoBorrowerRfids = computed(() =>
    Object.keys(props.borrowItemsByRfid ?? {}),
);

const borrowCatalogMap = computed(() => {
    const map = new Map();
    (props.borrowItemsCatalog ?? []).forEach((item) => {
        const barcode = String(item?.barcode ?? '').trim();
        if (!barcode) return;

        map.set(barcode, {
            name: item?.name ?? 'Unknown Item',
            id: item?.id ?? barcode,
            type: item?.type ?? 'Device',
            barcode,
            status: item?.status ?? 'Available',
        });
    });
    return map;
});

const resolveBorrowItemsPayload = (payload) => {
    if (Array.isArray(payload)) return payload;
    if (payload && typeof payload === 'object' && Array.isArray(payload.items))
        return payload.items;
    return [];
};

const borrowedBarcodeMap = computed(() => {
    const map = new Map();

    Object.entries(props.borrowItemsByRfid ?? {}).forEach(([rfid, payload]) => {
        const items = resolveBorrowItemsPayload(payload);

        items.forEach((item) => {
            const barcode = String(item?.barcode ?? '').trim();
            if (!barcode) return;
            map.set(barcode, normalizeRfid(rfid));
        });
    });

    return map;
});

const recentAttendance = computed(() =>
    [...attendanceRecords.value].slice(0, 6),
);
const attendeesSlideVisible = computed(
    () => panelUnlocked.value && currentMode.value === 'attendance',
);

const updateClock = () => {
    const now = new Date();
    currentDate.value = now.toLocaleDateString('en-US', {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
    currentTime.value = now.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        second: '2-digit',
        hour12: true,
    });
};

const pushHistory = (title, subtitle, tone = 'neutral') => {
    scanHistory.value.unshift({
        id: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
        title,
        subtitle,
        time: new Date().toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
        }),
        tone,
    });

    scanHistory.value = scanHistory.value.slice(0, 8);
};

const triggerPulse = (rfid) => {
    lastScanned.value = rfid;
    scanPulse.value = true;
    window.setTimeout(() => {
        scanPulse.value = false;
    }, 650);
};

const normalizeRfid = (value) =>
    String(value ?? '')
        .trim()
        .toLowerCase();

const escapeHtml = (value = '') =>
    String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

const xsrfToken = () => {
    const xsrfRaw = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    return xsrfRaw ? decodeURIComponent(xsrfRaw) : '';
};

const setTapHeadline = (message, holdMs = 2200) => {
    tapHeadline.value = message;
    if (tapHeadlineTimer) {
        clearTimeout(tapHeadlineTimer);
    }

    tapHeadlineTimer = window.setTimeout(() => {
        tapHeadline.value = defaultTapHeadline;
        tapHeadlineTimer = null;
    }, holdMs);
};

const triggerCameraCapture = () => {
    if (captureResetTimer) clearTimeout(captureResetTimer);
    const dataUrl = cameraRef.value?.captureFrame() ?? null;
    capturedPhotoUrl.value = dataUrl;
    // Auto-reset back to live feed after the student info hides
    captureResetTimer = window.setTimeout(() => {
        cameraRef.value?.resetCapture();
        capturedPhotoUrl.value = null;
        captureResetTimer = null;
    }, STUDENT_INFO_VISIBLE_MS);

    return dataUrl;
};

const showStudentTemporarily = (student) => {
    activeStudent.value = student;

    if (activeStudentTimer) {
        clearTimeout(activeStudentTimer);
    }

    activeStudentTimer = window.setTimeout(() => {
        activeStudent.value = null;
        activeStudentTimer = null;
    }, STUDENT_INFO_VISIBLE_MS);
};

const lookupRfidFromServer = async (rfid) => {
    try {
        const xsrfRaw = document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1];

        const response = await fetch('/attendance-control-panel/rfid-lookup', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrfRaw ? decodeURIComponent(xsrfRaw) : '',
            },
            body: JSON.stringify({ rfid, room: selectedRoom.value }),
        });

        if (!response.ok) return null;

        const payload = await response.json();
        if (!payload?.found || !payload?.profile) return null;

        return payload;
    } catch {
        return null;
    }
};

const recordStudentTapOnServer = async (student, extra = {}) => {
    try {
        const xsrfRaw = document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1];

        const response = await fetch('/attendance-control-panel/student-tap', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrfRaw ? decodeURIComponent(xsrfRaw) : '',
            },
            body: JSON.stringify({
                rfid: student.rfid,
                room: selectedRoom.value,
                subject_code: activeProfessor.value?.subject_code ?? null,
                schedule_id: activeProfessor.value?.schedule_id ?? null,
                force_checkout: forceStudentCheckoutNext.value,
                ...extra,
            }),
        });

        const payload = await response.json().catch(() => null);
        if (!response.ok) return payload;
        return payload;
    } catch {
        return null;
    }
};

const requestInstructorTapForTemporaryMovement = async (student) => {
    const result = await Swal.fire({
        icon: 'warning',
        title: 'Instructor RFID Required',
        text: `${student.name} is trying to record a temporary exit or return. Ask the active instructor to tap or enter their RFID.`,
        input: 'password',
        inputLabel: 'Instructor RFID',
        inputPlaceholder: 'Tap or enter instructor RFID',
        showCancelButton: true,
        confirmButtonText: 'Authorize Movement',
        confirmButtonColor: '#0f766e',
        cancelButtonText: 'Cancel',
        inputValidator: (value) =>
            String(value || '').trim()
                ? undefined
                : 'Instructor RFID is required.',
    });

    if (!result.isConfirmed) {
        return null;
    }

    return String(result.value || '').trim();
};

const loadAttendanceLogsFromServer = async () => {
    if (!selectedRoom.value) return;

    try {
        const xsrfRaw = document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1];

        const response = await fetch(
            '/attendance-control-panel/attendance-logs',
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrfRaw ? decodeURIComponent(xsrfRaw) : '',
                },
                body: JSON.stringify({
                    room: selectedRoom.value,
                    subject_code: activeProfessor.value?.subject_code ?? null,
                    schedule_id: activeProfessor.value?.schedule_id ?? null,
                }),
            },
        );

        const payload = await response.json().catch(() => null);
        if (!response.ok || !payload?.ok) return;

        attendanceRecords.value = Array.isArray(payload.records)
            ? payload.records
            : [];
    } catch {
        // Ignore fetch failures and keep current in-memory records.
    }
};

const syncPanelSessionState = async (status, extra = {}) => {
    if (!selectedRoom.value) return;
    if (status === 'borrowing' && !borrowingEnabled.value) return;

    try {
        const xsrfRaw = document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1];

        await fetch('/attendance-control-panel/session-state', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrfRaw ? decodeURIComponent(xsrfRaw) : '',
            },
            body: JSON.stringify({
                room: selectedRoom.value,
                status,
                is_listening: isListening.value,
                opened_by_user_id: activeProfessor.value?.user_id ?? null,
                subject_code: activeProfessor.value?.subject_code ?? null,
                schedule_id: activeProfessor.value?.schedule_id ?? null,
                meta: {
                    mode: currentMode.value,
                    ...extra,
                },
            }),
        });
    } catch {
        // Ignore sync failures and keep panel responsive.
    }
};

const persistPanelRuntime = () => {
    const runtime = {
        sessionActive: sessionActive.value,
        currentMode: currentMode.value,
        isListening: isListening.value,
        activeProfessor: activeProfessor.value,
        lastAction: lastAction.value,
    };

    localStorage.setItem(PANEL_RUNTIME_KEY, JSON.stringify(runtime));
};

const clearPanelRuntime = () => {
    localStorage.removeItem(PANEL_RUNTIME_KEY);
};

const showToast = (icon, title) => {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon,
        title,
        customClass: {
            popup: 'hover-fade-toast',
        },
        showConfirmButton: false,
        timer: 1800,
        timerProgressBar: true,
    });
};

const showStudentToast = (student, message, icon = 'success') => {
    const photoSrc =
        capturedPhotoUrl.value ??
        `https://api.dicebear.com/7.x/personas/svg?seed=${encodeURIComponent(student?.avatarSeed ?? student?.name ?? 'rfid-student')}&backgroundColor=b6e3f4`;
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon,
        customClass: {
            popup: 'hover-fade-toast',
        },
        html: `
      <div style="display:flex; align-items:center; gap:10px; font-family:'DM Sans',sans-serif;">
        <img
          src="${photoSrc}"
          alt="student"
          style="width:40px; height:40px; border-radius:10px; background:#dbeafe;"
        />
        <div style="text-align:left; line-height:1.2;">
          <div style="font-size:13px; font-weight:800; color:#0f172a;">${escapeHtml(student?.name ?? 'Student')}</div>
          <div style="font-size:12px; color:#475569;">${escapeHtml(message)}</div>
        </div>
      </div>
    `,
        showConfirmButton: false,
        timer: STUDENT_TOAST_MS,
        timerProgressBar: true,
        width: 380,
    });
};

const startAttendanceSession = (professor) => {
    sessionActive.value = true;
    currentMode.value = 'attendance';
    activeProfessor.value = professor;
    activeStudent.value = null;
    forceStudentCheckoutNext.value = false;
    attendanceRecords.value = [];
    tapHeadline.value = defaultTapHeadline;
    lastAction.value = `${professor.name} started attendance recording for ${professor.subject}.`;
    pushHistory(
        'Attendance started',
        `${professor.name} opened ${professor.subject} for ${professor.section}.`,
        'success',
    );
    syncPanelSessionState('attendance');
    loadAttendanceLogsFromServer();
    showToast('success', 'Attendance recording started');
};

const endAttendanceSession = () => {
    if (!activeProfessor.value) return;

    pushHistory(
        'Attendance ended',
        `${activeProfessor.value.name} closed the session with ${attendanceRecords.value.length} recorded student tap${attendanceRecords.value.length === 1 ? '' : 's'}.`,
        'warning',
    );

    lastAction.value =
        'Attendance session ended. Waiting for the next instructor RFID tap.';
    activeProfessor.value = null;
    activeStudent.value = null;
    forceStudentCheckoutNext.value = false;
    attendanceRecords.value = [];
    sessionActive.value = false;
    currentMode.value = 'idle';
    tapHeadline.value = defaultTapHeadline;
    syncPanelSessionState('online');
    showToast('info', 'Attendance session ended');
};

const enableStudentLogoutMode = () => {
    forceStudentCheckoutNext.value = true;
    currentMode.value = 'attendance';
    lastAction.value =
        'Student logout mode enabled. The next student RFID tap will be recorded as official check-out.';
    pushHistory(
        'Student logout mode',
        'Next student tap will be saved as official check-out instead of temporary exit.',
        'warning',
    );
    setTapHeadline('Student Logout Mode', 5000);
    syncPanelSessionState('attendance', {
        student_logout_mode: true,
        student_logout_mode_started_at: new Date().toISOString(),
    });
    showToast('info', 'Next student tap will be official checkout');
};

const checkStudentFaceForAttendance = async (
    student,
    base64DataUrl = null,
    instructorRfid = null,
    cameraUnavailable = false,
) => {
    try {
        const response = await fetch(
            '/attendance-control-panel/student-face-check',
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify({
                    rfid: student.rfid,
                    room: selectedRoom.value,
                    subject_code: activeProfessor.value?.subject_code ?? null,
                    schedule_id: activeProfessor.value?.schedule_id ?? null,
                    image: base64DataUrl,
                    instructor_rfid: instructorRfid,
                    camera_unavailable: cameraUnavailable,
                }),
            },
        );

        const payload = await response.json().catch(() => null);
        if (!response.ok) return payload ?? { ok: false };
        return payload;
    } catch {
        return {
            ok: false,
            message: 'Connection error during face verification.',
        };
    }
};

const recordAttendance = async (student) => {
    showStudentTemporarily(student);

    const timestamp = new Date().toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });

    const requestActiveInstructorRfid = async ({
        title = 'Instructor RFID Required',
        text,
        confirmButtonText = 'Verify Instructor',
        confirmButtonColor = '#2563eb',
    }) =>
        Swal.fire({
            icon: 'warning',
            title,
            text,
            input: 'text',
            inputPlaceholder: 'Scan instructor RFID',
            inputAttributes: {
                autocomplete: 'off',
                autocapitalize: 'off',
            },
            showCancelButton: true,
            confirmButtonText,
            confirmButtonColor,
            inputValidator: (value) =>
                String(value ?? '').trim()
                    ? undefined
                    : 'Instructor RFID is required.',
        });

    if (!student.hasFaceImage) {
        const instructorApproval = await requestActiveInstructorRfid({
            text: `${student.name} has no registered face image. Scan the active instructor's RFID card to approve this attendance.`,
        });

        if (!instructorApproval.isConfirmed) {
            pushHistory(
                'Attendance blocked',
                `${student.name} has no registered face and instructor approval was not provided.`,
                'warning',
            );
            setTapHeadline('Attendance Not Recorded');
            return;
        }

        const approvalResult = await checkStudentFaceForAttendance(
            student,
            null,
            String(instructorApproval.value).trim(),
        );

        if (!approvalResult?.ok || !approvalResult.instructor_override) {
            showStudentToast(
                student,
                approvalResult?.message ??
                    'Instructor RFID verification failed. Attendance was not recorded.',
                'warning',
            );
            pushHistory(
                'Instructor override blocked',
                `${student.name}: ${approvalResult?.message ?? 'invalid instructor RFID.'}`,
                'warning',
            );
            setTapHeadline('Instructor Verification Failed');
            return;
        }

        pushHistory(
            'Instructor override verified',
            `${activeProfessor.value?.name ?? 'The active instructor'} approved attendance for ${student.name}.`,
            'success',
        );
    } else {
        triggerCameraCapture();

        if (!capturedPhotoUrl.value) {
            let bypassResult = await checkStudentFaceForAttendance(
                student,
                null,
                null,
                true,
            );

            if (!bypassResult?.ok) {
                const instructorApproval = await requestActiveInstructorRfid({
                    title: 'Camera Unavailable',
                    text: "Scan the active instructor's RFID once to continue this scheduled class without camera capture. The bypass ends when the class session ends or the panel logs out.",
                    confirmButtonText: 'Enable Session Bypass',
                    confirmButtonColor: '#d97706',
                });

                if (!instructorApproval.isConfirmed) {
                    pushHistory(
                        'Attendance blocked',
                        'Camera was unavailable and the instructor did not enable the session bypass.',
                        'warning',
                    );
                    setTapHeadline('Attendance Not Recorded');
                    return;
                }

                bypassResult = await checkStudentFaceForAttendance(
                    student,
                    null,
                    String(instructorApproval.value).trim(),
                    true,
                );
            }

            if (!bypassResult?.ok || !bypassResult.camera_session_override) {
                showStudentToast(
                    student,
                    bypassResult?.message ??
                        'Camera bypass verification failed. Attendance was not recorded.',
                    'warning',
                );
                setTapHeadline('Instructor Verification Failed');
                return;
            }

            pushHistory(
                'Camera session bypass',
                `${student.name} continued under the active instructor's camera-unavailable approval.`,
                'warning',
            );
        } else {
            let faceResult = await checkStudentFaceForAttendance(
                student,
                capturedPhotoUrl.value,
            );

            if (faceResult?.requires_instructor_rfid) {
                const instructorApproval = await requestActiveInstructorRfid({
                    text:
                        faceResult.message ??
                        'Scan the active instructor RFID to approve this attendance.',
                });

                if (!instructorApproval.isConfirmed) {
                    pushHistory(
                        'Attendance blocked',
                        `${student.name}: instructor approval was not provided.`,
                        'warning',
                    );
                    setTapHeadline('Attendance Not Recorded');
                    return;
                }

                faceResult = await checkStudentFaceForAttendance(
                    student,
                    null,
                    String(instructorApproval.value).trim(),
                );
            }

            if (!faceResult?.ok || faceResult.verified === false) {
                showStudentToast(
                    student,
                    faceResult?.message ??
                        'Face verification failed. Attendance was not recorded.',
                    'warning',
                );
                pushHistory(
                    'Face verification blocked',
                    `${student.name}: ${faceResult?.message ?? 'verification failed.'}`,
                    'warning',
                );
                setTapHeadline('Face Verification Failed');
                return;
            }

            if (faceResult.provider_unavailable) {
                pushHistory(
                    'AWS Rekognition unavailable',
                    `${student.name}'s camera image was saved as attendance evidence without an AWS comparison.`,
                    'warning',
                );
                await Swal.fire({
                    icon: 'warning',
                    title: 'Face Rekognition Unavailable',
                    text: faceResult.message,
                    confirmButtonText: 'Continue',
                    confirmButtonColor: '#d97706',
                });
            } else {
                pushHistory(
                    'Face verified',
                    `${student.name} passed AWS face verification and the evidence image was saved.`,
                    'success',
                );
            }
        }
    }

    const wasForceCheckout = forceStudentCheckoutNext.value;
    let tapResult = await recordStudentTapOnServer(student);
    forceStudentCheckoutNext.value = false;

    if (tapResult?.requires_temporary_movement_instructor) {
        setTapHeadline('Instructor RFID Required', STUDENT_TOAST_MS);
        showStudentToast(
            student,
            tapResult.message ??
                'Instructor RFID is required before temporary movement.',
            'warning',
        );
        pushHistory(
            'Temporary movement authorization required',
            `${student.name} needs instructor RFID before temporary exit or return.`,
            'warning',
        );

        const instructorRfid =
            await requestInstructorTapForTemporaryMovement(student);

        if (!instructorRfid) {
            showStudentToast(
                student,
                'Temporary movement was cancelled.',
                'warning',
            );
            return;
        }

        tapResult = await recordStudentTapOnServer(student, {
            temporary_movement_instructor_rfid: instructorRfid,
        });
    }

    if (!tapResult?.ok) {
        showStudentToast(
            student,
            tapResult?.message ?? 'Unable to save attendance right now.',
            'warning',
        );
        return;
    }

    const savedRecord = tapResult.record ?? {};
    const savedStatus = savedRecord.status ?? 'Pending';
    const tapType = savedRecord.tap_type ?? tapResult.tap_type ?? 'Check-in';

    const mappedRecord = {
        id: savedRecord.id ?? `${student.id}-${Date.now()}`,
        attendance_id: savedRecord.attendance_id ?? null,
        rfid: savedRecord.rfid ?? student.rfid,
        name: savedRecord.name ?? student.name,
        year: student.year,
        course: student.course,
        section: student.section,
        time: savedRecord.time ?? timestamp,
        time_in: savedRecord.time_in ?? null,
        time_out: savedRecord.time_out ?? null,
        tap_type: tapType,
        tap_sequence_number: savedRecord.tap_sequence_number ?? null,
        room_status: savedRecord.room_status ?? null,
        remarks: savedRecord.remarks ?? tapResult.message ?? null,
        status: savedStatus,
    };

    attendanceRecords.value = [
        mappedRecord,
        ...attendanceRecords.value.filter(
            (record) => record.id !== mappedRecord.id,
        ),
    ];

    lastAction.value = `${student.name}: ${tapType} at ${timestamp}. Status: ${savedStatus}.`;
    pushHistory(
        tapResult.accepted === false ? 'Tap ignored' : tapType,
        `${student.name}: ${tapResult.message ?? `${tapType} recorded.`}`,
        tapResult.accepted === false ? 'warning' : 'success',
    );
    setTapHeadline(
        wasForceCheckout && tapResult.accepted !== false
            ? 'Student Logout Recorded'
            : tapResult.accepted === false
              ? 'Tap ignored'
              : tapType,
        STUDENT_TOAST_MS,
    );
    showStudentToast(
        student,
        tapResult.message ?? `${tapType} recorded.`,
        tapResult.accepted === false ? 'warning' : 'success',
    );
};

const submitBorrowingUpdate = async (borrower, selectedItems) => {
    const barcodes = selectedItems
        .map((item) => String(item?.barcode ?? '').trim())
        .filter((barcode) => barcode !== '');

    if (!borrower?.rfid || barcodes.length === 0) {
        return {
            ok: false,
            message: 'No borrower RFID or item barcode provided.',
        };
    }

    try {
        const xsrfRaw = document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1];

        const response = await fetch(
            '/attendance-control-panel/borrow-items-only',
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrfRaw ? decodeURIComponent(xsrfRaw) : '',
                },
                body: JSON.stringify({
                    rfid: borrower.rfid,
                    barcodes,
                    room: selectedRoom.value,
                    subject_code: activeProfessor.value?.subject_code ?? null,
                    schedule_id: activeProfessor.value?.schedule_id ?? null,
                }),
            },
        );

        const payload = await response.json().catch(() => null);
        if (!response.ok || !payload?.ok) {
            return {
                ok: false,
                message:
                    payload?.message ??
                    'Unable to update borrow items right now.',
            };
        }

        return {
            ok: true,
            message: payload?.message ?? 'Borrow items updated.',
            borrowed: Array.isArray(payload?.borrowed_barcodes)
                ? payload.borrowed_barcodes
                : [],
        };
    } catch {
        return {
            ok: false,
            message: 'Connection error while updating borrow items.',
        };
    }
};

const processBorrowerMode = (student) => {
    const borrower = student;
    showStudentTemporarily(borrower);

    const getActiveBorrowedItems = () => {
        const rfidKey = normalizeRfid(borrower?.rfid);
        const payload = props.borrowItemsByRfid?.[rfidKey];
        return resolveBorrowItemsPayload(payload);
    };

    const renderBorrowList = (items) => {
        if (!items.length) {
            return '<div class="rounded-xl border border-dashed border-slate-300 p-3 text-xs text-slate-500">No items scanned yet.</div>';
        }

        return items
            .map((item) => {
                return `<div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
          <div class="text-xs font-bold text-slate-800">${escapeHtml(item.name)}</div>
          <div class="text-[11px] text-slate-500">${escapeHtml(item.id)} | ${escapeHtml(item.type)} | ${escapeHtml(item.barcode)}</div>
          <div class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold ${item.requestedAction === 'borrow' ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-700'}">${item.requestedAction === 'borrow' ? 'BORROW' : 'RETURN'}</div>
        </div>`;
            })
            .join('');
    };

    const activeBorrowedItems = getActiveBorrowedItems();
    const borrowedItemsHtml = activeBorrowedItems.length
        ? activeBorrowedItems
              .map(
                  (
                      item,
                  ) => `<div class="flex items-center justify-between text-xs text-slate-700">
          <span>- ${escapeHtml(item.name)} (${escapeHtml(item.id)})</span>
          <span class="font-bold text-[10px] uppercase ${item.status === 'Borrow' ? 'text-blue-600' : 'text-emerald-600'}">${escapeHtml(item.status ?? 'Borrow')}</span>
        </div>`,
              )
              .join('')
        : '<div class="text-xs text-slate-500">No active borrowed items.</div>';

    const selectedItems = new Map();
    let barcodeBuffer = '';
    let barcodeLastKeyTime = 0;
    let barcodeFinalizeTimer = null;
    const BARCODE_TIMEOUT = 300;
    const BARCODE_FINALIZE_DELAY = 120;

    const updateSelectedListUi = () => {
        const container = document.getElementById('borrow-scan-items');
        if (!container) return;
        container.innerHTML = renderBorrowList(
            Array.from(selectedItems.values()),
        );
    };

    const finalizeBarcode = () => {
        const scanned = barcodeBuffer.trim();
        barcodeBuffer = '';
        if (!scanned) return;

        const hint = document.getElementById('borrow-scan-hint');

        const currentBorrowerRfid = normalizeRfid(borrower?.rfid);
        const activeBorrowedItem = activeBorrowedItems.find(
            (item) => String(item?.barcode ?? '').trim() === scanned,
        );
        if (activeBorrowedItem) {
            if (hint) {
                hint.textContent = `${activeBorrowedItem.name} is already borrowed by this user.`;
                hint.style.color = '#b91c1c';
            }
            return;
        }

        const found = borrowCatalogMap.value.get(scanned);
        if (!found) {
            if (hint) {
                hint.textContent = `Barcode ${scanned} not found in inventory items.`;
                hint.style.color = '#b91c1c';
            }
            return;
        }

        const ownerRfid = borrowedBarcodeMap.value.get(scanned);
        if (ownerRfid && ownerRfid !== currentBorrowerRfid) {
            if (hint) {
                hint.textContent = `${found.name} is currently borrowed by another user.`;
                hint.style.color = '#b91c1c';
            }
            return;
        }

        const inventoryStatus = String(found.status ?? 'Available')
            .trim()
            .toLowerCase();
        if (inventoryStatus !== 'available') {
            if (hint) {
                hint.textContent = `${found.name} is currently ${found.status ?? 'Unavailable'}.`;
                hint.style.color = '#b91c1c';
            }
            return;
        }

        selectedItems.set(scanned, {
            ...found,
            requestedAction: 'borrow',
        });

        if (hint) {
            hint.textContent = `Queued to borrow: ${found.name} (${found.id})`;
            hint.style.color = '#1d4ed8';
        }

        updateSelectedListUi();
    };

    const barcodeKeydownHandler = (event) => {
        const currentKeyTime = Date.now();
        if (currentKeyTime - barcodeLastKeyTime > BARCODE_TIMEOUT) {
            barcodeBuffer = '';
        }
        barcodeLastKeyTime = currentKeyTime;

        if (SCAN_TERMINATORS.has(event.key)) {
            event.preventDefault();
            if (barcodeFinalizeTimer) {
                clearTimeout(barcodeFinalizeTimer);
                barcodeFinalizeTimer = null;
            }
            finalizeBarcode();
            return;
        }

        if (event.key.length === 1) {
            barcodeBuffer += event.key;

            if (barcodeFinalizeTimer) clearTimeout(barcodeFinalizeTimer);
            barcodeFinalizeTimer = window.setTimeout(() => {
                finalizeBarcode();
                barcodeFinalizeTimer = null;
            }, BARCODE_FINALIZE_DELAY);
        }
    };

    const wasListening = isListening.value;
    isListening.value = false;

    const swalPromise = Swal.fire({
        title: 'Borrow Item Mode',
        html: `
      <div style="font-family:'DM Sans',sans-serif; text-align:left; display:flex; flex-direction:column; gap:10px;">
        <div style="padding:10px; border-radius:12px; background:#f8fafc; border:1px solid #e2e8f0;">
          <div style="font-size:12px; font-weight:800; color:#0f172a;">${escapeHtml(borrower?.name ?? 'Unknown borrower')}</div>
          <div style="font-size:11px; color:#475569; margin-top:2px;">${escapeHtml(borrower?.role ?? 'Student')} | RFID: ${escapeHtml(borrower?.rfid ?? 'N/A')}</div>
        </div>

        <div style="padding:10px; border-radius:12px; background:#fff; border:1px solid #e2e8f0;">
          <div style="font-size:11px; font-weight:800; color:#334155; margin-bottom:6px;">Active Borrowed Items</div>
          ${borrowedItemsHtml}
        </div>

        <div style="padding:10px; border-radius:12px; background:#fff7ed; border:1px solid #fed7aa;">
          <div style="font-size:11px; font-weight:800; color:#9a3412;">Scan barcode to borrow only</div>
          <div id="borrow-scan-hint" style="margin-top:4px; font-size:11px; color:#7c2d12;">Waiting for barcode scan...</div>
        </div>

        <div>
          <div style="font-size:11px; font-weight:800; color:#334155; margin-bottom:6px;">Scanned Items</div>
          <div id="borrow-scan-items" style="display:flex; flex-direction:column; gap:6px;">${renderBorrowList(selectedItems)}</div>
        </div>
      </div>
    `,
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonText: 'Confirm Update',
        denyButtonText: 'Cancel Update',
        cancelButtonText: 'Close',
        confirmButtonColor: '#16a34a',
        denyButtonColor: '#dc2626',
        cancelButtonColor: '#64748b',
        didOpen: () => {
            window.addEventListener('keydown', barcodeKeydownHandler, true);
        },
        willClose: () => {
            window.removeEventListener('keydown', barcodeKeydownHandler, true);
            if (barcodeFinalizeTimer) {
                clearTimeout(barcodeFinalizeTimer);
                barcodeFinalizeTimer = null;
            }
            isListening.value = wasListening;
        },
    }).then(async (result) => {
        if (result.isConfirmed) {
            const payloadItems = Array.from(selectedItems.values());
            if (payloadItems.length === 0) {
                showStudentToast(borrower, 'No item scanned yet.', 'info');
                return;
            }

            const submitResult = await submitBorrowingUpdate(
                borrower,
                payloadItems,
            );
            if (!submitResult.ok) {
                lastAction.value = `${borrower?.name ?? 'Borrower'} update failed: ${submitResult.message}`;
                pushHistory(
                    'Borrow update failed',
                    `${borrower?.name ?? 'Borrower'} could not update borrow/return items.`,
                    'warning',
                );
                showStudentToast(borrower, submitResult.message, 'warning');
                return;
            }

            const borrowedCount = submitResult.borrowed.length;
            lastAction.value = `${borrower?.name ?? 'Borrower'} borrowed ${borrowedCount} item${borrowedCount === 1 ? '' : 's'}.`;
            pushHistory(
                'Borrow update saved',
                `${borrower?.name ?? 'Borrower'} borrowed ${borrowedCount} item${borrowedCount === 1 ? '' : 's'}.`,
                'success',
            );
            setTapHeadline('Borrow Updated');
            showStudentToast(borrower, submitResult.message, 'success');
            return;
        }

        if (result.isDenied) {
            lastAction.value = `${borrower?.name ?? 'Borrower'} cancelled borrow item scanning.`;
            pushHistory(
                'Borrow cancelled',
                `${borrower?.name ?? 'Borrower'} cancelled borrow item scanning.`,
                'warning',
            );
        }
    });

    lastAction.value = `${borrower.name} is ready for borrower processing.`;
    return swalPromise;
};

const triggerEmergencyCall = async (
    selectedType = null,
    selectedHotline = null,
) => {
    let emergencyType = selectedType;
    if (!emergencyType) {
        const options = {};
        (props.emergencyTypes ?? []).forEach((type) => {
            options[type.emergency_type_id] =
                `${type.name} (${type.category ?? 'general'})`;
        });

        const result = await Swal.fire({
            icon: 'warning',
            title: 'Select Emergency',
            input: 'select',
            inputOptions: options,
            inputPlaceholder: 'Choose emergency type',
            showCancelButton: true,
            confirmButtonText: 'Next',
            confirmButtonColor: '#dc2626',
        });

        if (!result.isConfirmed || !result.value) return;
        emergencyType = (props.emergencyTypes ?? []).find(
            (type) => String(type.emergency_type_id) === String(result.value),
        );
    }

    let emergencyHotline = selectedHotline;
    if (!emergencyHotline && (props.emergencyHotlines ?? []).length > 0) {
        const hotlineOptions = {
            none: 'No specific hotline',
        };
        (props.emergencyHotlines ?? []).forEach((hotline) => {
            hotlineOptions[hotline.emergency_hotline_id] =
                `${hotline.name} - ${hotline.phone_number}`;
        });

        const hotlineResult = await Swal.fire({
            icon: 'warning',
            title: 'Select Hotline',
            input: 'select',
            inputOptions: hotlineOptions,
            inputValue: 'none',
            showCancelButton: true,
            confirmButtonText: 'Send Emergency Text',
            confirmButtonColor: '#dc2626',
        });

        if (!hotlineResult.isConfirmed) return;
        emergencyHotline = (props.emergencyHotlines ?? []).find(
            (hotline) =>
                String(hotline.emergency_hotline_id) ===
                String(hotlineResult.value),
        );
    }

    if (!emergencyType) {
        showToast('warning', 'No emergency type selected');
        return;
    }

    const professorName = activeProfessor.value?.name ?? 'Instructor';
    const emergencyMessage =
        emergencyType.default_message ||
        `${emergencyType.name} emergency assistance requested.`;
    const hotlineNote = emergencyHotline
        ? ` Hotline: ${emergencyHotline.name} ${emergencyHotline.phone_number}.`
        : '';
    const panelMessage = `${emergencyMessage}${hotlineNote}`;

    try {
        const xsrfRaw = document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1];

        const response = await fetch(
            '/attendance-control-panel/emergency-alert',
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrfRaw ? decodeURIComponent(xsrfRaw) : '',
                },
                body: JSON.stringify({
                    emergency_type_id: emergencyType.emergency_type_id,
                    room: selectedRoom.value,
                    subject_code: activeProfessor.value?.subject_code ?? null,
                    schedule_id: activeProfessor.value?.schedule_id ?? null,
                    triggered_by_user_id:
                        activeProfessor.value?.user_id ?? null,
                    triggered_by_name: professorName,
                    message: panelMessage,
                    metadata: {
                        panel: 'attendance-control-panel',
                        mode: currentMode.value,
                        emergency_hotline_id:
                            emergencyHotline?.emergency_hotline_id ?? null,
                        emergency_hotline_name: emergencyHotline?.name ?? null,
                        emergency_hotline_phone:
                            emergencyHotline?.phone_number ?? null,
                        emergency_hotline_sms_enabled:
                            emergencyHotline?.sms_enabled ?? false,
                    },
                }),
            },
        );

        if (!response.ok) {
            throw new Error('Unable to save emergency alert.');
        }
    } catch {
        showToast('error', 'Emergency alert could not be saved');
        return;
    }

    lastAction.value = `${professorName} triggered ${emergencyType.name} from ${selectedRoom.value}.`;
    pushHistory(`${emergencyType.name} triggered`, panelMessage, 'warning');
    setTapHeadline('Emergency Call Triggered', 4000);
    syncPanelSessionState('attendance', {
        emergency_call: true,
        emergency_type: emergencyType.name,
        emergency_hotline: emergencyHotline?.name ?? null,
        emergency_called_at: new Date().toISOString(),
    });
    Swal.fire({
        icon: 'warning',
        title: `${emergencyType.name} Sent`,
        text: panelMessage,
        confirmButtonColor: '#dc2626',
    });
};

const showInstructorOptions = async () => {
    if (currentMode.value === 'borrowing' && borrowingEnabled.value) {
        const result = await Swal.fire({
            title: 'Instructor RFID detected',
            text: 'Choose the next action for this live class.',
            showConfirmButton: true,
            showDenyButton: true,
            showCancelButton: true,
            confirmButtonText: 'Student Logout',
            denyButtonText: 'Continue Class',
            cancelButtonText: 'Emergency Call',
            confirmButtonColor: '#0f766e',
            denyButtonColor: '#16a34a',
            cancelButtonColor: '#dc2626',
            reverseButtons: true,
            allowOutsideClick: false,
        });

        if (result.isConfirmed) {
            enableStudentLogoutMode();
            return;
        }

        if (result.isDenied) {
            forceStudentCheckoutNext.value = false;
            currentMode.value = 'attendance';
            lastAction.value = `${activeProfessor.value?.name ?? 'Instructor'} resumed attendance recording.`;
            pushHistory(
                'Attendance resumed',
                'Borrower mode was closed and attendance recording resumed.',
                'success',
            );
            syncPanelSessionState('attendance');
            showToast('success', 'Attendance mode resumed');
            return;
        }

        if (result.dismiss === Swal.DismissReason.cancel) {
            forceStudentCheckoutNext.value = false;
            triggerEmergencyCall();
        }
        return;
    }

    let selectedInstructorAction = 'student_logout';
    const result = await Swal.fire({
        title: 'Instructor RFID detected again',
        text: 'Choose the next action for this live session.',
        html: borrowingEnabled.value
            ? '<button type="button" id="instructor-borrowing-mode" class="swal2-styled" style="background:#d97706;">Borrowing Mode</button>'
            : undefined,
        showConfirmButton: true,
        showDenyButton: true,
        showCancelButton: true,
        confirmButtonText: 'Student Logout',
        denyButtonText: 'Continue Class',
        cancelButtonText: 'Emergency Call',
        confirmButtonColor: '#0f766e',
        denyButtonColor: '#16a34a',
        cancelButtonColor: '#dc2626',
        reverseButtons: true,
        allowOutsideClick: false,
        didOpen: () => {
            const borrowingButton = document.getElementById(
                'instructor-borrowing-mode',
            );
            borrowingButton?.addEventListener('click', () => {
                selectedInstructorAction = 'borrowing';
                Swal.clickConfirm();
            });
        },
        preConfirm: () => selectedInstructorAction,
    });

    if (result.isConfirmed) {
        if (result.value === 'student_logout') {
            enableStudentLogoutMode();
            return;
        }

        forceStudentCheckoutNext.value = false;
        currentMode.value = 'borrowing';
        lastAction.value = `${activeProfessor.value?.name ?? 'Instructor'} switched the panel to borrow item mode.`;
        pushHistory(
            'Borrow mode enabled',
            'Scans will now open borrower details and barcode item scanning.',
            'warning',
        );
        syncPanelSessionState('borrowing');
        showToast('success', 'Borrow item mode enabled');
        return;
    }

    if (result.isDenied) {
        forceStudentCheckoutNext.value = false;
        currentMode.value = 'attendance';
        lastAction.value = `${activeProfessor.value?.name ?? 'Instructor'} continued the class session.`;
        pushHistory(
            'Class continued',
            'Attendance recording remains active.',
            'success',
        );
        syncPanelSessionState('attendance');
        showToast('success', 'Class continued');
        return;
    }

    if (result.dismiss === Swal.DismissReason.cancel) {
        forceStudentCheckoutNext.value = false;
        triggerEmergencyCall();
    }
};

const logoutPanel = async () => {
    const roomToClose = selectedRoom.value;
    panelUnlocked.value = false;
    sessionActive.value = false;
    currentMode.value = 'idle';
    activeProfessor.value = null;
    activeStudent.value = null;
    forceStudentCheckoutNext.value = false;
    attendanceRecords.value = [];
    lastAction.value =
        'Waiting for an instructor RFID tap to begin attendance recording.';
    tapHeadline.value = defaultTapHeadline;
    if (activeStudentTimer) {
        clearTimeout(activeStudentTimer);
        activeStudentTimer = null;
    }
    if (tapHeadlineTimer) {
        clearTimeout(tapHeadlineTimer);
        tapHeadlineTimer = null;
    }
    clearPanelRuntime();
    selectedRoom.value = roomToClose;
    await syncPanelSessionState('offline');

    try {
        const xsrfRaw = document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1];

        const response = await fetch('/attendance-control-panel/logout', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrfRaw ? decodeURIComponent(xsrfRaw) : '',
            },
            body: JSON.stringify({ room: roomToClose }),
        });

        const payload = await response.json().catch(() => ({}));
        window.location.href =
            payload.redirect ?? '/attendance-control-panel/login';
    } catch {
        window.location.href = '/attendance-control-panel/login';
    }
};

const performForcedPanelLogout = async (
    message = 'This panel was logged out by an administrator.',
) => {
    const roomToClose = selectedRoom.value;
    clearPanelRuntime();
    panelUnlocked.value = false;
    sessionActive.value = false;
    currentMode.value = 'idle';
    forceStudentCheckoutNext.value = false;

    try {
        const xsrfRaw = document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1];

        const response = await fetch('/attendance-control-panel/logout', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrfRaw ? decodeURIComponent(xsrfRaw) : '',
            },
            body: JSON.stringify({ room: roomToClose }),
        });

        const payload = await response.json().catch(() => ({}));
        window.location.href =
            payload.redirect ?? '/attendance-control-panel/login';
    } catch {
        window.location.href = '/attendance-control-panel/login';
    }

    return message;
};

const checkPanelStatus = async () => {
    if (!panelUnlocked.value || !selectedRoom.value) return;

    try {
        const xsrfRaw = document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1];

        const response = await fetch('/attendance-control-panel/status', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrfRaw ? decodeURIComponent(xsrfRaw) : '',
            },
            body: JSON.stringify({ room: selectedRoom.value }),
        });

        const payload = await response.json().catch(() => null);
        if (payload?.featureSettings) {
            panelFeatureSettings.value = payload.featureSettings;
        }
        if (response.ok && payload?.logout_required) {
            await performForcedPanelLogout(payload.message);
        }
    } catch {
        // Keep the panel usable if the short status check fails.
    }
};

const handleRfidScan = async (rfid) => {
    if (!rfid) return;

    triggerPulse(rfid);
    const lookup = await lookupRfidFromServer(rfid);
    const professor = lookup?.type === 'instructor' ? lookup.profile : null;
    const student = lookup?.type === 'student' ? lookup.profile : null;
    const user = lookup?.type === 'user' ? lookup.profile : null;

    if (professor) {
        // Check if instructor has a valid schedule in this room at this time
        if (lookup?.type === 'instructor' && !lookup?.has_valid_schedule) {
            lastAction.value = `${professor.name} has no valid schedule for ${selectedRoom.value} at this time.`;
            pushHistory(
                'Schedule validation failed',
                `${professor.name} does not have a scheduled class in ${selectedRoom.value} right now.`,
                'warning',
            );
            Swal.fire({
                icon: 'warning',
                title: 'No Schedule Found',
                text: `${professor.name} does not have a scheduled class in ${selectedRoom.value} at this time. Please verify the instructor and time.`,
                confirmButtonColor: '#dc2626',
            });
            return;
        }

        if (!sessionActive.value) {
            startAttendanceSession(professor);
            return;
        }

        if (activeProfessor.value?.rfid !== professor.rfid) {
            lastAction.value = `${professor.name} attempted to access a session owned by ${activeProfessor.value?.name ?? 'another instructor'}.`;
            pushHistory(
                'Instructor mismatch',
                `${professor.name} does not match the active instructor for this session.`,
                'warning',
            );
            showToast(
                'warning',
                'Active session belongs to another instructor',
            );
            return;
        }

        await showInstructorOptions();
        return;
    }

    if (student) {
        if (!sessionActive.value) {
            lastAction.value = `${student.name} tapped before a professor opened a session.`;
            pushHistory(
                'Student tap blocked',
                'Attendance is locked until an instructor starts the class.',
                'warning',
            );
            showStudentToast(
                student,
                'Instructor RFID is required first.',
                'warning',
            );
            showStudentTemporarily(student);
            setTapHeadline('Instructor RFID Required');
            return;
        }

        if (currentMode.value === 'borrowing' && borrowingEnabled.value) {
            processBorrowerMode(student);
            return;
        }

        await recordAttendance(student);
        return;
    }

    if (user) {
        if (!sessionActive.value) {
            lastAction.value = `${user.name} tapped before a professor opened a session.`;
            pushHistory(
                'User tap blocked',
                'Attendance is locked until an instructor starts the class.',
                'warning',
            );
            showStudentToast(
                user,
                'Instructor RFID is required first.',
                'warning',
            );
            showStudentTemporarily(user);
            setTapHeadline('Instructor RFID Required');
            return;
        }

        if (currentMode.value !== 'borrowing' || !borrowingEnabled.value) {
            lastAction.value = `${user.name} scanned while attendance mode is active.`;
            pushHistory(
                'Borrow mode required',
                borrowingEnabled.value
                    ? `${user.name} must wait until borrow item mode is enabled.`
                    : 'Borrowing is currently disabled by an administrator.',
                'warning',
            );
            showStudentToast(
                user,
                borrowingEnabled.value
                    ? 'Enable Borrow Item mode first.'
                    : 'Borrowing is currently disabled.',
                'info',
            );
            setTapHeadline(
                borrowingEnabled.value
                    ? 'Borrow Mode Required'
                    : 'Borrowing Disabled',
            );
            return;
        }

        processBorrowerMode(user);
        return;
    }

    lastAction.value = `RFID ${rfid} is not registered in the current system records.`;
    pushHistory(
        'Unknown RFID',
        `No instructor or student record matched RFID ${rfid}.`,
        'warning',
    );
    Swal.fire({
        icon: 'warning',
        title: 'RFID not registered',
        text: `No instructor or student was found for RFID: ${rfid}`,
        confirmButtonColor: '#dc2626',
    });
};

const finalizeScan = () => {
    const scannedValue = rfidBuffer.trim();
    rfidBuffer = '';

    if (!scannedValue) return;
    handleRfidScan(scannedValue);
};

// RFID tag reading happens here: keyboard/scanner input is buffered and finalized into one tag value.
const handleKeydown = (event) => {
    if (!isListening.value) return;
    if (!panelUnlocked.value) return;

    const activeElement = document.activeElement;
    if (
        activeElement &&
        (activeElement.tagName === 'INPUT' ||
            activeElement.tagName === 'TEXTAREA' ||
            activeElement.tagName === 'SELECT' ||
            activeElement.isContentEditable)
    ) {
        return;
    }

    const currentKeyTime = Date.now();
    if (currentKeyTime - lastKeyTime > SCAN_TIMEOUT) {
        rfidBuffer = '';
    }

    lastKeyTime = currentKeyTime;

    if (SCAN_TERMINATORS.has(event.key)) {
        event.preventDefault();
        if (scanFinalizeTimer) {
            clearTimeout(scanFinalizeTimer);
            scanFinalizeTimer = null;
        }
        finalizeScan();
        return;
    }

    if (event.key.length === 1) {
        rfidBuffer += event.key;

        if (scanFinalizeTimer) clearTimeout(scanFinalizeTimer);
        scanFinalizeTimer = window.setTimeout(() => {
            finalizeScan();
            scanFinalizeTimer = null;
        }, AUTO_FINALIZE_DELAY);
    }
};

const toggleListening = () => {
    isListening.value = !isListening.value;
    lastAction.value = isListening.value
        ? 'RFID listening has resumed.'
        : 'RFID listening is paused.';

    if (sessionActive.value) {
        syncPanelSessionState(
            currentMode.value === 'borrowing' && borrowingEnabled.value
                ? 'borrowing'
                : 'attendance',
        );
        return;
    }

    syncPanelSessionState(isListening.value ? 'online' : 'paused');
};

const runDemoTap = () => {
    if (!sessionActive.value) {
        const demoInstructorRfid = props.demoInstructorRfids?.[0];
        if (!demoInstructorRfid) {
            showToast('warning', 'No seeded instructor RFID found');
            return;
        }

        handleRfidScan(demoInstructorRfid);
        return;
    }

    if (currentMode.value === 'borrowing' && borrowingEnabled.value) {
        const borrowerRfid =
            demoStudentRfids.value[1] ?? demoStudentRfids.value[0];
        if (!borrowerRfid) {
            showToast('warning', 'No seeded student RFID found');
            return;
        }
        handleRfidScan(borrowerRfid);
        return;
    }

    if (demoStudentRfids.value.length === 0) {
        showToast('warning', 'No seeded student RFID found');
        return;
    }

    const nextRfid =
        demoStudentRfids.value[
            demoStudentCursor % demoStudentRfids.value.length
        ];
    demoStudentCursor += 1;
    handleRfidScan(nextRfid);
};

const demoProfessorRetap = () => {
    // Re-tap active professor to open session controls.
    const currentInstructorRfid =
        activeProfessor.value?.rfid ?? props.demoInstructorRfids?.[0];
    if (!currentInstructorRfid) {
        showToast('warning', 'No seeded instructor RFID found');
        return;
    }

    handleRfidScan(currentInstructorRfid);
};

const runDemoStudentTap = () => {
    if (!sessionActive.value || currentMode.value !== 'attendance') {
        showToast('warning', 'Start attendance session first');
        return;
    }

    if (demoStudentRfids.value.length === 0) {
        showToast('warning', 'No seeded student RFID found');
        return;
    }

    const nextRfid =
        demoStudentRfids.value[
            demoStudentCursor % demoStudentRfids.value.length
        ];
    demoStudentCursor += 1;
    handleRfidScan(nextRfid);
};

const runDemoBorrowTap = () => {
    if (!sessionActive.value) {
        showToast('warning', 'Start attendance session first');
        return;
    }

    if (!borrowingEnabled.value) {
        showToast('info', 'Borrowing is currently disabled');
        return;
    }

    if (currentMode.value !== 'borrowing') {
        currentMode.value = 'borrowing';
        syncPanelSessionState('borrowing');
        showToast('success', 'Borrow item mode enabled');
    }

    const nextRfid =
        demoBorrowerRfids.value[
            demoStudentCursor % Math.max(demoBorrowerRfids.value.length, 1)
        ];
    if (!nextRfid) {
        showToast('warning', 'No borrower RFID found for demo');
        return;
    }

    demoStudentCursor += 1;
    handleRfidScan(nextRfid);
};

onMounted(() => {
    if (!props.panelRoom) {
        window.location.href = '/attendance-control-panel/login';
        return;
    }

    try {
        selectedRoom.value = props.panelRoom;
        panelUnlocked.value = true;

        const panelRuntimeRaw = localStorage.getItem(PANEL_RUNTIME_KEY);
        if (panelRuntimeRaw) {
            const panelRuntime = JSON.parse(panelRuntimeRaw);
            sessionActive.value = Boolean(panelRuntime.sessionActive);
            const restoredMode =
                panelRuntime.currentMode ||
                (panelRuntime.sessionActive ? 'attendance' : 'idle');
            currentMode.value =
                restoredMode === 'borrowing' && !borrowingEnabled.value
                    ? 'attendance'
                    : restoredMode;
            isListening.value = panelRuntime.isListening ?? true;
            activeProfessor.value = panelRuntime.activeProfessor ?? null;
            if (panelRuntime.lastAction) {
                lastAction.value = panelRuntime.lastAction;
            }

            if (
                panelRuntime.sessionActive &&
                currentMode.value === 'attendance'
            ) {
                loadAttendanceLogsFromServer();
            }
        }

        if (!sessionActive.value) {
            syncPanelSessionState('online');
        }
    } catch {
        window.location.href = '/attendance-control-panel/login';
        return;
    }

    updateClock();
    timeTicker = window.setInterval(updateClock, 1000);
    panelStatusTicker = window.setInterval(checkPanelStatus, 5000);
    window.addEventListener('keydown', handleKeydown, true);
});

onUnmounted(() => {
    if (timeTicker) clearInterval(timeTicker);
    if (panelStatusTicker) clearInterval(panelStatusTicker);
    window.removeEventListener('keydown', handleKeydown, true);
    if (scanFinalizeTimer) clearTimeout(scanFinalizeTimer);
    if (activeStudentTimer) clearTimeout(activeStudentTimer);
    if (tapHeadlineTimer) clearTimeout(tapHeadlineTimer);
    if (captureResetTimer) clearTimeout(captureResetTimer);
});

watch(
    [sessionActive, currentMode, activeProfessor, isListening, selectedRoom],
    () => {
        if (!panelUnlocked.value) return;
        persistPanelRuntime();
    },
    { deep: true },
);

watch(
    [selectedRoom, currentMode, activeProfessor],
    ([room, mode, professor]) => {
        if (
            !panelUnlocked.value ||
            !room ||
            mode !== 'attendance' ||
            !professor
        )
            return;
        loadAttendanceLogsFromServer();
    },
    { deep: true },
);
</script>

<template>
    <!-- Main Panel -->
    <div
        v-if="panelUnlocked"
        class="min-h-screen w-full bg-[#f5f6fa] p-4 sm:p-5 lg:p-6"
    >
        <div
            class="grid min-h-[calc(100vh-2rem)] w-full gap-4 sm:min-h-[calc(100vh-2.5rem)] lg:min-h-[calc(100vh-3rem)] lg:grid-rows-[auto_1fr_auto]"
            :class="
                attendeesSlideVisible
                    ? 'lg:grid-cols-[1.3fr_0.9fr_0.85fr]'
                    : 'lg:grid-cols-[1.45fr_0.9fr]'
            "
        >
            <section
                class="rounded-[18px] bg-white px-5 py-3 shadow-sm ring-1 ring-slate-200/70"
                :class="
                    attendeesSlideVisible ? 'lg:col-span-3' : 'lg:col-span-2'
                "
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-4">
                        <div>
                            <div
                                class="text-[10px] font-bold tracking-[0.28em] text-slate-400 uppercase"
                            >
                                Professor Session
                            </div>
                            <div
                                class="mt-0.5 text-lg font-bold text-slate-900"
                            >
                                {{
                                    activeProfessor
                                        ? activeProfessor.name
                                        : 'Waiting for professor RFID tap…'
                                }}
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <div
                            class="rounded-xl bg-slate-50 px-3 py-2 ring-1 ring-slate-200"
                        >
                            <div
                                class="text-[9px] font-bold tracking-[0.22em] text-slate-400 uppercase"
                            >
                                Subject
                            </div>
                            <div class="text-xs font-semibold text-slate-700">
                                {{
                                    activeProfessor
                                        ? activeProfessor.subject
                                        : '—'
                                }}
                            </div>
                        </div>
                        <div
                            class="rounded-xl bg-slate-50 px-3 py-2 ring-1 ring-slate-200"
                        >
                            <div
                                class="text-[9px] font-bold tracking-[0.22em] text-slate-400 uppercase"
                            >
                                Section
                            </div>
                            <div class="text-xs font-semibold text-slate-700">
                                {{
                                    activeProfessor
                                        ? activeProfessor.section
                                        : '—'
                                }}
                            </div>
                        </div>
                        <div
                            class="rounded-xl bg-slate-50 px-3 py-2 ring-1 ring-slate-200"
                        >
                            <div
                                class="text-[9px] font-bold tracking-[0.22em] text-slate-400 uppercase"
                            >
                                Schedule
                            </div>
                            <div class="text-xs font-semibold text-slate-700">
                                {{
                                    sessionActive &&
                                    currentMode === 'attendance'
                                        ? activeProfessorSchedule
                                        : '—'
                                }}
                            </div>
                        </div>
                        <div
                            class="rounded-xl bg-slate-50 px-3 py-2 ring-1 ring-slate-200"
                        >
                            <div
                                class="text-[9px] font-bold tracking-[0.22em] text-slate-400 uppercase"
                            >
                                Students
                            </div>
                            <div class="text-xs font-semibold text-slate-700">
                                {{ attendanceCount }}
                            </div>
                        </div>
                        <div
                            class="rounded-xl bg-slate-50 px-3 py-2 ring-1 ring-slate-200"
                        >
                            <div
                                class="text-[9px] font-bold tracking-[0.22em] text-slate-400 uppercase"
                            >
                                Room
                            </div>
                            <div class="text-xs font-semibold text-slate-700">
                                {{ selectedRoom }}
                            </div>
                        </div>
                        <div
                            class="rounded-xl bg-[#123456] px-3 py-2 text-white shadow-sm"
                        >
                            <div
                                class="text-[9px] font-bold tracking-[0.22em] text-white/55 uppercase"
                            >
                                Time
                            </div>
                            <div class="text-xs font-semibold">
                                {{ currentTime }}
                            </div>
                        </div>
                        <button
                            type="button"
                            class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 transition hover:bg-red-100"
                            title="Logout and change room"
                            @click="logoutPanel"
                        >
                            Logout
                        </button>
                    </div>
                </div>
            </section>

            <section
                class="flex min-h-165 flex-col rounded-[22px] bg-white p-5 shadow-sm ring-1 ring-slate-200/70 lg:row-span-1"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <div
                            class="text-[11px] font-bold tracking-[0.28em] text-slate-400 uppercase"
                        >
                            Scanner Result
                        </div>
                        <h2
                            class="mt-2 text-[28px] font-extrabold text-slate-900"
                        >
                            Tap RFID Card
                        </h2>
                    </div>
                    <div
                        class="rounded-full px-3 py-1 text-xs font-bold"
                        :class="modeClass"
                    >
                        {{ sessionActive ? 'Live' : 'Waiting' }}
                    </div>
                </div>

                <div
                    class="mt-5 flex flex-1 flex-col rounded-[28px] bg-[radial-gradient(circle_at_top,rgba(37,99,235,0.16),transparent_56%),linear-gradient(180deg,#f8fbff_0%,#eef4ff_100%)] p-5 ring-1 ring-blue-100"
                >
                    <div
                        class="flex flex-1 flex-col items-center justify-center text-center"
                    >
                        <div
                            class="flex h-28 w-28 items-center justify-center rounded-[30px] shadow-lg transition-all duration-300"
                            :class="
                                scanPulse
                                    ? 'scale-110 bg-emerald-500 text-white'
                                    : 'bg-[#123456] text-white'
                            "
                        >
                            <svg
                                class="h-14 w-14"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                viewBox="0 0 24 24"
                            >
                                <rect
                                    x="2.5"
                                    y="5"
                                    width="19"
                                    height="14"
                                    rx="2.5"
                                ></rect>
                                <rect
                                    x="5.5"
                                    y="9"
                                    width="5"
                                    height="4"
                                    rx="1"
                                ></rect>
                                <path
                                    stroke-linecap="round"
                                    d="M15 10.5a1.6 1.6 0 0 1 0 3"
                                ></path>
                                <path
                                    stroke-linecap="round"
                                    d="M17.4 8.4a4.1 4.1 0 0 1 0 7.2"
                                ></path>
                            </svg>
                        </div>
                        <div
                            class="mt-5 text-[32px] leading-none font-extrabold text-slate-900"
                        >
                            {{ tapHeadline }}
                        </div>
                        <div
                            class="mt-3 max-w-105 text-sm leading-6 text-slate-500"
                        >
                            {{ statusSubline }}
                        </div>
                        <div
                            v-if="forceStudentCheckoutNext"
                            class="mt-4 rounded-2xl border border-teal-200 bg-teal-50 px-4 py-3 text-sm font-bold text-teal-700"
                        >
                            Student Logout Mode: next student tap records
                            official check-out.
                        </div>
                    </div>

                    <div
                        class="mt-6 rounded-[22px] bg-white/90 p-4 ring-1 ring-slate-200"
                    >
                        <div
                            class="text-[10px] font-bold tracking-[0.22em] text-slate-400 uppercase"
                        >
                            Scan Result
                        </div>
                        <div class="mt-2 text-lg font-extrabold text-slate-900">
                            {{ statusHeadline }}
                        </div>
                        <div class="mt-2 text-sm leading-6 text-slate-600">
                            {{ lastAction }}
                        </div>
                        <div
                            class="mt-4 flex items-center justify-between gap-3"
                        >
                            <div
                                class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white"
                            >
                                Last RFID: {{ lastScanned || 'Waiting...' }}
                            </div>
                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    class="rounded-xl bg-[#123456] px-4 py-2 text-xs font-bold text-white transition hover:bg-[#0e2840]"
                                    @click="runDemoTap"
                                >
                                    {{ actionButtonLabel }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50"
                                    @click="demoProfessorRetap"
                                >
                                    Professor Re-Tap
                                </button>
                                <button
                                    type="button"
                                    class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-xs font-bold text-emerald-700 transition hover:bg-emerald-100"
                                    @click="runDemoStudentTap"
                                >
                                    Student Attendance
                                </button>
                                <button
                                    type="button"
                                    class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2 text-xs font-bold text-amber-700 transition hover:bg-amber-100"
                                    @click="runDemoBorrowTap"
                                >
                                    Borrow Item Demo
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section
                class="flex min-h-165 flex-col rounded-[22px] bg-white p-5 shadow-sm ring-1 ring-slate-200/70 lg:row-span-2"
            >
                <div
                    class="text-[11px] font-bold tracking-[0.28em] text-slate-400 uppercase"
                >
                    Student Information
                </div>

                <div class="mt-4 flex flex-col items-center gap-3 pt-2">
                    <CameraCapture ref="cameraRef" class="h-36 w-36" />
                    <div class="text-center">
                        <div class="text-xl font-extrabold text-slate-900">
                            {{
                                activeStudent
                                    ? activeStudent.name
                                    : 'No student scanned yet'
                            }}
                        </div>
                        <div class="mt-1 text-sm text-slate-500">
                            {{
                                activeStudent
                                    ? activeStudent.studentId
                                    : 'Student details appear after tap.'
                            }}
                        </div>
                    </div>
                </div>

                <div class="mt-4 space-y-3">
                    <div
                        class="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-3 ring-1 ring-slate-200"
                    >
                        <span class="text-sm font-semibold text-slate-500"
                            >Year</span
                        >
                        <span class="text-sm font-bold text-slate-800">{{
                            activeStudent ? activeStudent.year : 'Waiting...'
                        }}</span>
                    </div>
                    <div
                        class="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-3 ring-1 ring-slate-200"
                    >
                        <span class="text-sm font-semibold text-slate-500"
                            >Strand</span
                        >
                        <span class="text-sm font-bold text-slate-800">{{
                            activeStudent ? activeStudent.strand : 'Waiting...'
                        }}</span>
                    </div>
                    <div
                        class="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-3 ring-1 ring-slate-200"
                    >
                        <span class="text-sm font-semibold text-slate-500"
                            >Section</span
                        >
                        <span class="text-sm font-bold text-slate-800">{{
                            activeStudent ? activeStudent.section : 'Waiting...'
                        }}</span>
                    </div>
                    <div
                        class="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-3 ring-1 ring-slate-200"
                    >
                        <span class="text-sm font-semibold text-slate-500"
                            >Status</span
                        >
                        <span
                            class="text-sm font-bold"
                            :class="
                                sessionActive
                                    ? 'text-emerald-600'
                                    : 'text-slate-500'
                            "
                            >{{
                                activeStudent ? 'Recognized' : 'Waiting'
                            }}</span
                        >
                    </div>
                </div>
            </section>

            <section
                v-if="attendeesSlideVisible"
                class="flex min-h-165 flex-col rounded-[22px] bg-white p-5 shadow-sm ring-1 ring-slate-200/70 lg:row-span-2"
            >
                <div class="border-b border-slate-200 pb-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#123456] text-white shadow-sm"
                            >
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M16 19a4 4 0 0 0-8 0"
                                    ></path>
                                    <circle cx="12" cy="9" r="3.2"></circle>
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M7 19H4.5A1.5 1.5 0 0 1 3 17.5v-11A1.5 1.5 0 0 1 4.5 5h15A1.5 1.5 0 0 1 21 6.5v11a1.5 1.5 0 0 1-1.5 1.5H17"
                                    ></path>
                                </svg>
                            </div>
                            <div>
                                <div
                                    class="text-[10px] font-bold tracking-[0.24em] text-slate-400 uppercase"
                                >
                                    Attendance Nav
                                </div>
                                <h3
                                    class="mt-1 text-xl font-extrabold text-slate-900"
                                >
                                    Attendance Taps
                                </h3>
                            </div>
                        </div>
                        <div
                            class="rounded-lg bg-emerald-50 px-2.5 py-1.5 text-xs font-semibold text-emerald-700"
                        >
                            Live list
                        </div>
                    </div>
                    <div
                        class="mt-3 inline-flex items-center gap-2 rounded-xl bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700"
                    >
                        <span>Total:</span>
                        <span class="font-bold">{{ attendanceCount }}</span>
                    </div>
                </div>

                <div class="mt-4 flex-1 overflow-y-auto">
                    <div
                        v-if="attendanceRecords.length === 0"
                        class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center"
                    >
                        <div class="mb-2 flex justify-center">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-200 text-slate-500"
                            >
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M16 19a4 4 0 0 0-8 0"
                                    ></path>
                                    <circle cx="12" cy="9" r="3.2"></circle>
                                </svg>
                            </div>
                        </div>
                        <div class="text-sm font-semibold text-slate-600">
                            No attendance yet
                        </div>
                        <div class="mt-1 text-xs text-slate-500">
                            Students will appear here after their RFID tap is
                            recorded.
                        </div>
                    </div>

                    <ul v-else class="space-y-3">
                        <li
                            v-for="record in attendanceRecords"
                            :key="record.id"
                            class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm"
                        >
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <div>
                                    <div
                                        class="text-sm font-bold text-slate-900"
                                    >
                                        {{ record.name }}
                                    </div>
                                    <div class="mt-0.5 text-xs text-slate-500">
                                        {{ record.course }} •
                                        {{ record.section }}
                                    </div>
                                </div>
                                <div
                                    class="rounded-lg bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-700"
                                >
                                    {{ record.status }}
                                </div>
                            </div>
                            <div
                                class="mt-2 flex flex-wrap items-center justify-between gap-2 text-[11px] text-slate-500"
                            >
                                <span>RFID: {{ record.rfid }}</span>
                                <span
                                    >{{ record.tap_type || 'Check-in' }} Â·
                                    {{ record.time }}</span
                                >
                            </div>
                            <div
                                class="mt-2 grid grid-cols-3 gap-2 rounded-xl bg-slate-50 px-3 py-2 text-[11px] text-slate-600"
                            >
                                <span>In: {{ record.time_in || '-' }}</span>
                                <span>Out: {{ record.time_out || '-' }}</span>
                                <span>{{
                                    record.room_status || 'Inside'
                                }}</span>
                            </div>
                        </li>
                    </ul>
                </div>
            </section>
        </div>
        <!-- Floating RFID Toggle (from Borrow.vue) -->
        <button
            type="button"
            class="fixed right-6 bottom-6 z-50 flex cursor-pointer items-center gap-2 rounded-full px-4 py-2.5 text-sm font-semibold shadow-lg transition-all duration-300 select-none"
            :class="
                scanPulse
                    ? 'scale-110 bg-emerald-500 text-white'
                    : !isListening
                      ? 'border border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100'
                      : currentMode === 'borrowing'
                        ? 'border border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100'
                        : currentMode === 'attendance'
                          ? 'border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100'
                          : 'border border-gray-200 bg-white text-gray-700 hover:border-emerald-300 hover:bg-emerald-50'
            "
            :title="
                isListening
                    ? 'Click to pause RFID scanner'
                    : 'Click to resume RFID scanner'
            "
            @click="!scanPulse && toggleListening()"
        >
            <svg
                class="h-5 w-5"
                :class="
                    currentMode === 'borrowing'
                        ? 'text-amber-500'
                        : isListening
                          ? 'text-emerald-500'
                          : 'text-amber-400'
                "
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                viewBox="0 0 24 24"
            >
                <rect
                    x="2"
                    y="5"
                    width="20"
                    height="14"
                    rx="2"
                    stroke-width="1.8"
                />
                <rect
                    x="5"
                    y="9"
                    width="5"
                    height="4"
                    rx="1"
                    stroke-width="1.5"
                />
                <path
                    stroke-linecap="round"
                    stroke-width="1.5"
                    d="M15 10.5a1.5 1.5 0 0 1 0 3"
                />
                <path
                    stroke-linecap="round"
                    stroke-width="1.5"
                    d="M17.5 8.5a4 4 0 0 1 0 7"
                />
            </svg>
            <span>{{
                scanPulse
                    ? 'Scanned!'
                    : !isListening
                      ? 'Scanner Paused'
                      : sessionActive
                        ? modeLabel
                        : 'RFID Listening'
            }}</span>
            <span
                v-if="isListening && !scanPulse"
                class="absolute top-2 right-2 h-2 w-2 animate-ping rounded-full bg-emerald-500"
            ></span>
        </button>
    </div>
    <div v-else class="min-h-screen w-full bg-[#f5f6fa]"></div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700;800&display=swap');

.control-panel-popup {
    border-radius: 22px !important;
    padding: 24px !important;
    max-width: 520px !important;
    font-family: 'DM Sans', sans-serif !important;
}

.control-panel-confirm {
    border-radius: 12px !important;
    font-family: 'DM Sans', sans-serif !important;
    font-weight: 700 !important;
    padding: 10px 24px !important;
}

.swal2-toast.hover-fade-toast {
    transition: opacity 180ms ease;
}

.swal2-toast.hover-fade-toast:hover {
    opacity: 0;
}

.attendee-drop-enter-active {
    transition: all 260ms ease;
}

.attendee-drop-enter-from {
    opacity: 0;
    transform: translateY(-14px) scale(0.98);
}

.attendee-drop-move {
    transition: transform 260ms ease;
}
</style>
