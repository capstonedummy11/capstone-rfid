<script setup>
import logo from '@/assets/images/logo.png';
import philsca from '@/assets/images/philsca.png';
import featureImage from '@/assets/images/Container.png';
import featureImage2 from '@/assets/images/Container 2.png';
import featureImage3 from '@/assets/images/Container 3.png';
import container4 from '@/assets/images/Container 4.png';
import item1 from '@/assets/images/Item 1.png';
import item2 from '@/assets/images/Item 2.png';
import item3 from '@/assets/images/Item 3.png';
import {
    getSavedStudentParentProfiles,
    removeSavedStudentParentProfile,
    setStudentParentSavePreference,
} from '@/composables/useSavedStudentParentProfiles';
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowRight, Eye, EyeOff, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const isMenuOpen = ref(false);
const showLoginPanel = ref(false);
const profiles = ref([]);
const selectedIndex = ref(0);
const useDifferentAccount = ref(true);
const saveOnDevice = ref(false);
const showPassword = ref(false);

const selectedProfile = computed(
    () => profiles.value[selectedIndex.value] ?? null,
);

const form = useForm({
    email: '',
    password: '',
    remember: true,
});

const helperText = computed(() =>
    useDifferentAccount.value || !selectedProfile.value
        ? 'Enter your Student or Parent account credentials.'
        : `Welcome back, ${selectedProfile.value.name}. Enter your password to continue.`,
);

const featureCards = [
    {
        image: featureImage,
        title: 'ATTENDANCE TRACKING',
        sub: 'View attendance records, time logs, class participation, and portal updates from one student-centered dashboard.',
    },
    {
        image: featureImage2,
        title: 'ONLINE CLASSES',
        sub: 'Access class sessions, meeting links, notifications, and join records through the student and parent portal.',
    },
    {
        image: featureImage3,
        title: 'MESSAGES',
        sub: 'Send portal messages, manage conversations, and keep school communication organized in one place.',
    },
];

const showcaseItems = [
    {
        number: '01',
        title: 'Student Dashboard',
        text: 'Students can review attendance summaries, online class activity, profile details, and portal notifications.',
    },
    {
        number: '02',
        title: 'Parent Access',
        text: 'Linked parents can view student records and switch between linked students when more than one child is assigned.',
    },
    {
        number: '03',
        title: 'Portal Records',
        text: 'Messages, excuse letters, attendance history, and online class joins stay connected to the student account.',
    },
];

const galleryImages = [
    {
        src: item1,
        alt: 'Student portal preview one',
    },
    {
        src: item2,
        alt: 'Student portal preview two',
    },
    {
        src: item3,
        alt: 'Student portal preview three',
    },
];

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

const removeProfile = (index) => {
    const profile = profiles.value[index];

    if (!profile) return;

    profiles.value = removeSavedStudentParentProfile(profile.email);
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

const login = () => {
    const shouldSave = !useDifferentAccount.value ? true : saveOnDevice.value;

    if (!useDifferentAccount.value && selectedProfile.value) {
        form.email = selectedProfile.value.email;
    }

    setStudentParentSavePreference(form.email, shouldSave);
    form.post(route('student-parent.login.store'));
};

const revealLoginPanel = () => {
    showLoginPanel.value = true;
    isMenuOpen.value = false;
};

onMounted(() => {
    profiles.value = getSavedStudentParentProfiles();

    if (profiles.value.length > 0) {
        selectProfile(0);
    }
});
</script>

<template>
    <div class="min-h-screen bg-white font-raleway text-default">
        <header
            class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur"
        >
            <nav
                class="mx-auto flex w-full max-w-[1400px] items-center justify-between px-5 py-4 md:px-12"
            >
                <a href="/" class="flex items-center gap-3">
                    <img :src="logo" alt="RFID logo" class="h-auto w-[170px]" />
                </a>

                <div
                    class="hidden items-center gap-7 text-sm font-semibold text-default md:flex"
                >
                    <a href="/" class="transition hover:text-brand">Home</a>
                    <a href="#about_us" class="transition hover:text-brand">
                        About Us
                    </a>
                    <button
                        type="button"
                        class="border-2 border-brand bg-brand px-7 py-2 text-center text-white transition hover:bg-[#006da5]"
                        @click="revealLoginPanel"
                    >
                        Login
                    </button>
                </div>

                <button
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center border border-slate-300 text-default md:hidden"
                    aria-label="Open menu"
                    @click="isMenuOpen = !isMenuOpen"
                >
                    <span class="text-2xl leading-none">
                        {{ isMenuOpen ? 'x' : '=' }}
                    </span>
                </button>
            </nav>

            <div
                v-if="isMenuOpen"
                class="border-t border-slate-200 bg-white px-5 py-4 md:hidden"
            >
                <div
                    class="flex flex-col gap-4 text-sm font-semibold text-default"
                >
                    <a href="/" @click="isMenuOpen = false">Home</a>
                    <a href="#about_us" @click="isMenuOpen = false">
                        About Us
                    </a>
                    <button
                        type="button"
                        class="bg-brand px-5 py-2 text-center text-white"
                        @click="revealLoginPanel"
                    >
                        Login
                    </button>
                </div>
            </div>
        </header>

        <main>
            <section class="relative min-h-[calc(100vh-74px)] overflow-hidden">
                <img
                    :src="philsca"
                    alt="Philsca campus"
                    class="absolute inset-0 h-full w-full object-cover"
                />
                <div class="absolute inset-0 bg-[#193153]/60" />

                <div
                    class="relative mx-auto grid min-h-[calc(100vh-74px)] w-full max-w-[1400px] items-center gap-10 px-7 py-16 text-white md:px-12"
                    :class="
                        showLoginPanel
                            ? 'lg:grid-cols-[1.05fr_0.72fr]'
                            : 'lg:grid-cols-1'
                    "
                >
                    <div>
                        <p
                            class="text-sm font-bold tracking-[0.25em] text-white/70 uppercase"
                        >
                            Student and Parent Portal
                        </p>
                        <h1
                            class="mt-5 text-[34px] leading-tight font-semibold md:text-[54px]"
                        >
                            RFID-Based Attendance
                            <br />
                            Monitoring, Borrowing, and
                            <br />
                            Inventory Management System
                        </h1>
                        <p
                            class="mt-6 max-w-2xl text-base leading-7 text-white/90 md:text-lg"
                        >
                            Access attendance records, online classes, excuse
                            letters, messages, and notifications through one
                            connected portal.
                        </p>
                        <button
                            type="button"
                            class="mt-8 inline-flex w-full max-w-[300px] items-center justify-center border-2 border-white bg-brand px-6 py-3 text-sm font-bold tracking-wide text-white transition hover:bg-black/50 sm:w-[300px]"
                            @click="revealLoginPanel"
                        >
                            GET STARTED TODAY
                        </button>
                    </div>

                    <Transition
                        enter-active-class="transition duration-300 ease-out"
                        enter-from-class="translate-x-12 opacity-0"
                        enter-to-class="translate-x-0 opacity-100"
                        leave-active-class="transition duration-200 ease-in"
                        leave-from-class="translate-x-0 opacity-100"
                        leave-to-class="translate-x-12 opacity-0"
                    >
                        <section
                            v-if="showLoginPanel"
                            id="student_login"
                            class="bg-white p-6 text-slate-900 shadow-2xl ring-1 ring-white/30 md:p-8"
                        >
                            <header>
                                <p
                                    class="text-xs font-black tracking-[0.22em] text-brand uppercase"
                                >
                                    Portal Login
                                </p>
                                <h2 class="mt-3 text-3xl font-black">Login</h2>
                                <p
                                    class="mt-2 text-sm font-semibold text-slate-500"
                                >
                                    Please select your Profile
                                </p>
                            </header>

                            <div
                                v-if="!useDifferentAccount && selectedProfile"
                                class="mt-8"
                            >
                                <p
                                    class="mb-3 text-xs font-black tracking-wide text-slate-400 uppercase"
                                >
                                    Account Ready {{ profiles.length }}
                                </p>
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-between border border-slate-200 bg-white p-3 text-left shadow-sm transition hover:border-sky-300"
                                    @click="selectProfile(selectedIndex)"
                                >
                                    <span
                                        class="flex min-w-0 items-center gap-3"
                                    >
                                        <span
                                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-slate-200 text-sm font-black text-slate-700"
                                        >
                                            {{ selectedProfile.initials }}
                                        </span>
                                        <span class="min-w-0">
                                            <span
                                                class="block truncate text-sm font-bold"
                                            >
                                                Welcome back,
                                                {{ selectedProfile.name }}
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
                                        @click.stop="
                                            removeProfile(selectedIndex)
                                        "
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
                                            :type="
                                                showPassword
                                                    ? 'text'
                                                    : 'password'
                                            "
                                            class="h-12 w-full border border-slate-300 px-3 pr-10 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
                                            required
                                            autocomplete="current-password"
                                            @keydown.enter.prevent="login"
                                        />
                                        <button
                                            type="button"
                                            class="absolute top-1/2 right-3 -translate-y-1/2 text-slate-400 transition hover:text-slate-700"
                                            :aria-label="
                                                showPassword
                                                    ? 'Hide password'
                                                    : 'Show password'
                                            "
                                            @click="
                                                showPassword = !showPassword
                                            "
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
                                class="mt-8 space-y-4"
                                @submit.prevent="login"
                            >
                                <label
                                    class="block text-sm font-bold text-slate-700"
                                >
                                    Email
                                    <input
                                        v-model="form.email"
                                        type="email"
                                        class="mt-2 h-12 w-full border border-slate-300 px-3 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
                                        required
                                        autocomplete="email"
                                    />
                                </label>
                                <label
                                    class="block text-sm font-bold text-slate-700"
                                >
                                    Password
                                    <span class="relative mt-2 block">
                                        <input
                                            v-model="form.password"
                                            :type="
                                                showPassword
                                                    ? 'text'
                                                    : 'password'
                                            "
                                            class="h-12 w-full border border-slate-300 px-3 pr-10 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
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
                                            @click="
                                                showPassword = !showPassword
                                            "
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

                            <p
                                class="mt-5 text-sm font-semibold text-slate-500"
                            >
                                {{ helperText }}
                            </p>
                            <p
                                v-if="form.errors.email"
                                class="mt-3 bg-red-50 px-3 py-2 text-sm font-semibold text-red-600"
                            >
                                {{ form.errors.email }}
                            </p>
                            <p
                                v-if="form.errors.password"
                                class="mt-3 bg-red-50 px-3 py-2 text-sm font-semibold text-red-600"
                            >
                                {{ form.errors.password }}
                            </p>
                            <Link
                                :href="route('password.request')"
                                class="mt-3 inline-block text-sm font-semibold text-blue-600 hover:text-blue-700"
                            >
                                Forgot password?
                            </Link>

                            <div
                                class="mt-8 flex items-center justify-between gap-4"
                            >
                                <button
                                    v-if="profiles.length > 0"
                                    type="button"
                                    class="text-left text-sm font-semibold text-slate-500 hover:text-sky-600"
                                    @click="
                                        useDifferentAccount &&
                                        profiles.length > 0
                                            ? selectProfile(selectedIndex)
                                            : showDifferentAccount()
                                    "
                                >
                                    {{
                                        useDifferentAccount &&
                                        profiles.length > 0
                                            ? 'Back to saved profiles'
                                            : 'Login to a different account'
                                    }}
                                </button>
                                <button
                                    type="button"
                                    :disabled="form.processing"
                                    class="inline-flex h-12 items-center gap-3 bg-blue-600 px-8 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 disabled:opacity-60"
                                    @click="login"
                                >
                                    {{
                                        form.processing
                                            ? 'Logging in...'
                                            : 'Login'
                                    }}
                                    <ArrowRight class="h-4 w-4" />
                                </button>
                            </div>

                            <div class="mt-6 border-t border-slate-300 pt-5">
                                <p class="text-xs font-semibold text-slate-400">
                                    Student and Parent accounts only.
                                </p>
                            </div>
                        </section>
                    </Transition>
                </div>
            </section>

            <section class="bg-[#FAFAFA] py-20">
                <div
                    class="mx-auto grid max-w-[1400px] gap-8 px-7 md:px-12 lg:grid-cols-3"
                >
                    <article
                        v-for="card in featureCards"
                        :key="card.title"
                        class="overflow-hidden rounded-lg bg-white shadow-lg"
                    >
                        <img
                            :src="card.image"
                            :alt="card.title"
                            class="h-56 w-full object-cover"
                        />
                        <div class="p-7">
                            <h2 class="text-xl font-bold text-default">
                                {{ card.title }}
                            </h2>
                            <p class="mt-4 text-sm leading-7 text-custom-gray">
                                {{ card.sub }}
                            </p>
                        </div>
                    </article>
                </div>
            </section>

            <section id="about_us" class="bg-white py-20">
                <div
                    class="mx-auto grid max-w-[1400px] items-center gap-12 px-7 md:px-12 lg:grid-cols-2"
                >
                    <img
                        :src="container4"
                        alt="RFID automation"
                        class="w-full rounded-lg object-cover shadow-lg"
                    />
                    <div>
                        <p
                            class="text-sm font-bold tracking-[0.25em] text-brand uppercase"
                        >
                            About the System
                        </p>
                        <h2
                            class="mt-4 text-3xl leading-tight font-bold text-default md:text-4xl"
                        >
                            Smart Automation Through RFID Technology
                        </h2>
                        <p class="mt-5 text-base leading-8 text-custom-gray">
                            The RFID-Based Attendance Monitoring, Borrowing, and
                            Inventory System is designed to provide an efficient
                            and automated solution for tracking attendance,
                            managing borrowed items, and maintaining inventory
                            records.
                        </p>
                    </div>
                </div>

                <div
                    class="mx-auto mt-16 max-w-[1100px] px-7 text-center md:px-12"
                >
                    <h2 class="text-2xl font-bold text-default">
                        More information about us
                    </h2>
                    <p class="mt-5 text-base leading-8 text-custom-gray">
                        This web-based solution automates manual processes using
                        RFID technology. It improves efficiency, accuracy, and
                        security by providing real-time tracking of attendance
                        records, borrowed items, and inventory data.
                    </p>
                    <a
                        href="/about"
                        class="mt-7 inline-flex border-2 border-brand px-8 py-3 text-sm font-bold text-brand transition hover:bg-brand hover:text-white"
                    >
                        LEARN MORE
                    </a>
                </div>
            </section>

            <section class="bg-azure-gradient py-16 text-white">
                <div
                    class="mx-auto flex max-w-[1400px] flex-col gap-7 px-7 md:flex-row md:items-center md:justify-between md:px-12"
                >
                    <div>
                        <h2 class="text-3xl font-bold">
                            Ready to open your student portal?
                        </h2>
                        <p class="mt-3 max-w-2xl text-white/85">
                            Sign in to view attendance, online classes, excuse
                            letters, messages, and notifications.
                        </p>
                    </div>
                    <a
                        href="#student_login"
                        class="inline-flex w-full max-w-[260px] justify-center border-2 border-white px-6 py-3 text-sm font-bold transition hover:bg-white hover:text-brand"
                    >
                        ACCESS PORTAL
                    </a>
                </div>
            </section>

            <section id="portal_showcase" class="bg-white py-20">
                <div class="mx-auto max-w-[1400px] px-7 md:px-12">
                    <div class="max-w-3xl">
                        <p
                            class="text-sm font-bold tracking-[0.25em] text-brand uppercase"
                        >
                            Portal Showcase
                        </p>
                        <h2 class="mt-4 text-3xl font-bold text-default">
                            Built for student and parent access
                        </h2>
                    </div>

                    <div class="mt-10 grid gap-7 md:grid-cols-3">
                        <article
                            v-for="item in showcaseItems"
                            :key="item.title"
                            class="border border-slate-200 bg-white p-6 shadow-sm"
                        >
                            <div class="text-4xl font-bold text-brand">
                                {{ item.number }}
                            </div>
                            <h3 class="mt-5 text-xl font-bold text-default">
                                {{ item.title }}
                            </h3>
                            <p class="mt-3 text-sm leading-7 text-custom-gray">
                                {{ item.text }}
                            </p>
                        </article>
                    </div>
                </div>
            </section>

            <section class="bg-[#FAFAFA] py-20">
                <div class="mx-auto max-w-[1400px] px-7 md:px-12">
                    <div
                        class="flex flex-col justify-between gap-5 md:flex-row md:items-end"
                    >
                        <div>
                            <p
                                class="text-sm font-bold tracking-[0.25em] text-brand uppercase"
                            >
                                Gallery
                            </p>
                            <h2 class="mt-4 text-3xl font-bold text-default">
                                RFID modules and assets
                            </h2>
                        </div>
                    </div>

                    <div class="mt-10 grid gap-5 md:grid-cols-3">
                        <img
                            v-for="image in galleryImages"
                            :key="image.alt"
                            :src="image.src"
                            :alt="image.alt"
                            class="h-72 w-full rounded-lg object-cover shadow-md"
                        />
                    </div>
                </div>
            </section>
        </main>

        <footer class="bg-azure-gradient p-7 text-white md:py-16">
            <div
                class="mx-auto flex max-w-[1400px] flex-col gap-10 md:flex-row md:items-start md:justify-center md:gap-20"
            >
                <div>
                    <h2 class="text-2xl font-bold">GET IN TOUCH</h2>
                    <ul class="mt-4 list-inside list-disc leading-8">
                        <li>Phone: +63-912-345-6789</li>
                        <li>Fax: +1 496 457 654</li>
                        <li>Email: our-mail@example.com</li>
                        <li>Address: samplestreet123</li>
                    </ul>
                </div>

                <div class="w-full max-w-[520px]">
                    <h2 class="text-2xl font-bold">NEWS LETTER</h2>
                    <p class="mt-4">Sign up your newsletter</p>
                    <form class="mt-4 flex flex-col gap-3 sm:flex-row">
                        <input
                            type="email"
                            class="h-[50px] w-full border-2 border-white bg-transparent p-3 text-white placeholder:text-white/70"
                            placeholder="Email Address"
                        />
                        <button
                            type="button"
                            class="h-[50px] bg-[#F17A20] px-10 font-bold text-white"
                        >
                            GO
                        </button>
                    </form>
                </div>
            </div>
        </footer>

        <footer
            class="flex min-h-[100px] flex-col items-center justify-center bg-[#002F5B] p-5 text-white"
        >
            <p>Blog | Contact Us</p>
            <p class="mt-3 text-center text-custom-gray">
                Copyright 2026 RFID - Attendance Monitoring/Borrowing and
                Inventory System
            </p>
        </footer>
    </div>
</template>
