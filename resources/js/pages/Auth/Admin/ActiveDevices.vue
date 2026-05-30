<script setup>
import { router, useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import { computed } from 'vue';

const props = defineProps({
    devices: {
        type: Array,
        default: () => [],
    },
    featureSettings: {
        type: Object,
        default: () => ({
            face_recognition_enabled: true,
        }),
    },
    panelAccess: {
        type: Object,
        default: () => ({
            device_label: 'Attendance Console',
        }),
    },
});

const settingsForm = useForm({
    borrowing_enabled: Boolean(props.featureSettings.borrowing_enabled),
    inventory_enabled: Boolean(props.featureSettings.inventory_enabled),
    face_recognition_enabled: Boolean(
        props.featureSettings.face_recognition_enabled,
    ),
});
const panelAccessForm = useForm({
    device_label: props.panelAccess.device_label ?? 'Attendance Console',
    pin: '',
});

const activeCount = computed(
    () => props.devices.filter((device) => device.is_active).length,
);
const waitingCount = computed(
    () => props.devices.filter((device) => device.is_waiting).length,
);

const statusClass = (device) => {
    if (device.is_active) return 'bg-emerald-50 text-emerald-700';
    if (device.is_waiting) return 'bg-amber-50 text-amber-700';
    return 'bg-slate-100 text-slate-600';
};

const saveFaceSetting = () => {
    settingsForm.put(route('admin.settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Face recognition setting updated',
                showConfirmButton: false,
                timer: 1800,
                timerProgressBar: true,
            });
        },
    });
};

const savePanelAccess = async () => {
    try {
        const xsrfRaw = document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1];

        const response = await fetch(
            route('admin.active-devices.panel-access.update'),
            {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrfRaw ? decodeURIComponent(xsrfRaw) : '',
                },
                body: JSON.stringify({
                    device_label: panelAccessForm.device_label,
                    pin: panelAccessForm.pin,
                }),
            },
        );

        const payload = await response.json().catch(() => ({}));
        if (!response.ok || !payload?.ok) {
            throw new Error(payload?.message ?? 'Unable to update panel access.');
        }

        panelAccessForm.pin = '';
        await Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: payload.message,
            showConfirmButton: false,
            timer: 1800,
            timerProgressBar: true,
        });

        router.reload({ only: ['devices', 'panelAccess'] });
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Panel access update failed',
            text: error?.message ?? 'Unable to update panel access.',
            confirmButtonColor: '#dc2626',
        });
    }
};

const forceLogout = async (device) => {
    if (!device.panel_session_id) return;

    const result = await Swal.fire({
        title: `Log out ${device.room}?`,
        text: 'The panel will be closed automatically on its next status check.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Log out panel',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b',
    });

    if (!result.isConfirmed) return;

    try {
        const xsrfRaw = document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1];

        const response = await fetch(
            route('admin.active-devices.force-logout', {
                panelSessionId: device.panel_session_id,
            }),
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrfRaw ? decodeURIComponent(xsrfRaw) : '',
                },
            },
        );

        const payload = await response.json().catch(() => ({}));
        if (!response.ok || !payload?.ok) {
            throw new Error(payload?.message ?? 'Unable to log out panel.');
        }

        await Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: payload.message,
            showConfirmButton: false,
            timer: 1600,
            timerProgressBar: true,
        });

        router.reload({ only: ['devices'] });
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Panel logout failed',
            text: error?.message ?? 'Unable to log out panel.',
            confirmButtonColor: '#dc2626',
        });
    }
};
</script>

<template>
    <div class="p-4 sm:p-6">
        <div class="mx-auto flex max-w-6xl flex-col gap-4">
            <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Active Devices</h2>
                        <p class="text-sm text-slate-500">
                            Monitor attendance panels and close a panel remotely.
                        </p>
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <a
                            :href="route('attendanceControlPanel.login')"
                            class="rounded-md border border-slate-200 px-4 py-2 text-center text-sm font-bold text-slate-700 transition hover:bg-slate-50"
                        >
                            Open Panel Login
                        </a>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-md bg-emerald-50 px-4 py-3 text-emerald-700">
                            <div class="text-xs font-bold uppercase">Active</div>
                            <div class="text-xl font-black">{{ activeCount }}</div>
                        </div>
                        <div class="rounded-md bg-amber-50 px-4 py-3 text-amber-700">
                            <div class="text-xs font-bold uppercase">Waiting</div>
                            <div class="text-xl font-black">{{ waitingCount }}</div>
                        </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
                <form
                    class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                    @submit.prevent="saveFaceSetting"
                >
                    <label class="flex items-center gap-3">
                        <input
                            v-model="settingsForm.face_recognition_enabled"
                            type="checkbox"
                            class="h-5 w-5 accent-brand"
                        />
                        <span>
                            <span class="block text-sm font-bold text-slate-900">
                                Face Rekognition
                            </span>
                            <span class="block text-sm text-slate-500">
                                Require camera face verification before saving attendance.
                            </span>
                        </span>
                    </label>
                    <button
                        type="submit"
                        :disabled="settingsForm.processing"
                        class="rounded-md bg-brand px-4 py-2 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {{ settingsForm.processing ? 'Saving...' : 'Save Setting' }}
                    </button>
                </form>
            </section>

            <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
                <form class="grid gap-4 lg:grid-cols-[1fr_220px_auto]" @submit.prevent="savePanelAccess">
                    <label class="block">
                        <span class="block text-sm font-bold text-slate-900">
                            Device Label
                        </span>
                        <input
                            v-model="panelAccessForm.device_label"
                            type="text"
                            class="mt-2 w-full rounded-md border border-slate-200 px-3 py-2 text-sm outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/10"
                            placeholder="Attendance Console"
                        />
                    </label>
                    <label class="block">
                        <span class="block text-sm font-bold text-slate-900">
                            New PIN
                        </span>
                        <input
                            v-model="panelAccessForm.pin"
                            type="password"
                            inputmode="numeric"
                            class="mt-2 w-full rounded-md border border-slate-200 px-3 py-2 text-sm outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/10"
                            placeholder="Leave unchanged"
                        />
                    </label>
                    <div class="flex items-end">
                        <button
                            type="submit"
                            class="w-full rounded-md bg-brand px-4 py-2 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="!panelAccessForm.device_label"
                        >
                            Save Access
                        </button>
                    </div>
                </form>
            </section>

            <section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-bold uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Device</th>
                                <th class="px-4 py-3">Room</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Active Attendance</th>
                                <th class="px-4 py-3">Instructor</th>
                                <th class="px-4 py-3">Last Update</th>
                                <th class="px-4 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="device in devices"
                                :key="device.room"
                                class="hover:bg-slate-50"
                            >
                                <td class="px-4 py-3 font-bold text-slate-900">
                                    {{ device.device_label }}
                                </td>
                                <td class="px-4 py-3 font-bold text-slate-900">
                                    {{ device.room }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold"
                                        :class="statusClass(device)"
                                    >
                                        {{ device.status_label }}
                                    </span>
                                    <div class="mt-1 text-xs text-slate-400">
                                        {{ device.mode }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-slate-800">
                                        {{ device.subject }}
                                    </div>
                                    <div class="text-xs text-slate-500">
                                        {{ device.section || 'No section' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    {{ device.instructor || 'No instructor' }}
                                </td>
                                <td class="px-4 py-3 text-slate-500">
                                    {{ device.updated_at || 'Never' }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button
                                        type="button"
                                        class="rounded-md border border-red-200 px-3 py-2 text-xs font-bold text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40"
                                        :disabled="!device.can_force_logout"
                                        @click="forceLogout(device)"
                                    >
                                        Log out panel
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="devices.length === 0">
                                <td
                                    colspan="7"
                                    class="px-4 py-10 text-center text-slate-500"
                                >
                                    No panel devices found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</template>
