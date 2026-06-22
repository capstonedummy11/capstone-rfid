<script setup>
import CameraCapture from '@/components/CameraCapture.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Camera, CreditCard, Users } from 'lucide-vue-next';
import Swal from 'sweetalert2';

const props = defineProps({
    people: { type: Array, default: () => [] },
    stats: {
        type: Object,
        default: () => ({
            total: 0,
            students: 0,
            faculty: 0,
            missing_face: 0,
            missing_rfid: 0,
            complete: 0,
        }),
    },
});

const page = usePage();
const selectedPerson = ref(null);
const search = ref('');
const typeFilter = ref('');
const statusFilter = ref('missing');
const faceFileInputRef = ref(null);
const faceCameraRef = ref(null);
const showFaceCamera = ref(false);
const capturedFaceName = ref('');

const rfidForm = useForm({ rfid_tag: '' });
const faceForm = useForm({ image: null });

const flashSuccess = computed(() => page.props.flash?.success);

const filteredPeople = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.people.filter((person) => {
        const matchesType =
            !typeFilter.value || person.type === typeFilter.value;
        const matchesStatus =
            statusFilter.value === 'all' ||
            (statusFilter.value === 'missing' &&
                (!person.has_face || !person.has_rfid)) ||
            (statusFilter.value === 'complete' &&
                person.has_face &&
                person.has_rfid);
        const matchesSearch =
            !term ||
            [
                person.name,
                person.number,
                person.email,
                person.rfid_tag,
                person.section,
                person.strand,
            ]
                .filter(Boolean)
                .some((value) => String(value).toLowerCase().includes(term));

        return matchesType && matchesStatus && matchesSearch;
    });
});

const openPerson = (person) => {
    selectedPerson.value = person;
    rfidForm.rfid_tag = person.rfid_tag || '';
    faceForm.image = null;
    capturedFaceName.value = '';
    showFaceCamera.value = false;
    if (faceFileInputRef.value) {
        faceFileInputRef.value.value = '';
    }
};

const rfidRoute = computed(() => {
    if (!selectedPerson.value) return '';
    return selectedPerson.value.type === 'student'
        ? route('registrar.students.rfid', { student: selectedPerson.value.id })
        : route('registrar.faculty.rfid', { user: selectedPerson.value.id });
});

const faceRoute = computed(() => {
    if (!selectedPerson.value) return '';
    return selectedPerson.value.type === 'student'
        ? route('registrar.students.face', { student: selectedPerson.value.id })
        : route('registrar.faculty.face', { user: selectedPerson.value.id });
});

const saveRfid = () => {
    rfidForm.put(rfidRoute.value, {
        preserveScroll: true,
        onSuccess: () => toast('RFID card assigned'),
    });
};

const uploadFace = () => {
    faceForm.post(faceRoute.value, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            faceForm.reset('image');
            capturedFaceName.value = '';
            faceCameraRef.value?.resetCapture();
            if (faceFileInputRef.value) {
                faceFileInputRef.value.value = '';
            }
            toast('Face image submitted');
        },
    });
};

const setFaceFile = (event) => {
    faceForm.image = event.target.files?.[0] ?? null;
    capturedFaceName.value = '';
    faceCameraRef.value?.resetCapture();
};

const captureFace = () => {
    const dataUrl = faceCameraRef.value?.captureFrame();
    if (!dataUrl) return;

    const fileName = `${selectedPerson.value?.type || 'person'}-${selectedPerson.value?.id || 'face'}-camera.jpg`;
    faceForm.image = dataUrlToFile(dataUrl, fileName);
    capturedFaceName.value = fileName;
    if (faceFileInputRef.value) {
        faceFileInputRef.value.value = '';
    }
};

const retakeFace = () => {
    faceForm.image = null;
    capturedFaceName.value = '';
    faceCameraRef.value?.resetCapture();
};

const dataUrlToFile = (dataUrl, fileName) => {
    const [meta, base64] = dataUrl.split(',');
    const mime = meta.match(/:(.*?);/)?.[1] || 'image/jpeg';
    const binary = atob(base64);
    const bytes = new Uint8Array(binary.length);

    for (let index = 0; index < binary.length; index += 1) {
        bytes[index] = binary.charCodeAt(index);
    }

    return new File([bytes], fileName, { type: mime });
};

const toast = (title) => {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title,
        showConfirmButton: false,
        timer: 1600,
    });
};
</script>

<template>
    <div class="bg-slate-50 p-4">
        <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
            <main class="min-w-0 space-y-4">
                <section
                    class="rounded-md border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <div>
                            <h1 class="text-2xl font-bold text-slate-900">
                                Biometric Enrollment
                            </h1>
                            <p class="text-sm text-slate-500">
                                Submit face images and assign RFID cards for
                                students and faculty.
                            </p>
                        </div>
                        <p
                            v-if="flashSuccess"
                            class="rounded-md bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700"
                        >
                            {{ flashSuccess }}
                        </p>
                    </div>
                </section>

                <section class="grid grid-cols-1 gap-3 md:grid-cols-5">
                    <div
                        class="rounded-md border border-slate-200 bg-white p-4 shadow-sm"
                    >
                        <p
                            class="text-xs font-semibold text-slate-400 uppercase"
                        >
                            Total
                        </p>
                        <p class="mt-1 text-2xl font-bold text-slate-900">
                            {{ stats.total }}
                        </p>
                    </div>
                    <div
                        class="rounded-md border border-slate-200 bg-white p-4 shadow-sm"
                    >
                        <p
                            class="text-xs font-semibold text-slate-400 uppercase"
                        >
                            Students
                        </p>
                        <p class="mt-1 text-2xl font-bold text-brand">
                            {{ stats.students }}
                        </p>
                    </div>
                    <div
                        class="rounded-md border border-slate-200 bg-white p-4 shadow-sm"
                    >
                        <p
                            class="text-xs font-semibold text-slate-400 uppercase"
                        >
                            Faculty
                        </p>
                        <p class="mt-1 text-2xl font-bold text-slate-700">
                            {{ stats.faculty }}
                        </p>
                    </div>
                    <div class="rounded-md border border-red-200 bg-red-50 p-4">
                        <p class="text-xs font-semibold text-red-500 uppercase">
                            Missing Face
                        </p>
                        <p class="mt-1 text-2xl font-bold text-red-700">
                            {{ stats.missing_face }}
                        </p>
                    </div>
                    <div
                        class="rounded-md border border-amber-200 bg-amber-50 p-4"
                    >
                        <p
                            class="text-xs font-semibold text-amber-600 uppercase"
                        >
                            Missing RFID
                        </p>
                        <p class="mt-1 text-2xl font-bold text-amber-700">
                            {{ stats.missing_rfid }}
                        </p>
                    </div>
                </section>

                <section
                    class="rounded-md border border-slate-200 bg-white shadow-sm"
                >
                    <div
                        class="grid grid-cols-1 gap-3 border-b border-slate-100 p-4 md:grid-cols-[1fr_160px_160px]"
                    >
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search name, number, RFID..."
                            class="rounded-md border border-slate-300 px-3 py-2 text-sm"
                        />
                        <select
                            v-model="typeFilter"
                            class="rounded-md border border-slate-300 px-3 py-2 text-sm"
                        >
                            <option value="">All Types</option>
                            <option value="student">Students</option>
                            <option value="faculty">Faculty</option>
                        </select>
                        <select
                            v-model="statusFilter"
                            class="rounded-md border border-slate-300 px-3 py-2 text-sm"
                        >
                            <option value="missing">Needs Action</option>
                            <option value="complete">Complete</option>
                            <option value="all">All Status</option>
                        </select>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[860px] text-sm">
                            <thead
                                class="bg-slate-50 text-left text-xs text-slate-500 uppercase"
                            >
                                <tr>
                                    <th class="px-4 py-3">Name</th>
                                    <th class="px-4 py-3">Type</th>
                                    <th class="px-4 py-3">Number</th>
                                    <th class="px-4 py-3">Group</th>
                                    <th class="px-4 py-3">Face</th>
                                    <th class="px-4 py-3">RFID</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="person in filteredPeople"
                                    :key="`${person.type}-${person.id}`"
                                    class="border-t border-slate-100 hover:bg-slate-50"
                                >
                                    <td
                                        class="px-4 py-3 font-semibold text-slate-800"
                                    >
                                        {{ person.name }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-slate-600 capitalize"
                                    >
                                        {{ person.type }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">
                                        {{ person.number || '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">
                                        {{
                                            [person.strand, person.section]
                                                .filter(Boolean)
                                                .join(' / ') || '-'
                                        }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            :class="
                                                person.has_face
                                                    ? 'bg-emerald-50 text-emerald-700'
                                                    : 'bg-red-50 text-red-700'
                                            "
                                            class="rounded-md px-2 py-1 text-xs font-bold"
                                        >
                                            {{
                                                person.has_face
                                                    ? `${person.face_count} image(s)`
                                                    : 'Missing'
                                            }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            :class="
                                                person.has_rfid
                                                    ? 'bg-emerald-50 text-emerald-700'
                                                    : 'bg-amber-50 text-amber-700'
                                            "
                                            class="rounded-md px-2 py-1 text-xs font-bold"
                                        >
                                            {{ person.rfid_tag || 'Missing' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button
                                            type="button"
                                            class="rounded-md bg-brand px-3 py-2 text-xs font-bold text-white"
                                            @click="openPerson(person)"
                                        >
                                            Manage
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>

            <aside
                class="rounded-md border border-slate-200 bg-white p-5 shadow-sm"
            >
                <div v-if="selectedPerson" class="space-y-5">
                    <div>
                        <p
                            class="text-xs font-semibold text-slate-400 uppercase"
                        >
                            {{ selectedPerson.type }}
                        </p>
                        <h2 class="mt-1 text-lg font-bold text-slate-900">
                            {{ selectedPerson.name }}
                        </h2>
                        <p class="text-sm text-slate-500">
                            {{ selectedPerson.email || selectedPerson.number }}
                        </p>
                    </div>

                    <form
                        class="rounded-md border border-slate-200 p-4"
                        @submit.prevent="saveRfid"
                    >
                        <div
                            class="flex items-center gap-2 text-sm font-bold text-slate-800"
                        >
                            <CreditCard class="h-4 w-4 text-brand" />
                            RFID Card
                        </div>
                        <input
                            v-model="rfidForm.rfid_tag"
                            type="text"
                            class="mt-3 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                            placeholder="Scan or enter RFID"
                            required
                        />
                        <p
                            v-if="rfidForm.errors.rfid_tag"
                            class="mt-2 text-xs text-red-600"
                        >
                            {{ rfidForm.errors.rfid_tag }}
                        </p>
                        <button
                            type="submit"
                            class="mt-3 w-full rounded-md bg-brand px-4 py-2 text-sm font-bold text-white"
                        >
                            Save RFID
                        </button>
                    </form>

                    <form
                        class="rounded-md border border-slate-200 p-4"
                        @submit.prevent="uploadFace"
                    >
                        <div
                            class="flex items-center gap-2 text-sm font-bold text-slate-800"
                        >
                            <Camera class="h-4 w-4 text-brand" />
                            Face Image
                        </div>
                        <div
                            class="mt-3 flex items-center justify-between gap-3"
                        >
                            <button
                                type="button"
                                class="rounded-md border border-slate-300 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50"
                                @click="showFaceCamera = !showFaceCamera"
                            >
                                {{
                                    showFaceCamera
                                        ? 'Hide Camera'
                                        : 'Use Camera'
                                }}
                            </button>
                            <p
                                v-if="capturedFaceName"
                                class="truncate text-xs font-semibold text-emerald-700"
                            >
                                Captured photo ready
                            </p>
                        </div>

                        <div
                            v-if="showFaceCamera"
                            class="mt-3 rounded-md border border-slate-200 bg-slate-50 p-3"
                        >
                            <div class="flex flex-col items-center gap-3">
                                <CameraCapture ref="faceCameraRef" />
                                <div class="grid w-full grid-cols-2 gap-2">
                                    <button
                                        type="button"
                                        class="rounded-md bg-brand px-3 py-2 text-xs font-bold text-white"
                                        @click="captureFace"
                                    >
                                        Capture Photo
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-md border border-slate-300 px-3 py-2 text-xs font-bold text-slate-600"
                                        @click="retakeFace"
                                    >
                                        Retake
                                    </button>
                                </div>
                            </div>
                        </div>

                        <input
                            ref="faceFileInputRef"
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            class="mt-3 w-full rounded-md border border-dashed border-slate-300 px-3 py-2 text-sm"
                            @change="setFaceFile"
                        />
                        <p
                            v-if="faceForm.errors.image"
                            class="mt-2 text-xs text-red-600"
                        >
                            {{ faceForm.errors.image }}
                        </p>
                        <button
                            type="submit"
                            class="mt-3 w-full rounded-md bg-slate-800 px-4 py-2 text-sm font-bold text-white"
                        >
                            Upload Face
                        </button>
                    </form>
                </div>

                <div
                    v-else
                    class="flex min-h-[320px] flex-col items-center justify-center text-center text-slate-500"
                >
                    <Users class="mb-3 h-10 w-10 text-slate-300" />
                    <p class="font-semibold">
                        Select a person to manage RFID and face images.
                    </p>
                </div>
            </aside>
        </div>
    </div>
</template>
