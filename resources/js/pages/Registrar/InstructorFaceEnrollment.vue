<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Camera, Users } from 'lucide-vue-next';
import Swal from 'sweetalert2';

const props = defineProps({
    people: { type: Array, default: () => [] },
    stats: {
        type: Object,
        default: () => ({
            total: 0,
            missing_face: 0,
            complete: 0,
        }),
    },
});

const page = usePage();
const selectedPerson = ref(null);
const search = ref('');
const statusFilter = ref('missing');
const faceForm = useForm({ image: null });
const deleteFaceForm = useForm({});

const flashSuccess = computed(() => page.props.flash?.success);

const filteredPeople = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.people.filter((person) => {
        const matchesStatus =
            statusFilter.value === 'all' ||
            (statusFilter.value === 'missing' && !person.has_face) ||
            (statusFilter.value === 'complete' && person.has_face);
        const matchesSearch =
            !term ||
            [person.name, person.number, person.email, person.rfid_tag, person.strand]
                .filter(Boolean)
                .some((value) => String(value).toLowerCase().includes(term));

        return matchesStatus && matchesSearch;
    });
});

const openPerson = (person) => {
    selectedPerson.value = person;
    faceForm.image = null;
};

const setFaceFile = (event) => {
    faceForm.image = event.target.files?.[0] ?? null;
};

const uploadFace = () => {
    if (!selectedPerson.value) return;

    faceForm.post(route('registrar.faculty.face', { user: selectedPerson.value.id }), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            faceForm.reset('image');
            toast('Instructor face image submitted');
        },
    });
};

const removeFace = (index) => {
    if (!selectedPerson.value) return;

    deleteFaceForm.delete(route('registrar.faculty.face.delete', {
        user: selectedPerson.value.id,
        index,
    }), {
        preserveScroll: true,
        onSuccess: () => toast('Instructor face image removed'),
    });
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
        <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_380px]">
            <main class="min-w-0 space-y-4">
                <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h1 class="text-2xl font-bold text-slate-900">Instructor Face Enrollment</h1>
                            <p class="text-sm text-slate-500">Upload, remove, and replace instructor facial-recognition images.</p>
                        </div>
                        <p v-if="flashSuccess" class="rounded-md bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700">
                            {{ flashSuccess }}
                        </p>
                    </div>
                </section>

                <section class="grid grid-cols-1 gap-3 md:grid-cols-3">
                    <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase text-slate-400">Instructors</p>
                        <p class="mt-1 text-2xl font-bold text-slate-900">{{ stats.total }}</p>
                    </div>
                    <div class="rounded-md border border-red-200 bg-red-50 p-4">
                        <p class="text-xs font-semibold uppercase text-red-500">Missing Face</p>
                        <p class="mt-1 text-2xl font-bold text-red-700">{{ stats.missing_face }}</p>
                    </div>
                    <div class="rounded-md border border-emerald-200 bg-emerald-50 p-4">
                        <p class="text-xs font-semibold uppercase text-emerald-600">Complete</p>
                        <p class="mt-1 text-2xl font-bold text-emerald-700">{{ stats.complete }}</p>
                    </div>
                </section>

                <section class="rounded-md border border-slate-200 bg-white shadow-sm">
                    <div class="grid grid-cols-1 gap-3 border-b border-slate-100 p-4 md:grid-cols-[1fr_180px]">
                        <input v-model="search" type="text" placeholder="Search instructor, number, email..." class="rounded-md border border-slate-300 px-3 py-2 text-sm" />
                        <select v-model="statusFilter" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="missing">Needs Face</option>
                            <option value="complete">Complete</option>
                            <option value="all">All Status</option>
                        </select>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[760px] text-sm">
                            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Instructor</th>
                                    <th class="px-4 py-3">Number</th>
                                    <th class="px-4 py-3">Email</th>
                                    <th class="px-4 py-3">Face</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="person in filteredPeople" :key="person.id" class="border-t border-slate-100 hover:bg-slate-50">
                                    <td class="px-4 py-3 font-semibold text-slate-800">{{ person.name }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ person.number || '-' }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ person.email || '-' }}</td>
                                    <td class="px-4 py-3">
                                        <span :class="person.has_face ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'" class="rounded-md px-2 py-1 text-xs font-bold">
                                            {{ person.has_face ? `${person.face_count} image(s)` : 'Missing' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button type="button" class="rounded-md bg-brand px-3 py-2 text-xs font-bold text-white" @click="openPerson(person)">
                                            Manage
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>

            <aside class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
                <div v-if="selectedPerson" class="space-y-5">
                    <div>
                        <p class="text-xs font-semibold uppercase text-slate-400">Instructor</p>
                        <h2 class="mt-1 text-lg font-bold text-slate-900">{{ selectedPerson.name }}</h2>
                        <p class="text-sm text-slate-500">{{ selectedPerson.email || selectedPerson.number }}</p>
                    </div>

                    <form class="rounded-md border border-slate-200 p-4" @submit.prevent="uploadFace">
                        <div class="flex items-center gap-2 text-sm font-bold text-slate-800">
                            <Camera class="h-4 w-4 text-brand" />
                            Face Images
                        </div>
                        <div v-if="selectedPerson.face_images?.length" class="mt-3 grid grid-cols-3 gap-2">
                            <div v-for="(image, index) in selectedPerson.face_images" :key="image" class="relative">
                                <img :src="`/storage/${image}`" class="aspect-square w-full rounded-md border border-slate-200 object-cover" alt="Instructor face image" />
                                <button
                                    type="button"
                                    class="absolute right-1 top-1 rounded bg-rose-600 px-2 py-1 text-xs font-bold text-white"
                                    :disabled="deleteFaceForm.processing"
                                    @click="removeFace(index)"
                                >
                                    Remove
                                </button>
                            </div>
                        </div>
                        <p v-else class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700">
                            No instructor face images enrolled yet.
                        </p>
                        <p v-if="selectedPerson.face_count >= 5" class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700">
                            Maximum 5 face images enrolled. Remove one before uploading a replacement.
                        </p>
                        <input type="file" accept="image/png,image/jpeg,image/webp" class="mt-3 w-full rounded-md border border-dashed border-slate-300 px-3 py-2 text-sm" required @change="setFaceFile" />
                        <p v-if="faceForm.errors.image" class="mt-2 text-xs text-red-600">{{ faceForm.errors.image }}</p>
                        <button type="submit" class="mt-3 w-full rounded-md bg-slate-800 px-4 py-2 text-sm font-bold text-white" :disabled="selectedPerson.face_count >= 5 || faceForm.processing">
                            Upload Face
                        </button>
                    </form>
                </div>

                <div v-else class="flex min-h-[320px] flex-col items-center justify-center text-center text-slate-500">
                    <Users class="mb-3 h-10 w-10 text-slate-300" />
                    <p class="font-semibold">Select an instructor to manage face images.</p>
                </div>
            </aside>
        </div>
    </div>
</template>
