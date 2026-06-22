<template>
    <div class="h-full w-full bg-white">
        <div
            v-if="isStudentParentLogin"
            class="grid min-h-[520px] grid-cols-1 overflow-hidden rounded-lg bg-white shadow-sm md:grid-cols-[0.95fr_1.05fr]"
        >
            <section class="flex flex-col justify-between bg-brand p-8 text-white">
                <div>
                    <img :src="logo" alt="PhilSCA" class="h-auto w-36 brightness-0 invert" />
                    <div class="mt-12">
                        <p class="text-sm font-semibold tracking-wide uppercase text-white/70">
                            RFID Attendance Portal
                        </p>
                        <h1 class="mt-3 text-3xl font-bold leading-tight">
                            Student and parent access
                        </h1>
                        <p class="mt-3 text-sm leading-6 text-white/80">
                            View attendance summaries, recent records, and student status in one focused dashboard.
                        </p>
                    </div>
                </div>
                <div class="mt-10 grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-md bg-white/10 px-2 py-3">
                        <CheckCircle2 class="mx-auto h-5 w-5" />
                        <p class="mt-2 text-xs font-semibold">Present</p>
                    </div>
                    <div class="rounded-md bg-white/10 px-2 py-3">
                        <Clock3 class="mx-auto h-5 w-5" />
                        <p class="mt-2 text-xs font-semibold">Late</p>
                    </div>
                    <div class="rounded-md bg-white/10 px-2 py-3">
                        <AlertCircle class="mx-auto h-5 w-5" />
                        <p class="mt-2 text-xs font-semibold">Absent</p>
                    </div>
                </div>
            </section>

            <form class="flex items-center p-6 md:p-10" @submit.prevent="submit">
                <div class="w-full">
                    <header>
                        <p class="text-xs font-bold tracking-wide text-brand uppercase">
                            Main Login
                        </p>
                        <h2 class="mt-2 text-2xl font-bold text-slate-900">
                            {{ heading }}
                        </h2>
                        <p class="mt-2 text-sm text-slate-500">
                            {{ subheading }}
                        </p>
                    </header>

                    <div class="mt-7 space-y-4 text-slate-900">
                        <input type="hidden" name="login_type" :value="loginType" />
                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase text-slate-500">Email</span>
                            <input
                                v-model="form.email"
                                type="email"
                                name="email"
                                class="h-12 w-full rounded-md border border-slate-300 px-3 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                                :class="{ 'border-red-500': form.errors.email }"
                                required
                            />
                            <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">
                                {{ form.errors.email }}
                            </p>
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase text-slate-500">Password</span>
                            <div class="relative">
                                <input
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    name="password"
                                    class="h-12 w-full rounded-md border border-slate-300 px-3 pr-10 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                                    :class="{ 'border-red-500': form.errors.password }"
                                    required
                                />
                                <button
                                    type="button"
                                    class="absolute top-1/2 right-3 -translate-y-1/2 text-slate-500"
                                    @click="showPassword = !showPassword"
                                >
                                    <EyeOn v-if="showPassword" />
                                    <EyeOff v-else />
                                </button>
                            </div>
                            <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">
                                {{ form.errors.password }}
                            </p>
                        </label>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="h-12 w-full rounded-md bg-brand text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{ form.processing ? 'Signing in...' : buttonText }}
                        </button>

                        <div class="flex items-center justify-between gap-3 text-sm">
                            <Link class="font-semibold text-brand">
                                Forgot Password?
                            </Link>
                            <Link :href="route('staff.login')" class="font-semibold text-slate-500 hover:text-brand">
                                Staff secure login
                            </Link>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <form v-else class="flex h-full min-h-[420px] items-center p-8" @submit.prevent="submit">
            <div class="w-full">
                <header class="py-5 text-center">
                    <h1 class="text-[30px] font-bold text-[#101828]">
                        {{ heading }}
                    </h1>
                    <p class="mt-2 text-sm text-slate-500">
                        {{ subheading }}
                    </p>
                </header>
                <div class="text-[#101828]">
                    <input type="hidden" name="login_type" :value="loginType" />
                    <div class="my-5 flex flex-col gap-5">
                        <label for="email">Email: </label>
                        <input
                            v-model="form.email"
                            type="email"
                            name="email"
                            class="h-[50px] w-full rounded-[10px] border-2 p-2"
                            :class="{ 'border-red-500': form.errors.email }"
                        />
                        <p v-if="form.errors.email" class="text-sm text-red-600">
                            {{ form.errors.email }}
                        </p>
                    </div>
                    <div class="my-5 flex flex-col">
                        <label for="password">Password:</label>
                        <div class="relative gap-5">
                            <input
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                name="password"
                                class="h-[50px] w-full rounded-[10px] border-2 p-2 pr-10"
                                :class="{ 'border-red-500': form.errors.password }"
                            />
                            <button
                                type="button"
                                class="absolute top-1/2 right-3 -translate-y-1/2"
                                @click="showPassword = !showPassword"
                            >
                                <EyeOn v-if="showPassword" />
                                <EyeOff v-else />
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="mt-2 text-sm text-red-600">
                            {{ form.errors.password }}
                        </p>
                        <Link class="m-0 p-0 text-brand">
                            Forgot Password?
                        </Link>
                    </div>

                    <div class="space-y-5">
                        <LoginButton :disabled="form.processing" :text="buttonText" />
                        <Link
                            :href="route('login')"
                            class="block text-center text-sm font-semibold text-brand"
                        >
                            Student / parent login
                        </Link>
                    </div>
                </div>
            </div>
        </form>
    </div>
</template>
<script setup>
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { AlertCircle, CheckCircle2, Clock3 } from 'lucide-vue-next';
import logo from '@/assets/images/logo.png';
import EyeOn from '../Icon/EyeOn.vue';
import EyeOff from '../Icon/EyeOff.vue';
import LoginButton from '../Buttons/LoginButton.vue';

const props = defineProps({
    loginType: { type: String, default: 'student_parent' },
});

const showPassword = ref(false);
const isStudentParentLogin = computed(() => props.loginType !== 'staff');
const heading = computed(() =>
    isStudentParentLogin.value ? 'Student / Parent Login' : 'Secure Staff Login',
);
const subheading = computed(() =>
    isStudentParentLogin.value
        ? 'Use your student or parent account to continue.'
        : 'For admin, instructor, clinic, and registrar accounts.',
);
const buttonText = computed(() =>
    isStudentParentLogin.value ? 'Login' : 'Continue Securely',
);

const form = useForm({
    email: '',
    password: '',
    remember: false,
    login_type: props.loginType,
});

const submit = () => {
    form.post('/login');
};
</script>
