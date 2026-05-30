<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import { computed } from 'vue';

const props = defineProps({
    featureSettings: {
        type: Object,
        default: () => ({
            borrowing_enabled: false,
            inventory_enabled: false,
            face_recognition_enabled: true,
        }),
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

const form = useForm({
    borrowing_enabled: Boolean(props.featureSettings.borrowing_enabled),
    inventory_enabled: Boolean(props.featureSettings.inventory_enabled),
    face_recognition_enabled: Boolean(
        props.featureSettings.face_recognition_enabled,
    ),
});

const saveSettings = () => {
    form.put(route('admin.settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Settings updated',
                showConfirmButton: false,
                timer: 1800,
                timerProgressBar: true,
            });
        },
    });
};
</script>

<template>
    <div class="p-4 sm:p-6">
        <div class="mx-auto flex max-w-4xl flex-col gap-4">
            <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-2 border-b border-slate-100 pb-4">
                    <h2 class="text-lg font-bold text-slate-900">System Settings</h2>
                    <p class="text-sm text-slate-500">
                        Control which system modules are available to users and the attendance panel.
                    </p>
                    <p
                        v-if="flashSuccess"
                        class="rounded-md bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700"
                    >
                        {{ flashSuccess }}
                    </p>
                </div>

                <form class="mt-4 flex flex-col gap-4" @submit.prevent="saveSettings">
                    <label
                        class="flex items-center justify-between gap-4 rounded-md border border-slate-200 p-4"
                    >
                        <span class="min-w-0">
                            <span class="block text-sm font-bold text-slate-900">
                                Borrowing
                            </span>
                            <span class="block text-sm text-slate-500">
                                Enables the admin borrowing page and borrowing mode on the attendance panel.
                            </span>
                        </span>
                        <input
                            v-model="form.borrowing_enabled"
                            type="checkbox"
                            class="h-5 w-5 shrink-0 accent-brand"
                        />
                    </label>

                    <label
                        class="flex items-center justify-between gap-4 rounded-md border border-slate-200 p-4"
                    >
                        <span class="min-w-0">
                            <span class="block text-sm font-bold text-slate-900">
                                Inventory
                            </span>
                            <span class="block text-sm text-slate-500">
                                Enables the admin inventory page and item management actions.
                            </span>
                        </span>
                        <input
                            v-model="form.inventory_enabled"
                            type="checkbox"
                            class="h-5 w-5 shrink-0 accent-brand"
                        />
                    </label>

                    <label
                        class="flex items-center justify-between gap-4 rounded-md border border-slate-200 p-4"
                    >
                        <span class="min-w-0">
                            <span class="block text-sm font-bold text-slate-900">
                                Face Rekognition
                            </span>
                            <span class="block text-sm text-slate-500">
                                Requires camera face verification before attendance is recorded.
                            </span>
                        </span>
                        <input
                            v-model="form.face_recognition_enabled"
                            type="checkbox"
                            class="h-5 w-5 shrink-0 accent-brand"
                        />
                    </label>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-md bg-brand px-4 py-2 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{ form.processing ? 'Saving...' : 'Save Settings' }}
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</template>
