<template>
    <div class="h-full w-full overflow-hidden bg-slate-50 font-sans">
        <div class="flex h-full w-full flex-col gap-4 px-4 py-4">

            <!-- Stat Cards -->
            <section class="grid shrink-0 grid-cols-2 gap-3 md:grid-cols-4">
                <div v-for="card in countCards" :key="card.label"
                    class="flex flex-col gap-1 rounded-lg border border-slate-100 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-2xl font-extrabold text-slate-900">{{ card.value }}</span>
                        <component :is="card.icon" class="h-6 w-6 text-slate-300" />
                    </div>
                    <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ card.label }}</div>
                    <!-- NOTE: The subtitle "X EMPLOYEES NEEDED" shown under each card in the screenshot
                         requires additional data fields not present in `counts`. Add subtitle keys to counts prop. -->
                    <div class="text-[10px] text-slate-300 uppercase tracking-wide">{{ card.subtitle }}</div>
                </div>
            </section>

            <!-- Middle Row: Calendar + Emergency Notifications -->
            <section class="grid shrink-0 gap-4 lg:grid-cols-[0.9fr_1.5fr]">

                <!-- Calendar -->
                <!-- NOTE: The screenshot shows a full monthly mini-calendar. There is no calendar
                     data/prop in the original code. This is a static display — wire up real
                     appointment/event data by adding a `calendarEvents` prop from the backend. -->
                <div class="rounded-lg border border-slate-100 bg-white p-4 shadow-sm">
                    <h2 class="mb-3 text-xs font-bold uppercase tracking-widest text-slate-400">Calendar</h2>
                    <div class="mb-1 grid grid-cols-7 gap-y-1 text-center text-[10px] font-semibold text-slate-400">
                        <span v-for="d in ['SUN','MON','TUE','WED','THU','FRI','SAT']" :key="d">{{ d }}</span>
                    </div>
                    <div class="grid grid-cols-7 gap-y-1 text-center text-[11px] text-slate-600">
                        <span v-for="(day, i) in calendarDays" :key="i"
                            :class="[
                                'h-6 w-6 mx-auto flex items-center justify-center rounded-full cursor-pointer hover:bg-blue-50 transition',
                                day.isToday ? 'bg-blue-600 text-white font-bold hover:bg-blue-600' : '',
                                day.isOtherMonth ? 'text-slate-300' : '',
                                day.hasEvent && !day.isToday ? 'text-blue-600 font-semibold' : ''
                            ]">
                            {{ day.label }}
                        </span>
                    </div>
                </div>

                <!-- Emergency Notifications -->
                <div class="rounded-lg border border-slate-100 bg-white p-4 shadow-sm">
                    <h2 class="mb-3 text-xs font-bold uppercase tracking-widest text-slate-400">Emergency Notification</h2>
                    <div class="max-h-44 space-y-2 overflow-y-auto pr-1">
                        <div v-for="alert in alerts" :key="alert.emergency_alert_id"
                            class="flex items-start gap-3 rounded-md border border-slate-100 bg-slate-50 px-3 py-2">
                            <!-- Icon dot based on status -->
                            <span :class="[
                                'mt-1 flex-shrink-0 h-2.5 w-2.5 rounded-full',
                                alert.status === 'open' ? 'bg-blue-500' : alert.status === 'resolved' ? 'bg-green-400' : 'bg-red-400'
                            ]"></span>
                            <div class="flex-1 min-w-0">
                                <p class="line-clamp-2 text-xs leading-snug text-slate-700">{{ alert.message }}</p>
                                <!-- NOTE: "RFID Timed In" sub-label visible in screenshot seems to be
                                     a separate notification sub-type not present in the alert model.
                                     May need an `alert.sub_type` field. -->
                                <div class="mt-1 text-[11px] text-slate-400">
                                    {{ alert.sub_type || alert.type }} · {{ alert.room || 'No room' }} · {{ alert.created_at }}
                                </div>
                            </div>
                            <select v-model="alert.status"
                                @change="updateAlert(alert.emergency_alert_id, alert.status)"
                                class="rounded-md border border-slate-200 px-2 py-1 text-[11px] text-slate-600 bg-white">
                                <option value="open">Open</option>
                                <option value="acknowledged">Acknowledged</option>
                                <option value="resolved">Resolved</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div v-if="alerts.length === 0"
                            class="rounded-lg border border-dashed border-slate-200 p-6 text-center text-sm text-slate-400">
                            No emergency notifications yet.
                        </div>
                    </div>
                </div>
            </section>

            <!-- Bottom Row: Emergency Detail Cards -->
            <!-- NOTE: The screenshot shows individual "Emergency Details" cards with a patient photo,
                 location, department, category color label, symptoms, time sent, and Dispatch/Ignore actions.
                 The original code has no `dispatches` or `emergencyDetails` prop — only `alerts`.
                 These cards appear to be a richer view of alerts with patient linkage.
                 You'll need a new `emergencyDetails` prop (array) passed from the backend with fields:
                 { id, patient_name, patient_avatar, location, department, category, symptoms, symptoms_color, time_sent, email } -->
            <section class="flex min-h-0 flex-1 flex-col">
                <h2 class="mb-2 shrink-0 text-xs font-bold uppercase tracking-widest text-slate-400">Emergency Details</h2>
                <div class="flex min-h-0 flex-1 flex-wrap content-start items-start gap-3 overflow-y-auto pr-1">
                    <div v-for="detail in emergencyDetails" :key="detail.id"
                        class="flex h-fit w-fit min-w-[320px] max-w-[420px] flex-none flex-col gap-2 rounded-lg border border-slate-100 bg-white p-3 shadow-sm">

                        <!-- Patient row -->
                        <div class="flex items-center gap-3">
                            <div class="h-8 w-8 flex-shrink-0 overflow-hidden rounded-full bg-slate-200">
                                <img v-if="detail.patient_avatar" :src="detail.patient_avatar" :alt="detail.patient_name" class="h-full w-full object-cover" />
                                <span v-else class="flex h-full w-full items-center justify-center text-xs font-bold text-slate-500">
                                    {{ detail.patient_name?.charAt(0) || '?' }}
                                </span>
                            </div>
                            <div>
                                <div class="text-xs font-semibold text-slate-800">{{ detail.patient_name }}</div>
                                <div class="text-[11px] text-slate-400">Emergency Details</div>
                            </div>
                        </div>

                        <!-- Detail rows -->
                        <div class="space-y-0.5 text-[11px] text-slate-600">
                            <div class="flex items-center gap-1.5">
                                <svg class="h-3.5 w-3.5 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0L6.343 16.657a8 8 0 1111.314 0z"/><path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <span class="truncate">{{ detail.location }}</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <svg class="h-3.5 w-3.5 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0H5m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 00-1-1h-2a1 1 0 00-1 1v5"/></svg>
                                <span class="truncate">{{ detail.department }}</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="inline-block rounded px-1.5 py-0.5 text-[10px] font-bold"
                                    :class="detail.category === 'Pending' ? 'bg-yellow-100 text-yellow-700' : detail.category === 'Critical' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'">
                                    {{ detail.category }}
                                </span>
                                <span class="text-slate-400">Category</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <svg class="h-3.5 w-3.5 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                                <!-- NOTE: `symptoms_color` should be 'pending', 'critical', or 'normal'
                                     matching the colored text labels in the screenshot. -->
                                <span class="line-clamp-2" :class="detail.symptoms_color === 'Pending' ? 'text-yellow-600 font-semibold' : detail.symptoms_color === 'Critical' ? 'text-red-600 font-semibold' : 'text-slate-600'">
                                    Symptoms: {{ detail.symptoms }}
                                </span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <svg class="h-3.5 w-3.5 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.948V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                <span>Tel: {{ detail.phone || '---' }}</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <svg class="h-3.5 w-3.5 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>Time Sent: {{ detail.time_sent || '---' }}</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <svg class="h-3.5 w-3.5 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                <span class="truncate">{{ detail.email || '---' }}</span>
                            </div>
                        </div>

                        <!-- Actions -->
                        <!-- NOTE: Dispatch action requires a `clinic.emergency-alerts.dispatch` route
                             (or equivalent) not present in the original code. Add to your backend routes. -->
                        <div class="flex gap-2 pt-1">
                            <button @click="dispatchAlert(detail.id)"
                                class="flex-1 rounded-md bg-red-500 px-3 py-1 text-xs font-bold text-white transition hover:bg-red-600">
                                Dispatch
                            </button>
                            <button @click="ignoreAlert(detail.id)"
                                class="flex-1 rounded-md border border-red-300 px-3 py-1 text-xs font-bold text-red-500 transition hover:bg-red-50">
                                Ignore
                            </button>
                        </div>
                    </div>

                    <div v-if="emergencyDetails.length === 0"
                        class="w-full rounded-xl border border-dashed border-slate-200 p-8 text-center text-sm text-slate-400">
                        No emergency detail cards yet.
                    </div>
                </div>
            </section>

        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    counts: { type: Object, default: () => ({}) },
    alerts: { type: Array, default: () => [] },
    emergencyTypes: { type: Array, default: () => [] },
    currentUser: { type: Object, default: () => ({}) },
    calendarEvents: { type: Array, default: () => [] },
    // NOTE: New prop required — not in original code.
    // Pass from backend: array of rich emergency detail objects for the bottom card grid.
    emergencyDetails: { type: Array, default: () => [] },
});

// ── Stat cards ──────────────────────────────────────────────────────────────
// NOTE: Icon components are placeholders using inline SVG strings.
// Replace with your icon library (e.g. heroicons, lucide-vue-next) as needed.
const countCards = computed(() => [
    {
        label: 'Total Cases',
        value: props.counts.clinicCases ?? 0,
        // NOTE: subtitle "X EMPLOYEES NEEDED" in screenshot needs a counts.casesSubtitle field
        subtitle: props.counts.casesSubtitle ?? '',
        icon: 'IconUsers',
    },
    {
        label: 'Total Responds',
        value: props.counts.totalResponds ?? 0,
        subtitle: props.counts.respondsSubtitle ?? '',
        icon: 'IconUsers',
    },
    {
        label: 'Total Pending',
        value: props.counts.openAlerts ?? 0,
        subtitle: props.counts.pendingSubtitle ?? '',
        icon: 'IconUsers',
    },
    {
        label: "Today's Year",
        // NOTE: The screenshot shows a year range (e.g. "2026 - 2027") under "Today's Year".
        // This is not in the original counts. Add counts.yearRange to your backend response.
        value: props.counts.todayAlerts ?? 0,
        subtitle: props.counts.yearRange ?? '',
        icon: 'IconCalendar',
    },
]);

// ── Calendar (static demo — replace with real data via `calendarEvents` prop) ──
// NOTE: This generates a simple current-month calendar with no event data.
// To show real appointments, pass a `calendarEvents` prop (array of date strings)
// and mark those days with `hasEvent: true`.
const calendarDays = computed(() => {
    const today = new Date();
    const year = today.getFullYear();
    const month = today.getMonth();
    const eventDates = new Set(props.calendarEvents || []);
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const daysInPrevMonth = new Date(year, month, 0).getDate();

    const days = [];
    // Prev month padding
    for (let i = firstDay - 1; i >= 0; i--) {
        days.push({ label: daysInPrevMonth - i, isOtherMonth: true });
    }
    // Current month
    for (let d = 1; d <= daysInMonth; d++) {
        const dateKey = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        days.push({
            label: d,
            isToday: d === today.getDate(),
            hasEvent: eventDates.has(dateKey),
        });
    }
    // Next month padding to complete grid
    const remaining = 42 - days.length;
    for (let d = 1; d <= remaining; d++) {
        days.push({ label: d, isOtherMonth: true });
    }
    return days;
});

// ── Actions ──────────────────────────────────────────────────────────────────
const updateAlert = (id, status) => {
    router.put(route('clinic.emergency-alerts.update', { id }), { status }, { preserveScroll: true });
};

// NOTE: dispatchAlert requires a new backend route: clinic.emergency-alerts.dispatch
// Add: Route::post('/emergency-alerts/{id}/dispatch', [...]) in your routes file.
const dispatchAlert = (id) => {
    router.post(route('clinic.emergency-alerts.dispatch', { id }), {}, { preserveScroll: true });
};

// NOTE: ignoreAlert maps to updating status to 'cancelled'. If a separate "ignore" route
// is needed, add it on the backend. Currently reuses the update route.
const ignoreAlert = (id) => {
    router.put(route('clinic.emergency-alerts.update', { id }), { status: 'cancelled' }, { preserveScroll: true });
};
</script>
