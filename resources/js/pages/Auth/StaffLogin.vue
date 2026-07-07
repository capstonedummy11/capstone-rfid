<script setup>
import logo from '@/assets/images/logo.png';
import philsca from '@/assets/images/philsca.png';
import featureImage from '@/assets/images/Container.png';
import featureImage2 from '@/assets/images/Container 2.png';
import featureImage3 from '@/assets/images/Container 3.png';
import aboutImage from '@/assets/images/Container 4.png';
import { Link, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff } from 'lucide-vue-next';
import { ref } from 'vue';

const showPassword = ref(false);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/secure-login');
};

const features = [
    {
        title: 'Attendance Tracking',
        image: featureImage,
        text: 'RFID attendance tools help staff monitor room-based student attendance with accurate timestamps.',
    },
    {
        title: 'RFID',
        image: featureImage2,
        text: 'Tagged IDs and assets support faster attendance, borrowing, and inventory workflows.',
    },
    {
        title: 'Facial Recognition',
        image: featureImage3,
        text: 'Optional face verification supports secure attendance and instructor verification flows.',
    },
];
</script>

<template>
    <div class="min-h-screen bg-white font-raleway text-slate-900">
        <header
            class="mx-auto flex h-20 max-w-7xl items-center justify-between px-6"
        >
            <div class="flex items-center gap-3">
                <img
                    :src="logo"
                    alt="Pasay City South High School logo"
                    class="h-12 w-12 object-contain"
                />
                <div class="text-xs font-black leading-tight text-[#07136b]">
                    PASAY CITY SOUTH<br />
                    HIGH SCHOOL
                </div>
            </div>
            <nav class="flex items-center gap-2 text-[10px] font-bold">
                <a
                    href="#home"
                    class="border border-[#07136b] px-5 py-2 text-[#07136b]"
                >
                    HOME
                </a>
                <a
                    href="#about_us"
                    class="border border-[#07136b] px-5 py-2 text-[#07136b]"
                >
                    ABOUT US
                </a>
                <a href="#login" class="bg-[#07136b] px-5 py-2 text-white">
                    LOGIN
                </a>
            </nav>
        </header>

        <div class="h-10 bg-[#07136b]" />

        <main id="home">
            <section
                class="mx-auto grid max-w-7xl overflow-hidden lg:grid-cols-[70%_30%]"
            >
                <div class="relative min-h-[520px] overflow-hidden">
                    <img
                        :src="philsca"
                        alt="School campus"
                        class="absolute inset-0 h-full w-full object-cover"
                    />
                    <div class="absolute inset-0 bg-[#193153]/55" />
                    <div
                        class="relative flex min-h-[520px] max-w-3xl flex-col justify-center px-10 text-white md:px-24"
                    >
                        <p class="mb-4 text-xs font-semibold">Home /</p>
                        <h1 class="max-w-xl text-3xl font-semibold md:text-5xl">
                            RFID and Facial Recognition Attendance Monitoring
                            System
                        </h1>
                        <p class="mt-5 max-w-xl text-sm leading-6 text-white/85">
                            A smart automated system designed to improve
                            efficiency, accuracy, and real-time tracking using
                            RFID technology and facial recognition.
                        </p>
                        <a
                            href="#login"
                            class="mt-8 inline-flex w-fit border border-white bg-[#07136b] px-7 py-3 text-xs font-black text-white"
                        >
                            GET STARTED TODAY
                        </a>
                    </div>
                </div>

                <aside
                    id="login"
                    class="flex min-h-[520px] items-center bg-white px-8 py-10"
                >
                    <form class="w-full" @submit.prevent="submit">
                        <h2 class="text-xl font-black text-slate-950">
                            Login to your account
                        </h2>
                        <p class="mt-2 text-xs font-semibold text-slate-500">
                            Secured access for admin, instructor, registrar, and
                            clinic accounts.
                        </p>

                        <label
                            class="mt-7 block text-sm font-bold text-slate-800"
                        >
                            Email
                            <input
                                v-model="form.email"
                                type="email"
                                required
                                autocomplete="email"
                                class="mt-2 h-11 w-full rounded-md border border-[#07136b] px-3 text-sm outline-none focus:ring-2 focus:ring-[#07136b]/15"
                            />
                        </label>
                        <p
                            v-if="form.errors.email"
                            class="mt-2 text-xs font-semibold text-red-600"
                        >
                            {{ form.errors.email }}
                        </p>

                        <label
                            class="mt-5 block text-sm font-bold text-slate-800"
                        >
                            Password
                            <span class="relative mt-2 block">
                                <input
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    required
                                    autocomplete="current-password"
                                    class="h-11 w-full rounded-md border border-slate-200 px-3 pr-10 text-sm outline-none focus:border-[#07136b] focus:ring-2 focus:ring-[#07136b]/15"
                                />
                                <button
                                    type="button"
                                    class="absolute top-1/2 right-3 -translate-y-1/2 text-slate-400"
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
                        <p
                            v-if="form.errors.password"
                            class="mt-2 text-xs font-semibold text-red-600"
                        >
                            {{ form.errors.password }}
                        </p>

                        <div class="mt-2 flex justify-end">
                            <Link
                                href="/forgot-password"
                                class="text-xs font-bold text-[#07136b]"
                            >
                                Forgot?
                            </Link>
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="mt-5 h-11 w-full rounded-md bg-[#07136b] text-sm font-black text-white disabled:opacity-60"
                        >
                            {{ form.processing ? 'Logging in...' : 'Login' }}
                        </button>
                        <Link
                            href="/"
                            class="mt-4 flex h-11 w-full items-center justify-center rounded-md bg-[#8381bd] text-sm font-black text-white"
                        >
                            Login as student or parent
                        </Link>
                    </form>
                </aside>
            </section>

            <section class="mx-auto grid max-w-5xl gap-8 px-6 py-12 md:grid-cols-3">
                <article v-for="feature in features" :key="feature.title">
                    <img
                        :src="feature.image"
                        :alt="feature.title"
                        class="h-24 w-full object-cover"
                    />
                    <h3
                        class="mt-4 text-xs font-black tracking-wide text-[#07136b] uppercase"
                    >
                        {{ feature.title }}
                    </h3>
                    <p class="mt-2 text-xs leading-5 text-slate-500">
                        {{ feature.text }}
                    </p>
                </article>
            </section>

            <section
                id="about_us"
                class="mx-auto grid max-w-5xl gap-10 px-6 py-12 md:grid-cols-2 md:items-center"
            >
                <div>
                    <p class="text-xs font-semibold italic text-slate-500">
                        We are
                    </p>
                    <h2
                        class="mt-2 max-w-sm text-2xl font-black tracking-wide text-[#07136b] uppercase"
                    >
                        Smart automation through RFID and facial recognition
                        technology
                    </h2>
                    <p class="mt-5 text-sm leading-7 text-slate-500">
                        The RFID-based attendance monitoring, borrowing, and
                        inventory system is designed to provide an efficient and
                        automated solution for tracking attendance, managing
                        borrowed items, and maintaining inventory records.
                    </p>
                </div>
                <img
                    :src="aboutImage"
                    alt="RFID scanner"
                    class="h-72 w-full object-cover"
                />
            </section>

            <section class="bg-[#07136b] px-6 py-16 text-center text-white">
                <h2 class="text-2xl font-black tracking-[0.18em] uppercase">
                    Transforming manual systems into smart RFID solutions
                </h2>
                <p class="mx-auto mt-4 max-w-2xl text-sm text-white/75">
                    This thesis project demonstrates the development of an
                    integrated RFID-based attendance, borrowing, and inventory
                    management system.
                </p>
                <a
                    href="#login"
                    class="mt-8 inline-flex bg-white px-8 py-3 text-xs font-black text-[#07136b]"
                >
                    Login now
                </a>
            </section>
        </main>
    </div>
</template>
