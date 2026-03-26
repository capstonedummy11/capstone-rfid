<script setup>
defineOptions({
  layout: null,
});

import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
  rooms: {
    type: Array,
    default: () => ['Computer Lab 1', 'Computer Lab 2', 'RFID Laboratory', 'Network Lab'],
  },
  studentToastSeconds: {
    type: Number,
    default: 15,
  },
  studentInfoVisibleSeconds: {
    type: Number,
    default: 10,
  },
});

// --- Panel gate / access control ---
const panelUnlocked = ref(false);
const selectedRoom = ref('');
const gateStep = ref('pin'); // 'pin' | 'room'
const pinVerified = ref(false);
const attendeesDrawerOpen = ref(false);
const pinValue = ref('');
const pinError = ref('');
const pinLoading = ref(false);
const roomSearch = ref('');

const filteredRooms = computed(() => {
  if (!roomSearch.value) return props.rooms;
  const query = roomSearch.value.toLowerCase();
  return props.rooms.filter((room) => room.toLowerCase().includes(query));
});

const instructorProfiles = [
  {
    id: 1,
    name: 'Prof. Andrea Cruz',
    rfid: 'INS-1001',
    role: 'instructor',
    subject: 'Systems Analysis and Design',
    section: 'BSIT 3A',
    course: 'Bachelor of Science in Information Technology',
    schedule: 'Mon 8:00 AM - 10:00 AM',
    room: 'RFID Laboratory',
  },
  {
    id: 2,
    name: 'Prof. Miguel Santos',
    rfid: 'INS-1002',
    role: 'instructor',
    subject: 'Database Management Systems',
    section: 'BSCS 2B',
    course: 'Bachelor of Science in Computer Science',
    schedule: 'Tue 1:00 PM - 3:00 PM',
    room: 'Computer Lab 2',
  },
];

const studentProfiles = [
  {
    id: 1,
    studentId: '2023-0001',
    name: 'Maria Santos',
    rfid: 'STU-2001',
    year: '3rd Year',
    course: 'BSIT',
    section: '3A',
    avatarSeed: 'Maria Santos',
  },
  {
    id: 2,
    studentId: '2023-0002',
    name: 'Juan Dela Cruz',
    rfid: 'STU-2002',
    year: '3rd Year',
    course: 'BSIT',
    section: '3A',
    avatarSeed: 'Juan Dela Cruz',
  },
  {
    id: 3,
    studentId: '2024-0108',
    name: 'Angela Reyes',
    rfid: 'STU-2003',
    year: '2nd Year',
    course: 'BSCS',
    section: '2B',
    avatarSeed: 'Angela Reyes',
  },
  {
    id: 4,
    studentId: '2024-0112',
    name: 'Carlo Mendoza',
    rfid: 'STU-2004',
    year: '2nd Year',
    course: 'BSCS',
    section: '2B',
    avatarSeed: 'Carlo Mendoza',
  },
];

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
const defaultTapHeadline = 'Tap RFID';
const tapHeadline = ref(defaultTapHeadline);
const lastAction = ref('Waiting for an instructor RFID tap to begin attendance recording.');
const attendanceRecords = ref([]);
const scanHistory = ref([
  {
    id: 'boot',
    title: 'Scanner ready',
    subtitle: 'The panel is listening for RFID input.',
    time: new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }),
    tone: 'neutral',
  },
]);

let timeTicker = null;
let rfidBuffer = '';
let lastKeyTime = 0;
let scanFinalizeTimer = null;
let activeStudentTimer = null;
let tapHeadlineTimer = null;

const SCAN_TIMEOUT = 300;
const AUTO_FINALIZE_DELAY = 120;
const SCAN_TERMINATORS = new Set(['Enter', 'NumpadEnter', 'Tab']);
const studentToastSeconds = Number(props.studentToastSeconds ?? 15);
const STUDENT_TOAST_MS = Number.isFinite(studentToastSeconds) && studentToastSeconds > 0
  ? studentToastSeconds * 1000
  : 15000;
const studentInfoVisibleSeconds = Number(props.studentInfoVisibleSeconds ?? 10);
const STUDENT_INFO_VISIBLE_MS = Number.isFinite(studentInfoVisibleSeconds) && studentInfoVisibleSeconds > 0
  ? studentInfoVisibleSeconds * 1000
  : 10000;

const modeLabel = computed(() => {
  if (currentMode.value === 'attendance') return 'Attendance Mode';
  if (currentMode.value === 'borrowing') return 'Borrowing Mode';
  return 'Idle Mode';
});

const modeClass = computed(() => systemModeStyles[currentMode.value] || systemModeStyles.idle);

const attendanceCount = computed(() => attendanceRecords.value.length);

const attendancePercentage = computed(() => {
  if (!activeProfessor.value) return 0;

  const matchingStudents = studentProfiles.filter((student) => {
    const professorSection = activeProfessor.value.section.split(' ').at(-1)?.toUpperCase();
    return student.section.toUpperCase() === professorSection;
  });

  if (matchingStudents.length === 0) return 0;

  return Math.round((attendanceRecords.value.length / matchingStudents.length) * 100);
});

const statusHeadline = computed(() => {
  if (!sessionActive.value) return 'Tap RFID Card';
  if (currentMode.value === 'borrowing') return 'Borrower verification is active';
  return 'Attendance recording is live';
});

const statusSubline = computed(() => {
  if (!sessionActive.value) return 'Professor tap starts the session.';
  if (currentMode.value === 'borrowing') return 'Tap the professor card again to resume attendance or end the session.';
  return 'Students can now tap their RFID cards to be marked present.';
});

const actionButtonLabel = computed(() => {
  if (!sessionActive.value) return 'Demo Instructor Tap';
  if (currentMode.value === 'borrowing') return 'Demo Borrower Tap';
  return 'Demo Student Tap';
});

const recentAttendance = computed(() => [...attendanceRecords.value].slice(0, 6));

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
    time: new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }),
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

const normalizeRfid = (value) => String(value ?? '').trim().toLowerCase();

const escapeHtml = (value = '') => String(value)
  .replaceAll('&', '&amp;')
  .replaceAll('<', '&lt;')
  .replaceAll('>', '&gt;')
  .replaceAll('"', '&quot;')
  .replaceAll("'", '&#039;');

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

const findInstructor = (rfid) => instructorProfiles.find((profile) => normalizeRfid(profile.rfid) === normalizeRfid(rfid)) || null;
const findStudent = (rfid) => studentProfiles.find((profile) => normalizeRfid(profile.rfid) === normalizeRfid(rfid)) || null;

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
          src="https://api.dicebear.com/7.x/personas/svg?seed=${encodeURIComponent(student?.avatarSeed ?? student?.name ?? 'rfid-student')}&backgroundColor=b6e3f4"
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
  attendanceRecords.value = [];
  tapHeadline.value = defaultTapHeadline;
  lastAction.value = `${professor.name} started attendance recording for ${professor.subject}.`;
  pushHistory('Attendance started', `${professor.name} opened ${professor.subject} for ${professor.section}.`, 'success');
  showToast('success', 'Attendance recording started');
};

const endAttendanceSession = () => {
  if (!activeProfessor.value) return;

  pushHistory(
    'Attendance ended',
    `${activeProfessor.value.name} closed the session with ${attendanceRecords.value.length} recorded student tap${attendanceRecords.value.length === 1 ? '' : 's'}.`,
    'warning',
  );

  lastAction.value = 'Attendance session ended. Waiting for the next instructor RFID tap.';
  activeProfessor.value = null;
  activeStudent.value = null;
  attendanceRecords.value = [];
  sessionActive.value = false;
  currentMode.value = 'idle';
  tapHeadline.value = defaultTapHeadline;
  showToast('info', 'Attendance session ended');
};

const recordAttendance = (student) => {
  showStudentTemporarily(student);

  const alreadyRecorded = attendanceRecords.value.some((entry) => entry.rfid === student.rfid);
  if (alreadyRecorded) {
    lastAction.value = `${student.name} already has an attendance record for this session.`;
    pushHistory('Duplicate tap ignored', `${student.name} is already marked present.`, 'warning');
    showStudentToast(student, 'Already marked present for this session.', 'info');
    setTapHeadline('Already Recorded');
    return;
  }

  const timestamp = new Date().toLocaleTimeString('en-US', {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  });

  attendanceRecords.value.unshift({
    id: `${student.id}-${Date.now()}`,
    rfid: student.rfid,
    name: student.name,
    year: student.year,
    course: student.course,
    section: student.section,
    time: timestamp,
    status: 'Present',
  });

  lastAction.value = `${student.name} was recorded present at ${timestamp}.`;
  pushHistory('Attendance recorded', `${student.name} tapped in at ${timestamp}.`, 'success');
  setTapHeadline('Attendance successfully recorded', STUDENT_TOAST_MS);
  showStudentToast(student, 'Attendance successfully recorded.', 'success');
};

const processBorrowerMode = (student) => {
  showStudentTemporarily(student);
  lastAction.value = `${student.name} is ready for borrower processing.`;
  pushHistory('Borrower verified', `${student.name} was recognized for borrower mode.`, 'success');
  showStudentToast(student, 'Borrower mode ready.', 'success');
  setTapHeadline('Borrower Verified');
};

const showInstructorOptions = async () => {
  const borrowActionLabel = currentMode.value === 'borrowing' ? 'Resume Attendance' : 'Borrower Mode';

  const result = await Swal.fire({
    title: 'Instructor RFID detected again',
    text: 'Choose the next action for this live session.',
    showConfirmButton: true,
    showDenyButton: true,
    showCancelButton: true,
    confirmButtonText: borrowActionLabel,
    denyButtonText: 'End Attendance Recording',
    cancelButtonText: 'Keep Current Session',
    confirmButtonColor: currentMode.value === 'borrowing' ? '#16a34a' : '#d97706',
    denyButtonColor: '#dc2626',
    cancelButtonColor: '#64748b',
    reverseButtons: true,
  });

  if (result.isConfirmed) {
    if (currentMode.value === 'borrowing') {
      currentMode.value = 'attendance';
      lastAction.value = `${activeProfessor.value?.name ?? 'Instructor'} resumed attendance recording.`;
      pushHistory('Attendance resumed', 'Borrower mode was closed and attendance recording resumed.', 'success');
      showToast('success', 'Attendance mode resumed');
      return;
    }

    currentMode.value = 'borrowing';
    lastAction.value = `${activeProfessor.value?.name ?? 'Instructor'} switched the panel to borrower mode.`;
    pushHistory('Borrower mode enabled', 'Student scans will now prepare borrower details.', 'warning');
    showToast('success', 'Borrower mode enabled');
    return;
  }

  if (result.isDenied) {
    endAttendanceSession();
  }
};

const verifyPin = async () => {
  if (!pinValue.value) return;
  pinError.value = '';
  pinLoading.value = true;
  try {
    const xsrfRaw = document.cookie
      .split('; ')
      .find((row) => row.startsWith('XSRF-TOKEN='))
      ?.split('=')[1];
    const response = await fetch('/panel-verify', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-XSRF-TOKEN': xsrfRaw ? decodeURIComponent(xsrfRaw) : '',
      },
      body: JSON.stringify({ pin: pinValue.value }),
    });
    if (response.ok) {
      pinVerified.value = true;
      gateStep.value = 'room';
    } else {
      const data = await response.json().catch(() => ({}));
      pinError.value = data.message ?? 'Incorrect PIN. Please try again.';
      pinValue.value = '';
      pinVerified.value = false;
    }
  } catch {
    pinError.value = 'Connection error. Please retry.';
    pinVerified.value = false;
  } finally {
    pinLoading.value = false;
  }
};

const unlockPanel = () => {
  if (!selectedRoom.value || !pinVerified.value) return;

  localStorage.setItem('panelAuth', JSON.stringify({
    unlocked: true,
    room: selectedRoom.value,
    timestamp: Date.now(),
  }));
  panelUnlocked.value = true;
};

const logoutPanel = () => {
  panelUnlocked.value = false;
  attendeesDrawerOpen.value = false;
  selectedRoom.value = '';
  gateStep.value = 'pin';
  pinVerified.value = false;
  pinValue.value = '';
  pinError.value = '';
  roomSearch.value = '';
  sessionActive.value = false;
  currentMode.value = 'idle';
  activeProfessor.value = null;
  activeStudent.value = null;
  attendanceRecords.value = [];
  lastAction.value = 'Waiting for an instructor RFID tap to begin attendance recording.';
  tapHeadline.value = defaultTapHeadline;
  if (activeStudentTimer) {
    clearTimeout(activeStudentTimer);
    activeStudentTimer = null;
  }
  if (tapHeadlineTimer) {
    clearTimeout(tapHeadlineTimer);
    tapHeadlineTimer = null;
  }
  localStorage.removeItem('panelAuth');
};

const handleRfidScan = async (rfid) => {
  if (!rfid) return;

  triggerPulse(rfid);
  const professor = findInstructor(rfid);
  const student = findStudent(rfid);

  if (professor) {
    if (!sessionActive.value) {
      startAttendanceSession(professor);
      return;
    }

    if (activeProfessor.value?.rfid !== professor.rfid) {
      lastAction.value = `${professor.name} attempted to access a session owned by ${activeProfessor.value?.name ?? 'another instructor'}.`;
      pushHistory('Instructor mismatch', `${professor.name} does not match the active instructor for this session.`, 'warning');
      showToast('warning', 'Active session belongs to another instructor');
      return;
    }

    await showInstructorOptions();
    return;
  }

  if (student) {
    if (!sessionActive.value) {
      lastAction.value = `${student.name} tapped before a professor opened a session.`;
      pushHistory('Student tap blocked', 'Attendance is locked until an instructor starts the class.', 'warning');
      showStudentToast(student, 'Instructor RFID is required first.', 'warning');
      showStudentTemporarily(student);
      setTapHeadline('Instructor RFID Required');
      return;
    }

    if (currentMode.value === 'borrowing') {
      processBorrowerMode(student);
      return;
    }

    recordAttendance(student);
    return;
  }

  lastAction.value = `RFID ${rfid} is not registered in the panel sample data.`;
  pushHistory('Unknown RFID', `No instructor or student record matched RFID ${rfid}.`, 'warning');
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

const handleKeydown = (event) => {
  if (!isListening.value) return;
  if (!panelUnlocked.value) return;

  const activeElement = document.activeElement;
  if (activeElement && (activeElement.tagName === 'INPUT' || activeElement.tagName === 'TEXTAREA' || activeElement.tagName === 'SELECT' || activeElement.isContentEditable)) {
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
};

const runDemoTap = () => {
  if (!sessionActive.value) {
    handleRfidScan(instructorProfiles[0].rfid);
    return;
  }

  if (currentMode.value === 'borrowing') {
    handleRfidScan(studentProfiles[1].rfid);
    return;
  }

  const nextStudent = studentProfiles.find((student) => !attendanceRecords.value.some((entry) => entry.rfid === student.rfid)) || studentProfiles[0];
  handleRfidScan(nextStudent.rfid);
};

const demoProfessorRetap = () => {
  if (!activeProfessor.value) {
    handleRfidScan(instructorProfiles[0].rfid);
    return;
  }

  handleRfidScan(activeProfessor.value.rfid);
};

onMounted(() => {
  const panelAuthRaw = localStorage.getItem('panelAuth');
  if (panelAuthRaw) {
    try {
      const panelAuth = JSON.parse(panelAuthRaw);
      if (panelAuth.unlocked && panelAuth.room) {
        selectedRoom.value = panelAuth.room;
        panelUnlocked.value = true;
      }
    } catch {
      // skip
    }
  }

  updateClock();
  timeTicker = window.setInterval(updateClock, 1000);
  window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
  if (timeTicker) clearInterval(timeTicker);
  window.removeEventListener('keydown', handleKeydown);
  if (scanFinalizeTimer) clearTimeout(scanFinalizeTimer);
  if (activeStudentTimer) clearTimeout(activeStudentTimer);
  if (tapHeadlineTimer) clearTimeout(tapHeadlineTimer);
});
</script>

<template>
  <!-- ═══ Gate Screen ═══ -->
  <div v-if="!panelUnlocked" class="min-h-screen w-full bg-[#f5f6fa] flex items-center justify-center p-6">
    <div class="w-full max-w-2xl">
      <!-- Icon + heading -->
      <div class="mb-10 text-center">
        <div class="inline-flex h-20 w-20 items-center justify-center rounded-3xl bg-[#123456] text-white shadow-lg">
          <svg class="h-10 w-10" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
            <rect x="3" y="11" width="18" height="11" rx="2" stroke-width="1.8"/>
            <path stroke-linecap="round" stroke-width="1.8" d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
        </div>
        <h1 class="mt-5 text-3xl font-extrabold text-slate-900">Panel Access</h1>
        <p class="mt-2 text-base text-slate-500">Verify to activate the attendance panel.</p>
      </div>

      <!-- Card -->
      <div class="rounded-[28px] bg-white p-10 shadow-xl ring-1 ring-slate-200/70">
        <!-- Step 1: PIN Entry -->
        <div v-if="gateStep === 'pin'">
          <div class="text-[10px] font-bold uppercase tracking-[0.28em] text-slate-400">Step 1 of 2</div>
          <h2 class="mt-3 text-2xl font-extrabold text-slate-900">Enter Access PIN</h2>
          <p class="mt-2 text-base text-slate-500">Verify administrator access before selecting a room.</p>
          <div class="mt-5">
            <input
              v-model="pinValue"
              type="password"
              inputmode="numeric"
              maxlength="8"
              placeholder="● ● ● ●"
              autocomplete="one-time-code"
              class="w-full rounded-2xl border-2 border-slate-200 bg-slate-50 px-4 py-3.5 text-center text-xl font-bold tracking-[0.5em] text-slate-900 placeholder:tracking-normal placeholder:text-slate-300 outline-none transition focus:border-[#123456] focus:bg-white focus:ring-2 focus:ring-[#123456]/10"
              :class="pinError ? 'border-red-300 focus:border-red-400 focus:ring-red-100' : ''"
              @keydown.enter="verifyPin"
            />
            <p v-if="pinError" class="mt-2 text-center text-xs font-semibold text-red-500">{{ pinError }}</p>
          </div>
          <button
            type="button"
            class="mt-5 w-full rounded-2xl bg-[#123456] py-4 text-base font-bold text-white shadow-sm transition hover:bg-[#0e2840] disabled:cursor-not-allowed disabled:opacity-40"
            :disabled="!pinValue || pinLoading"
            @click="verifyPin"
          >
            <span v-if="pinLoading">Verifying...</span>
            <span v-else>Continue to Room Selection →</span>
          </button>
          <Link
            :href="route('landingPage')"
            class="mt-3 block w-full rounded-2xl border border-slate-200 bg-white py-3 text-center text-sm font-semibold text-slate-600 transition hover:bg-slate-50"
          >
            Exit
          </Link>
        </div>

        <!-- Step 2: Room Selection -->
        <div v-else>
          <button
            type="button"
            class="text-xs font-semibold text-slate-400 transition hover:text-slate-700"
            @click="gateStep = 'pin'; pinValue = ''; pinError = ''; pinVerified = false"
          >
            ← Change PIN
          </button>
          <div class="mt-4 text-[10px] font-bold uppercase tracking-[0.28em] text-slate-400">Step 2 of 2</div>
          <h2 class="mt-3 text-2xl font-extrabold text-slate-900">Select a Room</h2>
          <p class="mt-2 text-base text-slate-500">Which room is this panel assigned to?</p>

          <div class="mt-5">
            <input
              v-model="roomSearch"
              type="text"
              placeholder="Search rooms..."
              class="w-full rounded-xl border-2 border-slate-200 bg-slate-50 px-4 py-3 text-base outline-none transition focus:border-[#123456] focus:bg-white focus:ring-2 focus:ring-[#123456]/10"
            />
          </div>

          <div class="mt-4 max-h-96 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50/30 p-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
              <button
                v-for="room in filteredRooms"
                :key="room"
                type="button"
                class="rounded-2xl border-2 px-4 py-4 text-left text-sm font-semibold transition-all duration-150"
                :class="selectedRoom === room
                  ? 'border-[#123456] bg-[#123456] text-white shadow-md'
                  : 'border-slate-200 text-slate-700 hover:border-slate-300 hover:bg-white'"
                @click="selectedRoom = room"
              >
                <div class="mb-1 text-[10px] font-bold uppercase tracking-wide" :class="selectedRoom === room ? 'text-white/60' : 'text-slate-400'">Room</div>
                <div class="min-h-8 whitespace-normal">{{ room }}</div>
              </button>
            </div>
            <div v-if="filteredRooms.length === 0" class="py-8 text-center text-sm text-slate-500">
              No rooms found
            </div>
          </div>

          <button
            type="button"
            class="mt-5 w-full rounded-2xl bg-[#123456] py-4 text-base font-bold text-white shadow-sm transition hover:bg-[#0e2840] disabled:cursor-not-allowed disabled:opacity-40"
            :disabled="!selectedRoom || !pinVerified"
            @click="unlockPanel"
          >
            Unlock Panel
          </button>
        </div>
      </div>

      <p class="mt-6 text-center text-xs text-slate-400">Contact your system administrator if you need access assistance.</p>
    </div>
  </div>

  <!-- ═══ Main Panel ═══ -->
  <div v-else class="min-h-screen w-full bg-[#f5f6fa] p-4 sm:p-5 lg:p-6">
    <div class="grid min-h-[calc(100vh-2rem)] w-full gap-4 sm:min-h-[calc(100vh-2.5rem)] lg:min-h-[calc(100vh-3rem)] lg:grid-cols-[1.45fr_0.9fr] lg:grid-rows-[auto_1fr_auto]">
      <section class="rounded-[18px] bg-white px-5 py-3 shadow-sm ring-1 ring-slate-200/70 lg:col-span-2">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div class="flex items-center gap-4">
            <div>
              <div class="text-[10px] font-bold uppercase tracking-[0.28em] text-slate-400">Professor Session</div>
              <div class="mt-0.5 text-lg font-bold text-slate-900">{{ activeProfessor ? activeProfessor.name : 'Waiting for professor RFID tap…' }}</div>
            </div>
          </div>

          <div class="flex flex-wrap items-center gap-2">
            <div class="rounded-xl bg-slate-50 px-3 py-2 ring-1 ring-slate-200">
              <div class="text-[9px] font-bold uppercase tracking-[0.22em] text-slate-400">Subject</div>
              <div class="text-xs font-semibold text-slate-700">{{ activeProfessor ? activeProfessor.subject : '—' }}</div>
            </div>
            <div class="rounded-xl bg-slate-50 px-3 py-2 ring-1 ring-slate-200">
              <div class="text-[9px] font-bold uppercase tracking-[0.22em] text-slate-400">Section</div>
              <div class="text-xs font-semibold text-slate-700">{{ activeProfessor ? activeProfessor.section : '—' }}</div>
            </div>
            <div class="rounded-xl bg-slate-50 px-3 py-2 ring-1 ring-slate-200">
              <div class="text-[9px] font-bold uppercase tracking-[0.22em] text-slate-400">Students</div>
              <div class="text-xs font-semibold text-slate-700">{{ attendanceCount }}</div>
            </div>
            <div class="rounded-xl bg-slate-50 px-3 py-2 ring-1 ring-slate-200">
              <div class="text-[9px] font-bold uppercase tracking-[0.22em] text-slate-400">Room</div>
              <div class="text-xs font-semibold text-slate-700">{{ selectedRoom }}</div>
            </div>
            <div class="rounded-xl bg-[#123456] px-3 py-2 text-white shadow-sm">
              <div class="text-[9px] font-bold uppercase tracking-[0.22em] text-white/55">Time</div>
              <div class="text-xs font-semibold">{{ currentTime }}</div>
            </div>
            <button
              type="button"
              class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
              title="Show students who attended"
              @click="attendeesDrawerOpen = true"
            >
              <svg class="h-4 w-4 text-[#123456]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16 19a4 4 0 0 0-8 0"></path>
                <circle cx="12" cy="9" r="3.2"></circle>
                <path stroke-linecap="round" stroke-linejoin="round" d="M7 19H4.5A1.5 1.5 0 0 1 3 17.5v-11A1.5 1.5 0 0 1 4.5 5h15A1.5 1.5 0 0 1 21 6.5v11a1.5 1.5 0 0 1-1.5 1.5H17"></path>
              </svg>
              <span>Attendees ({{ attendanceCount }})</span>
            </button>
            <button
              type="button"
              class="rounded-xl bg-red-50 border border-red-200 px-3 py-2 text-xs font-semibold text-red-700 transition hover:bg-red-100"
              title="Logout and change room"
              @click="logoutPanel"
            >
              Logout
            </button>
          </div>
        </div>
      </section>

      <section class="flex min-h-165 flex-col rounded-[22px] bg-white p-5 shadow-sm ring-1 ring-slate-200/70 lg:row-span-1">
        <div class="flex items-center justify-between">
          <div>
            <div class="text-[11px] font-bold uppercase tracking-[0.28em] text-slate-400">Scanner Result</div>
            <h2 class="mt-2 text-[28px] font-extrabold text-slate-900">Tap RFID Card</h2>
          </div>
          <div class="rounded-full px-3 py-1 text-xs font-bold" :class="modeClass">
            {{ sessionActive ? 'Live' : 'Waiting' }}
          </div>
        </div>

        <div class="mt-5 flex flex-1 flex-col rounded-[28px] bg-[radial-gradient(circle_at_top,rgba(37,99,235,0.16),transparent_56%),linear-gradient(180deg,#f8fbff_0%,#eef4ff_100%)] p-5 ring-1 ring-blue-100">
          <div class="flex flex-1 flex-col items-center justify-center text-center">
            <div
              class="flex h-28 w-28 items-center justify-center rounded-[30px] shadow-lg transition-all duration-300"
              :class="scanPulse ? 'scale-110 bg-emerald-500 text-white' : 'bg-[#123456] text-white'"
            >
              <svg class="h-14 w-14" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <rect x="2.5" y="5" width="19" height="14" rx="2.5"></rect>
                <rect x="5.5" y="9" width="5" height="4" rx="1"></rect>
                <path stroke-linecap="round" d="M15 10.5a1.6 1.6 0 0 1 0 3"></path>
                <path stroke-linecap="round" d="M17.4 8.4a4.1 4.1 0 0 1 0 7.2"></path>
              </svg>
            </div>
            <div class="mt-5 text-[32px] font-extrabold leading-none text-slate-900">{{ tapHeadline }}</div>
            <div class="mt-3 max-w-105 text-sm leading-6 text-slate-500">{{ statusSubline }}</div>
          </div>

          <div class="mt-6 rounded-[22px] bg-white/90 p-4 ring-1 ring-slate-200">
            <div class="text-[10px] font-bold uppercase tracking-[0.22em] text-slate-400">Scan Result</div>
            <div class="mt-2 text-lg font-extrabold text-slate-900">{{ statusHeadline }}</div>
            <div class="mt-2 text-sm leading-6 text-slate-600">{{ lastAction }}</div>
            <div class="mt-4 flex items-center justify-between gap-3">
              <div class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white">
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
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="flex min-h-165 flex-col rounded-[22px] bg-white p-5 shadow-sm ring-1 ring-slate-200/70 lg:row-span-2">
        <div class="text-[11px] font-bold uppercase tracking-[0.28em] text-slate-400">Student Information</div>

        <div class="mt-4 flex flex-col items-center gap-3 pt-2">
          <img
            :src="`https://api.dicebear.com/7.x/personas/svg?seed=${encodeURIComponent(activeStudent?.avatarSeed ?? 'rfid-panel-placeholder')}`"
            alt="student avatar"
            class="h-36 w-36 rounded-3xl"
          />
          <div class="text-center">
            <div class="text-xl font-extrabold text-slate-900">{{ activeStudent ? activeStudent.name : 'No student scanned yet' }}</div>
            <div class="mt-1 text-sm text-slate-500">{{ activeStudent ? activeStudent.studentId : 'Student details appear after tap.' }}</div>
          </div>
        </div>

        <div class="mt-4 space-y-3">
          <div class="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-3 ring-1 ring-slate-200">
            <span class="text-sm font-semibold text-slate-500">Year</span>
            <span class="text-sm font-bold text-slate-800">{{ activeStudent ? activeStudent.year : 'Waiting...' }}</span>
          </div>
          <div class="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-3 ring-1 ring-slate-200">
            <span class="text-sm font-semibold text-slate-500">Course</span>
            <span class="text-sm font-bold text-slate-800">{{ activeStudent ? activeStudent.course : 'Waiting...' }}</span>
          </div>
          <div class="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-3 ring-1 ring-slate-200">
            <span class="text-sm font-semibold text-slate-500">Section</span>
            <span class="text-sm font-bold text-slate-800">{{ activeStudent ? activeStudent.section : 'Waiting...' }}</span>
          </div>
          <div class="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-3 ring-1 ring-slate-200">
            <span class="text-sm font-semibold text-slate-500">Status</span>
            <span class="text-sm font-bold" :class="sessionActive ? 'text-emerald-600' : 'text-slate-500'">{{ activeStudent ? 'Recognized' : 'Waiting' }}</span>
          </div>
        </div>
      </section>

    </div>

    <!-- Floating RFID Toggle (from Borrow.vue) -->
    <button
      type="button"
      class="fixed bottom-6 right-6 flex items-center gap-2 rounded-full px-4 py-2.5 text-sm font-semibold shadow-lg transition-all duration-300 z-50 select-none cursor-pointer"
      :class="scanPulse
        ? 'bg-emerald-500 text-white scale-110'
        : !isListening
          ? 'bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100'
          : currentMode === 'borrowing'
            ? 'bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100'
            : currentMode === 'attendance'
              ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100'
              : 'bg-white text-gray-700 border border-gray-200 hover:bg-emerald-50 hover:border-emerald-300'"
      :title="isListening ? 'Click to pause RFID scanner' : 'Click to resume RFID scanner'"
      @click="!scanPulse && toggleListening()"
    >
      <svg class="h-5 w-5" :class="currentMode === 'borrowing' ? 'text-amber-500' : isListening ? 'text-emerald-500' : 'text-amber-400'" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
        <rect x="2" y="5" width="20" height="14" rx="2" stroke-width="1.8"/>
        <rect x="5" y="9" width="5" height="4" rx="1" stroke-width="1.5"/>
        <path stroke-linecap="round" stroke-width="1.5" d="M15 10.5a1.5 1.5 0 0 1 0 3"/>
        <path stroke-linecap="round" stroke-width="1.5" d="M17.5 8.5a4 4 0 0 1 0 7"/>
      </svg>
      <span>{{ scanPulse ? 'Scanned!' : !isListening ? 'Scanner Paused' : sessionActive ? modeLabel : 'RFID Listening' }}</span>
      <span v-if="isListening && !scanPulse" class="absolute top-2 right-2 h-2 w-2 animate-ping rounded-full bg-emerald-500"></span>
    </button>

    <div
      v-if="attendeesDrawerOpen"
      class="fixed inset-0 z-50"
      role="dialog"
      aria-modal="true"
      aria-label="Attendees list"
    >
      <button
        type="button"
        class="absolute inset-0 bg-slate-900/35"
        aria-label="Close attendees drawer"
        @click="attendeesDrawerOpen = false"
      ></button>

      <aside class="absolute right-0 top-0 h-full w-full max-w-md bg-white shadow-2xl ring-1 ring-slate-200">
        <div class="flex h-full flex-col">
          <div class="border-b border-slate-200 px-5 py-4">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#123456] text-white shadow-sm">
                  <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 19a4 4 0 0 0-8 0"></path>
                    <circle cx="12" cy="9" r="3.2"></circle>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 19H4.5A1.5 1.5 0 0 1 3 17.5v-11A1.5 1.5 0 0 1 4.5 5h15A1.5 1.5 0 0 1 21 6.5v11a1.5 1.5 0 0 1-1.5 1.5H17"></path>
                  </svg>
                </div>
                <div>
                <div class="text-[10px] font-bold uppercase tracking-[0.24em] text-slate-400">Attendance Drawer</div>
                <h3 class="mt-1 text-xl font-extrabold text-slate-900">Students Attended</h3>
                </div>
              </div>
              <button
                type="button"
                class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-50"
                @click="attendeesDrawerOpen = false"
              >
                Close
              </button>
            </div>
            <div class="mt-3 inline-flex items-center gap-2 rounded-xl bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700">
              <span>Total:</span>
              <span class="font-bold">{{ attendanceCount }}</span>
            </div>
          </div>

          <div class="flex-1 overflow-y-auto px-4 py-4">
            <div v-if="attendanceRecords.length === 0" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
              <div class="mb-2 flex justify-center">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-200 text-slate-500">
                  <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 19a4 4 0 0 0-8 0"></path>
                    <circle cx="12" cy="9" r="3.2"></circle>
                  </svg>
                </div>
              </div>
              <div class="text-sm font-semibold text-slate-600">No attendance yet</div>
              <div class="mt-1 text-xs text-slate-500">Students will appear here after their RFID tap is recorded.</div>
            </div>

            <ul v-else class="space-y-3">
              <li
                v-for="record in attendanceRecords"
                :key="record.id"
                class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm"
              >
                <div class="flex items-center justify-between gap-3">
                  <div>
                    <div class="text-sm font-bold text-slate-900">{{ record.name }}</div>
                    <div class="mt-0.5 text-xs text-slate-500">{{ record.course }} • {{ record.section }}</div>
                  </div>
                  <div class="rounded-lg bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-700">{{ record.status }}</div>
                </div>
                <div class="mt-2 flex items-center justify-between text-[11px] text-slate-500">
                  <span>RFID: {{ record.rfid }}</span>
                  <span>{{ record.time }}</span>
                </div>
              </li>
            </ul>
          </div>
        </div>
      </aside>
    </div>
  </div>
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
</style>