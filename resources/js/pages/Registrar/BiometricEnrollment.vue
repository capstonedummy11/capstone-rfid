<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Camera, CreditCard, Users } from 'lucide-vue-next';
import Swal from 'sweetalert2';
import CameraCapture from '@/components/CameraCapture.vue';

const props = defineProps({
    people: { type: Array, default: () => [] },
    stats: {
        type: Object,
        default: () => ({
            total: 0,
            students: 0,
            missing_face: 0,
            missing_rfid: 0,
            complete: 0,
        }),
    },
});

const page = usePage();
const selectedPerson = ref(null);
const search = ref('');
const statusFilter = ref('missing');

const rfidForm = useForm({ rfid_tag: '' });
const faceForm = useForm({ image: null });
const deleteFaceForm = useForm({});
const showCamera = ref(false);
const cameraRef = ref(null);
const fileInputRef = ref(null);

const flashSuccess = computed(() => page.props.flash?.success);

const filteredPeople = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.people.filter((person) => {
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

        return matchesStatus && matchesSearch;
    });
});

const openPerson = (person) => {
    selectedPerson.value = person;
    rfidForm.rfid_tag = person.rfid_tag || '';
    faceForm.image = null;
    showCamera.value = false;
};

const rfidRoute = computed(() => {
    if (!selectedPerson.value) return '';
    return route('registrar.students.rfid', {
        student: selectedPerson.value.id,
    });
});

const faceRoute = computed(() => {
    if (!selectedPerson.value) return '';
    return route('registrar.students.face', {
        student: selectedPerson.value.id,
    });
});

const saveRfid = () => {
    rfidForm.put(rfidRoute.value, {
        preserveScroll: true,
        onSuccess: () => toast('RFID card assigned'),
    });
};

const uploadFace = () => {
    if (selectedPerson.value?.type !== 'student') return;

    faceForm.post(faceRoute.value, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            faceForm.reset('image');
            cameraRef.value?.resetCapture();
            showCamera.value = false;
            if (fileInputRef.value) fileInputRef.value.value = '';
            toast('Face image submitted');
        },
    });
};

const setFaceFile = (event) => {
    faceForm.image = event.target.files?.[0] ?? null;
};

const useFileUpload = () => {
    faceForm.image = null;
    showCamera.value = false;
    faceForm.clearErrors('image');
};

const useCamera = () => {
    faceForm.image = null;
    showCamera.value = true;
    faceForm.clearErrors('image');
};

const captureFace = async () => {
    const dataUrl = cameraRef.value?.captureFrame();
    if (!dataUrl) {
        faceForm.setError(
            'image',
            'Camera is not ready. Allow camera access and try again.',
        );
        return;
    }

    const blob = await (await fetch(dataUrl)).blob();
    faceForm.image = new File([blob], `student-face-${Date.now()}.jpg`, {
        type: 'image/jpeg',
    });
};

const retakeFace = () => {
    faceForm.image = null;
    faceForm.clearErrors('image');
    cameraRef.value?.resetCapture();
};

const removeFace = (index) => {
    if (selectedPerson.value?.type !== 'student') return;

    deleteFaceForm.delete(
        route('registrar.students.face.delete', {
            student: selectedPerson.value.id,
            index,
        }),
        {
            preserveScroll: true,
            onSuccess: () => toast('Face image removed'),
        },
    );
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
                                Student Biometric Enrollment
                            </h1>
                            <p class="text-sm text-slate-500">
                                Submit face images and assign RFID cards for
                                students.
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

                <section class="grid grid-cols-1 gap-3 md:grid-cols-4">
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
                        class="grid grid-cols-1 gap-3 border-b border-slate-100 p-4 md:grid-cols-[1fr_160px]"
                    >
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search name, number, RFID..."
                            class="rounded-md border border-slate-300 px-3 py-2 text-sm"
                        />
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
                            v-if="selectedPerson.face_images?.length"
                            class="mt-3 grid grid-cols-3 gap-2"
                        >
                            <div
                                v-for="(
                                    image, index
                                ) in selectedPerson.face_images"
                                :key="image"
                                class="relative"
                            >
                                <img
                                    :src="`/storage/${image}`"
                                    class="aspect-square w-full rounded-md border border-slate-200 object-cover"
                                    alt="Student face image"
                                />
                                <button
                                    type="button"
                                    class="absolute top-1 right-1 rounded bg-rose-600 px-2 py-1 text-xs font-bold text-white"
                                    :disabled="deleteFaceForm.processing"
                                    @click="removeFace(index)"
                                >
                                    Remove
                                </button>
                            </div>
                        </div>
                        <p
                            v-if="selectedPerson.face_count >= 5"
                            class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700"
                        >
                            Maximum 5 face images enrolled. Remove one before
                            uploading a replacement.
                        </p>
                        <div
                            class="mt-3 grid grid-cols-2 gap-2 rounded-md bg-slate-50 p-1"
                        >
                            <button
                                type="button"
                                class="rounded px-3 py-2 text-xs font-bold"
                                :class="
                                    !showCamera
                                        ? 'bg-white text-slate-900 shadow-sm'
                                        : 'text-slate-500'
                                "
                                @click="useFileUpload"
                            >
                                Upload File
                            </button>
                            <button
                                type="button"
                                class="rounded px-3 py-2 text-xs font-bold"
                                :class="
                                    showCamera
                                        ? 'bg-white text-slate-900 shadow-sm'
                                        : 'text-slate-500'
                                "
                                :disabled="selectedPerson.face_count >= 5"
                                @click="useCamera"
                            >
                                Use Camera
                            </button>
                        </div>
                        <div
                            v-if="showCamera"
                            class="mt-3 rounded-md border border-slate-200 p-3"
                        >
                            <p class="mb-3 text-xs text-slate-500">
                                Center the student's face in the frame with good
                                lighting.
                            </p>
                            <div class="flex justify-center">
                                <CameraCapture ref="cameraRef" />
                            </div>
                            <div class="mt-3 grid grid-cols-2 gap-2">
                                <button
                                    v-if="!faceForm.image"
                                    type="button"
                                    class="col-span-2 rounded-md bg-brand px-3 py-2 text-xs font-bold text-white disabled:opacity-50"
                                    :disabled="
                                        faceForm.processing ||
                                        selectedPerson.face_count >= 5
                                    "
                                    @click="captureFace"
                                >
                                    Capture Photo
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    class="rounded-md bg-brand px-3 py-2 text-xs font-bold text-white disabled:opacity-50"
                                    :disabled="
                                        faceForm.processing ||
                                        selectedPerson.face_count >= 5
                                    "
                                    @click="uploadFace"
                                >
                                    Save Captured Face
                                </button>
                                <button
                                    type="button"
                                    class="rounded-md border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700"
                                    :disabled="faceForm.processing"
                                    @click="retakeFace"
                                >
                                    Retake
                                </button>
                            </div>
                        </div>
                        <template v-else>
                            <input
                                ref="fileInputRef"
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                class="mt-3 w-full rounded-md border border-dashed border-slate-300 px-3 py-2 text-sm"
                                :disabled="selectedPerson.face_count >= 5"
                                @change="setFaceFile"
                            />
                            <button
                                type="submit"
                                class="mt-3 w-full rounded-md bg-slate-800 px-4 py-2 text-sm font-bold text-white disabled:opacity-50"
                                :disabled="
                                    selectedPerson.face_count >= 5 ||
                                    faceForm.processing ||
                                    !faceForm.image
                                "
                            >
                                Upload Face
                            </button>
                        </template>
                        <p
                            v-if="faceForm.errors.image"
                            class="mt-2 text-xs text-red-600"
                        >
                            {{ faceForm.errors.image }}
                        </p>
                    </form>

                </div>

                <div
                    v-else
                    class="flex min-h-[320px] flex-col items-center justify-center text-center text-slate-500"
                >
                    <Users class="mb-3 h-10 w-10 text-slate-300" />
                    <p class="font-semibold">
                        Select a student to manage RFID and face images.
                    </p>
                </div>
            </aside>
        </div>
    </div>
</template>
