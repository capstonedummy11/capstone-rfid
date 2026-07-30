<script setup>
import { router, useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import { computed, ref } from 'vue';

const props = defineProps({
    devices: {
        type: Array,
        default: () => [],
    },
    laboratories: {
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
const labForm = useForm({
    name: '',
    description: '',
    location: '',
    status: 'active',
});
const editingLabId = ref(null);
const deviceForm = useForm({
    laboratory_id: '',
    label: '',
    description: '',
    pin: '',
    is_active: true,
});
const editingDeviceId = ref(null);
const deleteLabForm = useForm({});

const activeCount = computed(
    () => props.devices.filter((device) => device.is_active).length,
);
const waitingCount = computed(
    () => props.devices.filter((device) => device.is_waiting).length,
);
const activeLabCount = computed(
    () =>
        props.laboratories.filter(
            (laboratory) => laboratory.status === 'active',
        ).length,
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
            throw new Error(
                payload?.message ?? 'Unable to update panel access.',
            );
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

const resetLabForm = () => {
    editingLabId.value = null;
    labForm.name = '';
    labForm.description = '';
    labForm.location = '';
    labForm.status = 'active';
    labForm.clearErrors();
};

const saveLaboratory = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            resetLabForm();
            router.reload({ only: ['laboratories'] });
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: editingLabId.value ? 'Laboratory updated' : 'Laboratory saved',
                showConfirmButton: false,
                timer: 1600,
                timerProgressBar: true,
            });
        },
    };
    if (editingLabId.value) {
        labForm.put(route('admin.laboratories.update', { id: editingLabId.value }), options);
    } else {
        labForm.post(route('admin.laboratories.store'), options);
    }
};

const editLaboratory = (laboratory) => {
    editingLabId.value = laboratory.laboratory_id;
    labForm.name = laboratory.name;
    labForm.description = laboratory.description ?? '';
    labForm.location = laboratory.location ?? '';
    labForm.status = laboratory.status ?? 'active';
};

const resetDeviceForm = () => {
    editingDeviceId.value = null;
    deviceForm.reset();
    deviceForm.is_active = true;
    deviceForm.clearErrors();
};

const saveDevice = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            resetDeviceForm();
            router.reload({ only: ['devices'] });
        },
    };
    if (editingDeviceId.value) {
        deviceForm.put(route('admin.active-devices.update', { device: editingDeviceId.value }), options);
    } else {
        deviceForm.post(route('admin.active-devices.store'), options);
    }
};

const editDevice = (device) => {
    editingDeviceId.value = device.panel_device_id;
    deviceForm.laboratory_id = String(device.laboratory_id ?? '');
    deviceForm.label = device.device_label;
    deviceForm.description = device.description ?? '';
    deviceForm.pin = '';
    deviceForm.is_active = Boolean(device.is_enabled);
};

const toggleDevice = (device) => {
    router.put(route('admin.active-devices.update', { device: device.panel_device_id }), {
        laboratory_id: device.laboratory_id,
        label: device.device_label,
        description: device.description ?? '',
        is_active: !device.is_enabled,
    }, { preserveScroll: true });
};

const deleteDevice = async (device) => {
    const result = await Swal.fire({
        title: `Delete ${device.device_label}?`,
        text: 'The device configuration and its PIN will be removed. Historical attendance logs remain.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete device',
        confirmButtonColor: '#dc2626',
    });
    if (!result.isConfirmed) return;
    router.delete(route('admin.active-devices.destroy', { device: device.panel_device_id }), { preserveScroll: true });
};

const setLaboratoryStatus = (laboratory, status) => {
    router.put(
        route('admin.laboratories.update', { id: laboratory.laboratory_id }),
        {
            name: laboratory.name,
            description: laboratory.description ?? '',
            location: laboratory.location,
            status,
        },
        {
            preserveScroll: true,
            onSuccess: () => router.reload({ only: ['laboratories'] }),
        },
    );
};

const deleteLaboratory = async (laboratory) => {
    const result = await Swal.fire({
        title: `Delete ${laboratory.name}?`,
        text: 'Schedules using this laboratory may need to be updated.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b',
    });

    if (!result.isConfirmed) return;

    deleteLabForm.delete(
        route('admin.laboratories.destroy', {
            id: laboratory.laboratory_id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => router.reload({ only: ['laboratories'] }),
        },
    );
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

const changePanelPin = async (device) => {
    const result = await Swal.fire({
        title: `Change PIN for ${device.device_label}`,
        input: 'password',
        inputLabel: 'New panel PIN',
        inputPlaceholder: 'Enter at least 4 characters',
        inputAttributes: {
            maxlength: 32,
            autocapitalize: 'off',
            autocorrect: 'off',
        },
        showCancelButton: true,
        confirmButtonText: 'Save PIN',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#2563eb',
        cancelButtonColor: '#64748b',
        inputValidator: (value) => {
            if (!value || value.length < 4) {
                return 'PIN must be at least 4 characters.';
            }

            return null;
        },
    });

    if (!result.isConfirmed) return;

    try {
        const xsrfRaw = document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1];

        const response = await fetch(
            route('admin.active-devices.pin.update', {
                device: device.panel_device_id,
            }),
            {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrfRaw ? decodeURIComponent(xsrfRaw) : '',
                },
                body: JSON.stringify({ pin: result.value }),
            },
        );

        const payload = await response.json().catch(() => ({}));
        if (!response.ok || !payload?.ok) {
            throw new Error(payload?.message ?? 'Unable to update panel PIN.');
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
            title: 'Panel PIN update failed',
            text: error?.message ?? 'Unable to update panel PIN.',
            confirmButtonColor: '#dc2626',
        });
    }
};
</script>

<template>
    <div class="p-4 sm:p-6">
        <div class="mx-auto flex max-w-6xl flex-col gap-4">
            <section
                class="rounded-md border border-slate-200 bg-white p-5 shadow-sm"
            >
                <div
                    class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                >
                    <div>
                        <h1 class="text-2xl font-black text-slate-900">
                            Laboratories & Devices
                        </h1>
                        <p class="text-sm text-slate-500">
                            Laboratories are physical rooms. Devices are the
                            attendance panels assigned to those rooms and own
                            their PIN and enabled state.
                        </p>
                    </div>
                    <div class="grid grid-cols-3 gap-3 text-sm">
                        <div
                            class="rounded-md bg-slate-100 px-4 py-3 text-slate-700"
                        >
                            <div class="text-xs font-bold uppercase">Labs</div>
                            <div class="text-xl font-black">
                                {{ laboratories.length }}
                            </div>
                        </div>
                        <div
                            class="rounded-md bg-emerald-50 px-4 py-3 text-emerald-700"
                        >
                            <div class="text-xs font-bold uppercase">
                                Active
                            </div>
                            <div class="text-xl font-black">
                                {{ activeCount }}
                            </div>
                        </div>
                        <div
                            class="rounded-md bg-amber-50 px-4 py-3 text-amber-700"
                        >
                            <div class="text-xs font-bold uppercase">
                                Waiting
                            </div>
                            <div class="text-xl font-black">
                                {{ waitingCount }}
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section
                class="rounded-md border border-slate-200 bg-white p-5 shadow-sm"
            >
                <div class="grid gap-5 lg:grid-cols-[1fr_340px]">
                    <div>
                        <div
                            class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between"
                        >
                            <div>
                                <h2 class="text-lg font-bold text-slate-900">
                                    Laboratories
                                </h2>
                                <p class="text-sm text-slate-500">
                                    {{ activeLabCount }} active rooms available
                                    for schedules and panels.
                                </p>
                            </div>
                        </div>
                        <div
                            class="overflow-x-auto rounded-md border border-slate-200"
                        >
                            <table
                                class="min-w-full divide-y divide-slate-200 text-sm"
                            >
                                <thead
                                    class="bg-slate-50 text-left text-xs font-bold text-slate-500 uppercase"
                                >
                                    <tr>
                                        <th class="px-4 py-3">Laboratory</th>
                                        <th class="px-4 py-3">Location</th>
                                        <th class="px-4 py-3">Status</th>
                                        <th class="px-4 py-3 text-right">
                                            Action
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr
                                        v-for="laboratory in laboratories"
                                        :key="laboratory.laboratory_id"
                                        class="hover:bg-slate-50"
                                    >
                                        <td class="px-4 py-3">
                                            <div
                                                class="font-bold text-slate-900"
                                            >
                                                {{ laboratory.name }}
                                            </div>
                                            <div class="text-xs text-slate-500">
                                                {{
                                                    laboratory.description ||
                                                    'No description'
                                                }}
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-slate-600">
                                            {{ laboratory.location || '-' }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span
                                                class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold"
                                                :class="
                                                    laboratory.status ===
                                                    'active'
                                                        ? 'bg-emerald-50 text-emerald-700'
                                                        : 'bg-slate-100 text-slate-600'
                                                "
                                            >
                                                {{
                                                    laboratory.status ||
                                                    'inactive'
                                                }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <div class="flex justify-end gap-2">
                                                <button
                                                    type="button"
                                                    class="rounded-md border border-blue-200 px-3 py-2 text-xs font-bold text-blue-700 transition hover:bg-blue-50"
                                                    @click="editLaboratory(laboratory)"
                                                >
                                                    Edit
                                                </button>
                                                <button
                                                    type="button"
                                                    class="rounded-md border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50"
                                                    @click="
                                                        setLaboratoryStatus(
                                                            laboratory,
                                                            laboratory.status ===
                                                                'active'
                                                                ? 'inactive'
                                                                : 'active',
                                                        )
                                                    "
                                                >
                                                    {{
                                                        laboratory.status ===
                                                        'active'
                                                            ? 'Disable'
                                                            : 'Activate'
                                                    }}
                                                </button>
                                                <button
                                                    type="button"
                                                    class="rounded-md border border-red-200 px-3 py-2 text-xs font-bold text-red-600 transition hover:bg-red-50"
                                                    @click="
                                                        deleteLaboratory(
                                                            laboratory,
                                                        )
                                                    "
                                                >
                                                    Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="laboratories.length === 0">
                                        <td
                                            colspan="4"
                                            class="px-4 py-10 text-center text-slate-500"
                                        >
                                            No laboratory records found.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <form
                        class="rounded-md border border-slate-200 bg-slate-50 p-4"
                        @submit.prevent="saveLaboratory"
                    >
                        <h3 class="text-sm font-black text-slate-900">
                            {{ editingLabId ? 'Edit Laboratory' : 'Add Laboratory' }}
                        </h3>
                        <label class="mt-4 block">
                            <span
                                class="block text-xs font-bold text-slate-500 uppercase"
                            >
                                Name
                            </span>
                            <input
                                v-model="labForm.name"
                                type="text"
                                class="mt-2 w-full rounded-md border border-slate-200 px-3 py-2 text-sm transition outline-none focus:border-brand focus:ring-2 focus:ring-brand/10"
                                placeholder="Computer Lab 1"
                            />
                            <span
                                v-if="labForm.errors.name"
                                class="mt-1 block text-xs text-red-600"
                            >
                                {{ labForm.errors.name }}
                            </span>
                        </label>
                        <label class="mt-3 block">
                            <span
                                class="block text-xs font-bold text-slate-500 uppercase"
                            >
                                Location
                            </span>
                            <input
                                v-model="labForm.location"
                                type="text"
                                class="mt-2 w-full rounded-md border border-slate-200 px-3 py-2 text-sm transition outline-none focus:border-brand focus:ring-2 focus:ring-brand/10"
                                placeholder="Building A"
                            />
                        </label>
                        <label class="mt-3 block">
                            <span
                                class="block text-xs font-bold text-slate-500 uppercase"
                            >
                                Description
                            </span>
                            <textarea
                                v-model="labForm.description"
                                rows="3"
                                class="mt-2 w-full rounded-md border border-slate-200 px-3 py-2 text-sm transition outline-none focus:border-brand focus:ring-2 focus:ring-brand/10"
                                placeholder="Optional room details"
                            />
                        </label>
                        <label class="mt-3 block">
                            <span
                                class="block text-xs font-bold text-slate-500 uppercase"
                            >
                                Status
                            </span>
                            <select
                                v-model="labForm.status"
                                class="mt-2 w-full rounded-md border border-slate-200 px-3 py-2 text-sm transition outline-none focus:border-brand focus:ring-2 focus:ring-brand/10"
                            >
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </label>
                        <button
                            type="submit"
                            :disabled="labForm.processing"
                            class="mt-4 w-full rounded-md bg-brand px-4 py-2 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{
                                labForm.processing
                                    ? 'Saving...'
                                    : editingLabId
                                      ? 'Update Laboratory'
                                      : 'Add Laboratory'
                            }}
                        </button>
                        <button
                            v-if="editingLabId"
                            type="button"
                            class="mt-2 w-full rounded-md border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700"
                            @click="resetLabForm"
                        >
                            Cancel editing
                        </button>
                    </form>
                </div>
            </section>

            <section
                class="rounded-md border border-slate-200 bg-white p-5 shadow-sm"
            >
                <div
                    class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                >
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">
                            Panel Devices
                        </h2>
                        <p class="text-sm text-slate-500">
                            Create and assign devices, change each device PIN,
                            enable or disable access, and manage live panels.
                        </p>
                    </div>
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-center"
                    >
                        <a
                            :href="route('attendanceControlPanel.login')"
                            class="rounded-md border border-slate-200 px-4 py-2 text-center text-sm font-bold text-slate-700 transition hover:bg-slate-50"
                        >
                            Panel Login
                        </a>
                    </div>
                </div>
            </section>

            <section
                class="rounded-md border border-slate-200 bg-white p-5 shadow-sm"
            >
                <div class="mb-4">
                    <h3 class="font-bold text-slate-900">
                        {{ editingDeviceId ? 'Edit Device' : 'Add Device' }}
                    </h3>
                    <p class="text-sm text-slate-500">
                        Assign one managed device to each laboratory. Its PIN is used when opening that laboratory's attendance panel.
                    </p>
                </div>
                <form class="grid gap-4 md:grid-cols-2 xl:grid-cols-5" @submit.prevent="saveDevice">
                    <label class="block">
                        <span class="text-xs font-bold uppercase text-slate-500">Laboratory</span>
                        <select v-model="deviceForm.laboratory_id" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Select laboratory</option>
                            <option v-for="laboratory in laboratories" :key="laboratory.laboratory_id" :value="String(laboratory.laboratory_id)">
                                {{ laboratory.name }}
                            </option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-xs font-bold uppercase text-slate-500">Device label</span>
                        <input v-model="deviceForm.label" required placeholder="LAB-1 Panel" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                    </label>
                    <label class="block">
                        <span class="text-xs font-bold uppercase text-slate-500">Description</span>
                        <input v-model="deviceForm.description" placeholder="Front desk terminal" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                    </label>
                    <label v-if="!editingDeviceId" class="block">
                        <span class="text-xs font-bold uppercase text-slate-500">Initial PIN</span>
                        <input v-model="deviceForm.pin" type="password" required minlength="4" placeholder="At least 4 characters" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                    </label>
                    <label class="flex items-center gap-2 self-end rounded-md border border-slate-200 px-3 py-2 text-sm font-semibold">
                        <input v-model="deviceForm.is_active" type="checkbox" />
                        Device enabled
                    </label>
                    <div class="flex items-end gap-2 md:col-span-2 xl:col-span-5">
                        <button type="submit" :disabled="deviceForm.processing" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-bold text-white disabled:opacity-60">
                            {{ editingDeviceId ? 'Update device' : 'Add device' }}
                        </button>
                        <button v-if="editingDeviceId" type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-bold" @click="resetDeviceForm">
                            Cancel
                        </button>
                    </div>
                    <p v-if="Object.keys(deviceForm.errors).length" class="text-sm text-red-600 md:col-span-2 xl:col-span-5">
                        {{ Object.values(deviceForm.errors)[0] }}
                    </p>
                </form>
            </section>

            <section
                class="rounded-md border border-slate-200 bg-white p-5 shadow-sm"
            >
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
                            <span
                                class="block text-sm font-bold text-slate-900"
                            >
                                Face Rekognition
                            </span>
                            <span class="block text-sm text-slate-500">
                                Require camera face verification before saving
                                attendance.
                            </span>
                        </span>
                    </label>
                    <button
                        type="submit"
                        :disabled="settingsForm.processing"
                        class="rounded-md bg-brand px-4 py-2 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {{
                            settingsForm.processing
                                ? 'Saving...'
                                : 'Save Setting'
                        }}
                    </button>
                </form>
            </section>

            <section
                class="rounded-md border border-amber-200 bg-amber-50 p-5 shadow-sm"
            >
                <div class="mb-3">
                    <h3 class="font-bold text-amber-900">Unassigned-panel fallback</h3>
                    <p class="text-sm text-amber-800">
                        Used only when a laboratory has no managed device. Normally, change PINs from the device table below.
                    </p>
                </div>
                <form
                    class="grid gap-4 lg:grid-cols-[1fr_220px_auto]"
                    @submit.prevent="savePanelAccess"
                >
                    <label class="block">
                        <span class="block text-sm font-bold text-slate-900">
                            Fallback Device Label
                        </span>
                        <input
                            v-model="panelAccessForm.device_label"
                            type="text"
                            class="mt-2 w-full rounded-md border border-slate-200 px-3 py-2 text-sm transition outline-none focus:border-brand focus:ring-2 focus:ring-brand/10"
                            placeholder="Attendance Console"
                        />
                    </label>
                    <label class="block">
                        <span class="block text-sm font-bold text-slate-900">
                            Fallback PIN
                        </span>
                        <input
                            v-model="panelAccessForm.pin"
                            type="password"
                            inputmode="numeric"
                            class="mt-2 w-full rounded-md border border-slate-200 px-3 py-2 text-sm transition outline-none focus:border-brand focus:ring-2 focus:ring-brand/10"
                            placeholder="Leave unchanged"
                        />
                    </label>
                    <div class="flex items-end">
                        <button
                            type="submit"
                            class="w-full rounded-md bg-brand px-4 py-2 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="!panelAccessForm.device_label"
                        >
                            Save Fallback
                        </button>
                    </div>
                </form>
            </section>

            <section
                class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm"
            >
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead
                            class="bg-slate-50 text-left text-xs font-bold text-slate-500 uppercase"
                        >
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
                                :key="device.panel_device_id"
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
                                    <div class="flex justify-end gap-2">
                                        <button
                                            type="button"
                                            class="rounded-md border border-blue-200 px-3 py-2 text-xs font-bold text-blue-700 hover:bg-blue-50"
                                            @click="editDevice(device)"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-md border border-slate-300 px-3 py-2 text-xs font-bold"
                                            @click="toggleDevice(device)"
                                        >
                                            {{ device.is_enabled ? 'Disable' : 'Enable' }}
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-md border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50"
                                            @click="changePanelPin(device)"
                                        >
                                            Change PIN
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-md border border-red-200 px-3 py-2 text-xs font-bold text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40"
                                            :disabled="!device.can_force_logout"
                                            @click="forceLogout(device)"
                                        >
                                            Log out panel
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-md border border-red-200 px-3 py-2 text-xs font-bold text-red-600 hover:bg-red-50"
                                            @click="deleteDevice(device)"
                                        >
                                            Delete
                                        </button>
                                    </div>
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
