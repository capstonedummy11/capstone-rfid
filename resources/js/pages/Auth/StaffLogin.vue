<script setup>
import featureImage from '@/assets/images/Container.png';
import featureImage2 from '@/assets/images/Container 2.png';
import featureImage3 from '@/assets/images/Container 3.png';
import container4 from '@/assets/images/Container 4.png';
import logo from '@/assets/images/logo-only.jpg';
import schoolPhoto from '@/assets/images/philsca.png';
import {
    getSavedStaffProfiles,
    removeSavedStaffProfile,
    setStaffSavePreference,
} from '@/composables/useSavedStudentParentProfiles';
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowRight, Eye, EyeOff, ShieldCheck, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const profiles = ref([]);
const selectedIndex = ref(0);
const useDifferentAccount = ref(true);
const saveOnDevice = ref(false);
const showPassword = ref(false);
const activeSlideIndex = ref(0);
let carouselTimer = null;

const carouselSlides = [
    {
        image: schoolPhoto,
        label: 'Secure staff access',
        title: 'RFID-Based Attendance Monitoring and Management System',
        text: 'Continue to role-based workspaces for admin, instructor, clinic, and registrar operations.',
    },
    {
        image: featureImage,
        label: 'Attendance monitoring',
        title: 'Fast access for daily school operations',
        text: 'Review attendance activity, staff workflows, and portal records from a secure staff login.',
    },
    {
        image: featureImage2,
        label: 'Online class tools',
        title: 'Keep learning records connected',
        text: 'Manage class access, verification, and student activity with a focused staff experience.',
    },
    {
        image: featureImage3,
        label: 'Registrar and clinic support',
        title: 'Move between records with confidence',
        text: 'Open the right workspace for enrollment, clinic cases, and administrative follow-up.',
    },
    {
        image: container4,
        label: 'RFID control panel',
        title: 'Use the attendance console when needed',
        text: 'Staff can jump to the attendance control panel from this secure login screen.',
    },
];

const selectedProfile = computed(
    () => profiles.value[selectedIndex.value] ?? null,
);

const activeSlide = computed(() => carouselSlides[activeSlideIndex.value]);

const form = useForm({
    email: '',
    password: '',
    remember: true,
});

const helperText = computed(() =>
    useDifferentAccount.value || !selectedProfile.value
        ? 'Use an authorized admin, instructor, registrar, or clinic account.'
        : `Welcome back, ${selectedProfile.value.name}. Enter your password to continue.`,
);

const selectProfile = (index) => {
    selectedIndex.value = index;
    useDifferentAccount.value = false;
    form.email = selectedProfile.value.email;
    form.password = '';
    form.clearErrors();
};

const showDifferentAccount = () => {
    useDifferentAccount.value = true;
    form.email = '';
    form.password = '';
    saveOnDevice.value = false;
    form.clearErrors();
};

const goToSlide = (index) => {
    activeSlideIndex.value = index;
};

const removeProfile = (index) => {
    const profile = profiles.value[index];

    if (!profile) return;

    profiles.value = removeSavedStaffProfile(profile.email);
    selectedIndex.value = Math.min(
        index,
        Math.max(profiles.value.length - 1, 0),
    );

    if (profiles.value.length === 0) {
        showDifferentAccount();
        return;
    }

    selectProfile(selectedIndex.value);
};

const submit = () => {
    const shouldSave = !useDifferentAccount.value ? true : saveOnDevice.value;

    if (!useDifferentAccount.value && selectedProfile.value) {
        form.email = selectedProfile.value.email;
    }

    setStaffSavePreference(form.email, shouldSave);
    form.post(route('staff.login.store'));
};

onMounted(() => {
    profiles.value = getSavedStaffProfiles();

    if (profiles.value.length > 0) {
        selectProfile(0);
    }

    carouselTimer = window.setInterval(() => {
        activeSlideIndex.value =
            (activeSlideIndex.value + 1) % carouselSlides.length;
    }, 4500);
});

onUnmounted(() => {
    if (carouselTimer) {
        window.clearInterval(carouselTimer);
    }
});
</script>

<template>
    <div class="min-h-screen bg-white p-2">
        <main
            class="grid min-h-[calc(100vh-1rem)] overflow-hidden bg-white lg:grid-cols-[42%_58%]"
        >
            <section
                class="relative hidden overflow-hidden bg-slate-900 lg:block"
            >
                <Transition
                    enter-active-class="transition duration-700 ease-out"
                    enter-from-class="opacity-0 scale-105"
                    enter-to-class="opacity-100 scale-100"
                    leave-active-class="absolute inset-0 transition duration-700 ease-in"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-95"
                >
                    <img
                        :key="activeSlide.image"
                        :src="activeSlide.image"
                        alt="Staff portal visual"
                        class="absolute inset-0 h-full w-full object-cover"
                    />
                </Transition>
                <div class="absolute inset-0 bg-[#082f49]/45" />

                <div
                    class="absolute top-10 left-12 flex items-center gap-3 text-white"
                >
                    <img
                        :src="logo"
                        alt="School logo"
                        class="h-11 w-11 rounded-full bg-white/90 p-1"
                    />
                    <span class="text-3xl font-black tracking-tight">
                        PCSHS
                    </span>
                </div>

                <div class="absolute right-12 bottom-12 left-12 text-white">
                    <div
                        class="inline-flex items-center gap-2 rounded-full bg-white/15 px-4 py-2 text-xs font-bold backdrop-blur"
                    >
                        <ShieldCheck class="h-4 w-4" />
                        {{ activeSlide.label }}
                    </div>
                    <h1 class="mt-5 max-w-lg text-4xl leading-tight font-black">
                        {{ activeSlide.title }}
                    </h1>
                    <p class="mt-4 max-w-md text-sm leading-6 text-white/85">
                        {{ activeSlide.text }}
                    </p>
                </div>

                <div
                    class="absolute right-0 bottom-8 left-0 flex justify-center gap-2"
                >
                    <button
                        v-for="(_, index) in carouselSlides"
                        :key="index"
                        type="button"
                        class="h-3 rounded-full transition"
                        :class="
                            activeSlideIndex === index
                                ? 'w-7 bg-sky-400'
                                : 'w-3 bg-white/80 hover:bg-white'
                        "
                        :aria-label="`Show staff login slide ${index + 1}`"
                        @click="goToSlide(index)"
                    />
                </div>
            </section>

            <section class="flex items-center justify-center px-6 py-10">
                <div class="w-full max-w-md">
                    <div class="mb-10 flex items-center gap-3 lg:hidden">
                        <img
                            :src="logo"
                            alt="School logo"
                            class="h-12 w-12 rounded-full bg-white p-1 shadow"
                        />
                        <div>
                            <p class="text-lg font-black text-slate-900">
                                PCSHS
                            </p>
                            <p class="text-xs font-bold text-slate-400">
                                Secure staff portal
                            </p>
                        </div>
                    </div>

                    <header>
                        <p
                            class="text-xs font-black tracking-[0.22em] text-blue-600 uppercase"
                        >
                            Staff Login
                        </p>
                        <h1 class="mt-3 text-3xl font-black text-slate-900">
                            Login
                        </h1>
                        <p class="mt-2 text-sm font-semibold text-slate-500">
                            Please select your Profile
                        </p>
                    </header>

                    <div
                        v-if="!useDifferentAccount && selectedProfile"
                        class="mt-9"
                    >
                        <p
                            class="mb-3 text-xs font-black tracking-wide text-slate-400 uppercase"
                        >
                            Account Ready {{ profiles.length }}
                        </p>
                        <button
                            type="button"
                            class="flex w-full items-center justify-between rounded-md border border-slate-200 bg-white p-3 text-left shadow-sm transition hover:border-sky-300"
                            @click="selectProfile(selectedIndex)"
                        >
                            <span class="flex min-w-0 items-center gap-3">
                                <span
                                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-slate-200 text-sm font-black text-slate-700"
                                >
                                    {{ selectedProfile.initials }}
                                </span>
                                <span class="min-w-0">
                                    <span
                                        class="block truncate text-sm font-bold text-slate-900"
                                    >
                                        Welcome back, {{ selectedProfile.name }}
                                    </span>
                                    <span
                                        class="block truncate text-xs font-semibold text-slate-400"
                                    >
                                        {{ selectedProfile.role }}
                                    </span>
                                </span>
                            </span>
                            <span
                                class="inline-flex items-center gap-1 text-xs font-black text-red-500"
                                @click.stop="removeProfile(selectedIndex)"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                                Remove
                            </span>
                        </button>

                        <div class="mt-4 flex justify-center gap-2">
                            <button
                                v-for="(_, index) in profiles"
                                :key="index"
                                type="button"
                                class="h-3 rounded-full transition"
                                :class="
                                    selectedIndex === index
                                        ? 'w-7 bg-blue-600'
                                        : 'w-3 bg-slate-300'
                                "
                                :aria-label="`Select ready account ${index + 1}`"
                                @click="selectProfile(index)"
                            />
                        </div>

                        <label
                            class="mt-5 block text-sm font-bold text-slate-700"
                        >
                            Password
                            <span class="relative mt-2 block">
                                <input
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    class="h-12 w-full rounded-md border border-slate-300 px-3 pr-10 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
                                    required
                                    autocomplete="current-password"
                                    @keydown.enter.prevent="submit"
                                />
                                <button
                                    type="button"
                                    class="absolute top-1/2 right-3 -translate-y-1/2 text-slate-400 transition hover:text-slate-700"
                                    :aria-label="
                                        showPassword
                                            ? 'Hide password'
                                            : 'Show password'
                                    "
                                    @click="showPassword = !showPassword"
                                >
                                    <EyeOff
                                        v-if="showPassword"
                                        class="h-4 w-4"
                                    />
                                    <Eye v-else class="h-4 w-4" />
                                </button>
                            </span>
                        </label>
                    </div>

                    <form
                        v-else
                        class="mt-9 space-y-4"
                        @submit.prevent="submit"
                    >
                        <label class="block text-sm font-bold text-slate-700">
                            Email
                            <input
                                v-model="form.email"
                                type="email"
                                class="mt-2 h-12 w-full rounded-md border border-slate-300 px-3 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
                                required
                                autocomplete="email"
                            />
                        </label>
                        <label class="block text-sm font-bold text-slate-700">
                            Password
                            <span class="relative mt-2 block">
                                <input
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    class="h-12 w-full rounded-md border border-slate-300 px-3 pr-10 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
                                    required
                                    autocomplete="current-password"
                                />
                                <button
                                    type="button"
                                    class="absolute top-1/2 right-3 -translate-y-1/2 text-slate-400 transition hover:text-slate-700"
                                    :aria-label="
                                        showPassword
                                            ? 'Hide password'
                                            : 'Show password'
                                    "
                                    @click="showPassword = !showPassword"
                                >
                                    <EyeOff
                                        v-if="showPassword"
                                        class="h-4 w-4"
                                    />
                                    <Eye v-else class="h-4 w-4" />
                                </button>
                            </span>
                        </label>
                        <label
                            class="flex items-center gap-2 text-sm font-semibold text-slate-600"
                        >
                            <input
                                v-model="saveOnDevice"
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                            />
                            Save this account on this device
                        </label>
                    </form>

                    <p class="mt-5 text-sm font-semibold text-slate-500">
                        {{ helperText }}
                    </p>
                    <p
                        v-if="form.errors.email"
                        class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm font-semibold text-red-600"
                    >
                        {{ form.errors.email }}
                    </p>
                    <p
                        v-if="form.errors.password"
                        class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm font-semibold text-red-600"
                    >
                        {{ form.errors.password }}
                    </p>
                    <Link
                        :href="route('password.request')"
                        class="mt-3 inline-block text-sm font-semibold text-blue-600 hover:text-blue-700"
                    >
                        Forgot password?
                    </Link>

                    <div class="mt-10 flex items-center justify-between gap-4">
                        <button
                            v-if="profiles.length > 0"
                            type="button"
                            class="text-left text-sm font-semibold text-slate-500 hover:text-sky-600"
                            @click="
                                useDifferentAccount && profiles.length > 0
                                    ? selectProfile(selectedIndex)
                                    : showDifferentAccount()
                            "
                        >
                            {{
                                useDifferentAccount && profiles.length > 0
                                    ? 'Back to saved profiles'
                                    : 'Login to a different account'
                            }}
                        </button>
                        <button
                            type="button"
                            :disabled="form.processing"
                            class="inline-flex h-12 items-center gap-3 rounded-md bg-blue-600 px-8 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 disabled:opacity-60"
                            @click="submit"
                        >
                            {{ form.processing ? 'Logging in...' : 'Login' }}
                            <ArrowRight class="h-4 w-4" />
                        </button>
                    </div>

                    <div
                        class="mt-8 flex flex-col gap-3 border-t border-slate-300 pt-5 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <p class="text-xs font-semibold text-slate-400">
                            Staff access only.
                        </p>
                        <div
                            class="flex flex-col gap-2 sm:flex-row sm:items-center"
                        >
                            <Link
                                :href="route('attendanceControlPanel.login')"
                                class="inline-flex h-9 items-center justify-center rounded-md bg-slate-900 px-4 text-xs font-black text-white transition hover:bg-slate-700"
                            >
                                Attendance Panel
                            </Link>
                            <Link
                                href="/"
                                class="inline-flex h-9 items-center justify-center rounded-md border border-slate-300 px-4 text-xs font-black text-blue-600 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700"
                            >
                                Student / Parent
                            </Link>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</template>
