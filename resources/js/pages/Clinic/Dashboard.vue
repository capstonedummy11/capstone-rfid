<script setup>
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import {
    AlertTriangle,
    BellRing,
    CalendarDays,
    CheckCircle2,
    Edit3,
    ListChecks,
    Plus,
    Trash2,
    Users,
} from 'lucide-vue-next';

const props = defineProps({
    counts: { type: Object, default: () => ({}) },
    alerts: { type: Array, default: () => [] },
    emergencyTypes: { type: Array, default: () => [] },
    calendarEvents: { type: Array, default: () => [] },
    emergencyDetails: { type: Array, default: () => [] },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const editingTypeId = ref(null);
const latestAlertId = ref(0);
const audioUnlocked = ref(false);
const processingAlertIds = ref(new Set());
const emergencyAlertSoundPath = '/sound/emergency-alert.mp3';
const showAudioNotice = computed(() => !audioUnlocked.value);
let alertPollInterval = null;
let alertAudio = null;

const typeForm = useForm({
    name: '',
    category: 'clinic',
    default_message: '',
    is_active: true,
    sort_order: 0,
});

const countCards = computed(() => [
    {
        label: 'Clinic Cases',
        value: props.counts.clinicCases ?? 0,
        subtitle: props.counts.casesSubtitle ?? '',
        icon: Users,
        tone: 'bg-sky-50 text-sky-600',
    },
    {
        label: 'Responses',
        value: props.counts.totalResponds ?? 0,
        subtitle: props.counts.respondsSubtitle ?? '',
        icon: CheckCircle2,
        tone: 'bg-emerald-50 text-emerald-600',
    },
    {
        label: 'Pending Alerts',
        value: props.counts.openAlerts ?? 0,
        subtitle: props.counts.pendingSubtitle ?? '',
        icon: BellRing,
        tone: 'bg-amber-50 text-amber-600',
    },
    {
        label: 'Today',
        value: props.counts.todayAlerts ?? 0,
        subtitle: props.counts.yearRange ?? '',
        icon: CalendarDays,
        tone: 'bg-indigo-50 text-indigo-600',
    },
]);

const calendarDays = computed(() => {
    const today = new Date();
    const year = today.getFullYear();
    const month = today.getMonth();
    const eventDates = new Set(props.calendarEvents || []);
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const daysInPrevMonth = new Date(year, month, 0).getDate();
    const days = [];

    for (let i = firstDay - 1; i >= 0; i -= 1) {
        days.push({ label: daysInPrevMonth - i, isOtherMonth: true });
    }

    for (let day = 1; day <= daysInMonth; day += 1) {
        const dateKey = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        days.push({
            label: day,
            isToday: day === today.getDate(),
            hasEvent: eventDates.has(dateKey),
        });
    }

    while (days.length < 42) {
        days.push({
            label: days.length - firstDay - daysInMonth + 1,
            isOtherMonth: true,
        });
    }

    return days;
});

const resetTypeForm = () => {
    editingTypeId.value = null;
    typeForm.reset();
    typeForm.category = 'clinic';
    typeForm.is_active = true;
    typeForm.sort_order = 0;
};

const editType = (type) => {
    editingTypeId.value = type.emergency_type_id;
    typeForm.name = type.name || '';
    typeForm.category = type.category || 'clinic';
    typeForm.default_message = type.default_message || '';
    typeForm.is_active = Boolean(type.is_active);
    typeForm.sort_order = type.sort_order || 0;
};

const refreshDashboard = (
    only = ['alerts', 'emergencyDetails', 'counts', 'calendarEvents'],
) => {
    router.reload({
        only,
        preserveScroll: true,
        preserveState: true,
    });
};

const setAlertProcessing = (id, isProcessing) => {
    const next = new Set(processingAlertIds.value);

    if (isProcessing) {
        next.add(id);
    } else {
        next.delete(id);
    }

    processingAlertIds.value = next;
};

const isAlertProcessing = (id) => processingAlertIds.value.has(id);

const submitType = () => {
    if (editingTypeId.value) {
        typeForm.put(
            route('clinic.emergency-types.update', editingTypeId.value),
            {
                preserveScroll: true,
                onSuccess: () => {
                    resetTypeForm();
                    refreshDashboard(['emergencyTypes']);
                },
            },
        );
        return;
    }

    typeForm.post(route('clinic.emergency-types.store'), {
        preserveScroll: true,
        onSuccess: () => {
            resetTypeForm();
            refreshDashboard(['emergencyTypes']);
        },
    });
};

const deleteType = (type) => {
    if (!confirm(`Delete emergency type "${type.name}"?`)) return;

    router.delete(
        route('clinic.emergency-types.destroy', type.emergency_type_id),
        {
            preserveScroll: true,
            onSuccess: () => refreshDashboard(['emergencyTypes']),
        },
    );
};

const updateAlert = (id, status) => {
    router.put(
        route('clinic.emergency-alerts.update', { id }),
        { status },
        {
            preserveScroll: true,
            onSuccess: () => refreshDashboard(),
        },
    );
};

const dispatchAlert = (id) => {
    setAlertProcessing(id, true);

    router.post(
        route('clinic.emergency-alerts.dispatch', { id }),
        {},
        {
            preserveScroll: true,
            onSuccess: () => refreshDashboard(),
            onFinish: () => setAlertProcessing(id, false),
        },
    );
};

const ignoreAlert = (id) => {
    setAlertProcessing(id, true);

    router.put(
        route('clinic.emergency-alerts.update', { id }),
        { status: 'cancelled' },
        {
            preserveScroll: true,
            onSuccess: () => refreshDashboard(),
            onFinish: () => setAlertProcessing(id, false),
        },
    );
};

const highestAlertId = (alerts) =>
    Math.max(
        0,
        ...(alerts ?? []).map((alert) => Number(alert.emergency_alert_id) || 0),
    );

const unlockAlertAudio = () => {
    if (audioUnlocked.value || typeof window === 'undefined') return;

    alertAudio ||= new Audio(emergencyAlertSoundPath);
    alertAudio.preload = 'auto';

    const previousVolume = alertAudio.volume;
    alertAudio.volume = 0;
    alertAudio
        .play()
        .then(() => {
            alertAudio.pause();
            alertAudio.currentTime = 0;
            alertAudio.volume = previousVolume || 1;
            audioUnlocked.value = true;
        })
        .catch(() => {
            alertAudio.volume = previousVolume || 1;
        });
};

const playEmergencySound = () => {
    if (!audioUnlocked.value || !alertAudio) return;

    alertAudio.pause();
    alertAudio.currentTime = 0;
    alertAudio.play().catch(() => {
        audioUnlocked.value = false;
    });
};

watch(
    () => props.alerts,
    (alerts) => {
        const newestId = highestAlertId(alerts);
        if (!latestAlertId.value) {
            latestAlertId.value = newestId;
            return;
        }

        if (newestId > latestAlertId.value) {
            latestAlertId.value = newestId;
            playEmergencySound();
        }
    },
    { immediate: true, deep: true },
);

onMounted(() => {
    window.addEventListener('click', unlockAlertAudio, { once: true });
    window.addEventListener('keydown', unlockAlertAudio, { once: true });

    alertPollInterval = window.setInterval(() => {
        router.reload({
            only: ['alerts', 'emergencyDetails', 'counts', 'calendarEvents'],
            preserveScroll: true,
            preserveState: true,
        });
    }, 10000);
});

onBeforeUnmount(() => {
    window.removeEventListener('click', unlockAlertAudio);
    window.removeEventListener('keydown', unlockAlertAudio);

    if (alertPollInterval) {
        window.clearInterval(alertPollInterval);
    }
});
</script>

<template>
    <div class="min-h-full bg-slate-50 px-4 py-5 text-slate-900">
        <div class="mx-auto flex max-w-7xl flex-col gap-5">
            <header
                class="flex flex-col gap-3 rounded-md bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <p
                        class="text-xs font-black tracking-wide text-brand uppercase"
                    >
                        Clinic Workspace
                    </p>
                    <h1 class="mt-1 text-2xl font-black text-slate-950">
                        Emergency and Patient Care
                    </h1>
                    <p class="text-sm text-slate-500">
                        Monitor alerts, dispatch response, and maintain
                        emergency categories.
                    </p>
                </div>
                <p
                    v-if="flashSuccess"
                    class="rounded-md bg-emerald-50 px-3 py-2 text-sm font-bold text-emerald-700"
                >
                    {{ flashSuccess }}
                </p>
            </header>

            <div
                v-if="showAudioNotice"
                class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800"
            >
                Click anywhere or press any key once to enable emergency alert
                sound.
            </div>

            <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="card in countCards"
                    :key="card.label"
                    class="rounded-md bg-white p-4 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-3xl font-black">{{ card.value }}</p>
                            <p class="mt-1 text-sm font-bold text-slate-700">
                                {{ card.label }}
                            </p>
                        </div>
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-md"
                            :class="card.tone"
                        >
                            <component :is="card.icon" class="h-5 w-5" />
                        </div>
                    </div>
                    <p class="mt-3 text-xs font-semibold text-slate-400">
                        {{ card.subtitle }}
                    </p>
                </article>
            </section>

            <section class="grid gap-5 xl:grid-cols-[320px_1fr]">
                <div class="rounded-md bg-white p-4 shadow-sm">
                    <h2
                        class="mb-3 text-sm font-black tracking-wide text-slate-500 uppercase"
                    >
                        Alert Calendar
                    </h2>
                    <div
                        class="mb-2 grid grid-cols-7 text-center text-[10px] font-bold text-slate-400"
                    >
                        <span
                            v-for="day in [
                                'SUN',
                                'MON',
                                'TUE',
                                'WED',
                                'THU',
                                'FRI',
                                'SAT',
                            ]"
                            :key="day"
                            >{{ day }}</span
                        >
                    </div>
                    <div
                        class="grid grid-cols-7 gap-y-1 text-center text-xs text-slate-600"
                    >
                        <span
                            v-for="(day, index) in calendarDays"
                            :key="index"
                            class="mx-auto flex h-7 w-7 items-center justify-center rounded-full"
                            :class="[
                                day.isToday
                                    ? 'bg-brand font-bold text-white'
                                    : '',
                                day.isOtherMonth ? 'text-slate-300' : '',
                                day.hasEvent && !day.isToday
                                    ? 'bg-sky-50 font-bold text-brand'
                                    : '',
                            ]"
                        >
                            {{ day.label }}
                        </span>
                    </div>
                </div>

                <div class="rounded-md bg-white p-4 shadow-sm">
                    <div class="mb-3 flex items-center justify-between">
                        <h2
                            class="text-sm font-black tracking-wide text-slate-500 uppercase"
                        >
                            Emergency Notifications
                        </h2>
                        <AlertTriangle class="h-5 w-5 text-amber-500" />
                    </div>
                    <div class="max-h-72 space-y-2 overflow-y-auto pr-1">
                        <article
                            v-for="alert in alerts"
                            :key="alert.emergency_alert_id"
                            class="flex flex-col gap-3 rounded-md border border-slate-100 bg-slate-50 p-3 md:flex-row md:items-start"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="h-2.5 w-2.5 rounded-full"
                                        :class="
                                            alert.status === 'open'
                                                ? 'bg-rose-500'
                                                : alert.status === 'resolved'
                                                  ? 'bg-emerald-500'
                                                  : 'bg-amber-500'
                                        "
                                    />
                                    <p class="font-bold text-slate-900">
                                        {{ alert.type }}
                                    </p>
                                </div>
                                <p
                                    class="mt-1 line-clamp-2 text-sm text-slate-600"
                                >
                                    {{ alert.message }}
                                </p>
                                <p class="mt-1 text-xs text-slate-400">
                                    {{ alert.sub_type || 'Emergency Alert' }} |
                                    {{ alert.room || 'No room' }} |
                                    {{ alert.created_at }}
                                </p>
                            </div>
                            <select
                                v-model="alert.status"
                                class="rounded-md border border-slate-200 bg-white px-2 py-1 text-xs font-semibold text-slate-600"
                                @change="
                                    updateAlert(
                                        alert.emergency_alert_id,
                                        alert.status,
                                    )
                                "
                            >
                                <option value="open">Open</option>
                                <option value="acknowledged">
                                    Acknowledged
                                </option>
                                <option value="resolved">Resolved</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </article>
                        <div
                            v-if="alerts.length === 0"
                            class="rounded-md border border-dashed border-slate-200 p-8 text-center text-sm text-slate-400"
                        >
                            No emergency notifications yet.
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-5 xl:grid-cols-[1fr_390px]">
                <div class="rounded-md bg-white p-4 shadow-sm">
                    <h2
                        class="mb-3 text-sm font-black tracking-wide text-slate-500 uppercase"
                    >
                        Emergency Details
                    </h2>
                    <div class="grid gap-3 lg:grid-cols-2">
                        <article
                            v-for="detail in emergencyDetails"
                            :key="detail.id"
                            class="rounded-md border border-slate-100 p-4"
                        >
                            <div class="flex items-start gap-3">
                                <div
                                    class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-slate-100"
                                >
                                    <img
                                        v-if="detail.patient_avatar"
                                        :src="detail.patient_avatar"
                                        :alt="detail.patient_name"
                                        class="h-full w-full object-cover"
                                    />
                                    <span
                                        v-else
                                        class="text-sm font-black text-slate-500"
                                        >{{
                                            detail.patient_name?.charAt(0) ||
                                            '?'
                                        }}</span
                                    >
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h3 class="font-black text-slate-900">
                                        {{ detail.patient_name }}
                                    </h3>
                                    <p class="text-xs text-slate-400">
                                        {{ detail.location }} |
                                        {{ detail.time_sent || 'No time' }}
                                    </p>
                                    <p class="mt-2 text-sm text-slate-600">
                                        {{ detail.symptoms }}
                                    </p>
                                </div>
                                <span
                                    class="rounded-full px-2 py-1 text-[10px] font-black uppercase"
                                    :class="
                                        detail.category === 'Critical'
                                            ? 'bg-rose-50 text-rose-700'
                                            : detail.category === 'Pending'
                                              ? 'bg-amber-50 text-amber-700'
                                              : 'bg-emerald-50 text-emerald-700'
                                    "
                                >
                                    {{ detail.category }}
                                </span>
                            </div>
                            <div class="mt-3 flex gap-2">
                                <button
                                    class="flex-1 rounded-md bg-rose-500 px-3 py-2 text-xs font-bold text-white disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="isAlertProcessing(detail.id)"
                                    @click="dispatchAlert(detail.id)"
                                >
                                    {{
                                        isAlertProcessing(detail.id)
                                            ? 'Sending...'
                                            : 'Dispatch'
                                    }}
                                </button>
                                <button
                                    class="flex-1 rounded-md border border-slate-200 px-3 py-2 text-xs font-bold text-slate-500 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="isAlertProcessing(detail.id)"
                                    @click="ignoreAlert(detail.id)"
                                >
                                    Ignore
                                </button>
                            </div>
                        </article>
                        <div
                            v-if="emergencyDetails.length === 0"
                            class="rounded-md border border-dashed border-slate-200 p-8 text-center text-sm text-slate-400 lg:col-span-2"
                        >
                            No emergency detail cards yet.
                        </div>
                    </div>
                </div>

                <aside class="rounded-md bg-white p-4 shadow-sm">
                    <div class="mb-4 flex items-center justify-between">
                        <h2
                            class="text-sm font-black tracking-wide text-slate-500 uppercase"
                        >
                            Emergency Types
                        </h2>
                        <ListChecks class="h-5 w-5 text-brand" />
                    </div>

                    <form class="grid gap-3" @submit.prevent="submitType">
                        <input
                            v-model="typeForm.name"
                            required
                            placeholder="Type name"
                            class="rounded-md border border-slate-300 px-3 py-2 text-sm"
                        />
                        <p
                            v-if="typeForm.errors.name"
                            class="text-xs font-semibold text-rose-600"
                        >
                            {{ typeForm.errors.name }}
                        </p>
                        <div class="grid grid-cols-2 gap-2">
                            <select
                                v-model="typeForm.category"
                                class="rounded-md border border-slate-300 px-3 py-2 text-sm"
                            >
                                <option value="clinic">Clinic</option>
                                <option value="disaster">Disaster</option>
                                <option value="general">General</option>
                            </select>
                            <input
                                v-model.number="typeForm.sort_order"
                                type="number"
                                min="0"
                                placeholder="Sort"
                                class="rounded-md border border-slate-300 px-3 py-2 text-sm"
                            />
                        </div>
                        <p
                            v-if="
                                typeForm.errors.category ||
                                typeForm.errors.sort_order
                            "
                            class="text-xs font-semibold text-rose-600"
                        >
                            {{
                                typeForm.errors.category ||
                                typeForm.errors.sort_order
                            }}
                        </p>
                        <textarea
                            v-model="typeForm.default_message"
                            rows="3"
                            placeholder="Default message"
                            class="rounded-md border border-slate-300 px-3 py-2 text-sm"
                        />
                        <p
                            v-if="typeForm.errors.default_message"
                            class="text-xs font-semibold text-rose-600"
                        >
                            {{ typeForm.errors.default_message }}
                        </p>
                        <label
                            class="flex items-center gap-2 text-sm font-semibold text-slate-600"
                        >
                            <input
                                v-model="typeForm.is_active"
                                type="checkbox"
                                class="h-4 w-4 accent-brand"
                            />
                            Active
                        </label>
                        <div class="flex gap-2">
                            <button
                                class="inline-flex flex-1 items-center justify-center gap-2 rounded-md bg-brand px-3 py-2 text-sm font-bold text-white"
                                :disabled="typeForm.processing"
                            >
                                <Plus class="h-4 w-4" />
                                {{ editingTypeId ? 'Update Type' : 'Add Type' }}
                            </button>
                            <button
                                type="button"
                                class="rounded-md border border-slate-200 px-3 py-2 text-sm font-bold text-slate-500"
                                @click="resetTypeForm"
                            >
                                Clear
                            </button>
                        </div>
                    </form>

                    <div class="mt-5 max-h-80 space-y-2 overflow-y-auto">
                        <article
                            v-for="type in emergencyTypes"
                            :key="type.emergency_type_id"
                            class="rounded-md border border-slate-100 p-3"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-bold text-slate-900">
                                        {{ type.name }}
                                    </p>
                                    <p class="text-xs text-slate-400">
                                        {{ type.category }} | Sort
                                        {{ type.sort_order || 0 }}
                                    </p>
                                </div>
                                <span
                                    class="rounded-full px-2 py-1 text-[10px] font-black uppercase"
                                    :class="
                                        type.is_active
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'bg-slate-100 text-slate-500'
                                    "
                                >
                                    {{ type.is_active ? 'Active' : 'Off' }}
                                </span>
                            </div>
                            <p class="mt-2 line-clamp-2 text-xs text-slate-500">
                                {{ type.default_message }}
                            </p>
                            <div class="mt-3 flex gap-2">
                                <button
                                    class="rounded-md border border-slate-200 p-2 text-slate-600"
                                    @click="editType(type)"
                                >
                                    <Edit3 class="h-4 w-4" />
                                </button>
                                <button
                                    class="rounded-md border border-rose-200 p-2 text-rose-600"
                                    @click="deleteType(type)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </article>
                    </div>
                </aside>
            </section>
        </div>
    </div>
</template>
