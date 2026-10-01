<script setup>
import logo from '@/assets/images/logo-only.jpg';
import schoolPhoto from '@/assets/images/pasayCitysouth.png';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { KeyRound, LogOut, Mail, ShieldCheck } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

defineOptions({ layout: null });

const props = defineProps({
    email: { type: String, required: true },
    expiresAt: { type: Number, default: null },
    resendAfterSeconds: { type: Number, default: 0 },
    status: { type: String, default: '' },
});

const page = usePage();
const now = ref(Math.floor(Date.now() / 1000));
const resendAvailableAt = ref(now.value + props.resendAfterSeconds);
let timer;

const verifyForm = useForm({ otp: '' });
const resendForm = useForm({});
const flashSuccess = computed(() => page.props.flash?.success || props.status);
const expiresIn = computed(() =>
    Math.max(0, (props.expiresAt || 0) - now.value),
);
const resendIn = computed(() =>
    Math.max(0, resendAvailableAt.value - now.value),
);
const expiryLabel = computed(() => {
    const minutes = Math.floor(expiresIn.value / 60);
    const seconds = expiresIn.value % 60;
    return `${minutes}:${String(seconds).padStart(2, '0')}`;
});

const submit = () => {
    verifyForm.clearErrors();
    verifyForm.otp = verifyForm.otp.replace(/\D/g, '').slice(0, 6);
    verifyForm.post(route('admin.login-verification.verify'), {
        preserveScroll: true,
        onError: () => verifyForm.reset('otp'),
    });
};

const resend = () => {
    resendForm.post(route('admin.login-verification.resend'), {
        preserveScroll: true,
        onSuccess: () => {
            resendAvailableAt.value =
                Math.floor(Date.now() / 1000) +
                Math.max(1, props.resendAfterSeconds || 60);
            verifyForm.reset();
        },
    });
};

onMounted(() => {
    timer = window.setInterval(
        () => (now.value = Math.floor(Date.now() / 1000)),
        1000,
    );
});
onBeforeUnmount(() => window.clearInterval(timer));
</script>

<template>
    <main class="grid min-h-screen bg-white lg:grid-cols-[42%_58%]">
        <section class="relative hidden overflow-hidden bg-slate-950 lg:block">
            <img
                :src="schoolPhoto"
                alt="School building"
                class="absolute inset-0 h-full w-full object-cover opacity-65"
            />
            <div class="absolute inset-0 bg-[#071052]/55" />
            <div
                class="absolute top-10 left-12 flex items-center gap-3 text-white"
            >
                <img
                    :src="logo"
                    alt="School logo"
                    class="h-12 w-12 rounded-full bg-white p-1"
                />
                <span class="text-3xl font-black">PCSHS</span>
            </div>
            <div class="absolute right-12 bottom-14 left-12 text-white">
                <div
                    class="inline-flex items-center gap-2 rounded-full bg-white/15 px-4 py-2 text-xs font-bold backdrop-blur"
                >
                    <ShieldCheck class="h-4 w-4" /> Admin security checkpoint
                </div>
                <h1 class="mt-5 text-4xl font-black">
                    Verify every Admin login.
                </h1>
                <p class="mt-4 max-w-md leading-7 text-white/85">
                    Password access is held until the one-time code sent to the
                    Admin account is confirmed.
                </p>
            </div>
        </section>

        <section class="flex items-center justify-center px-6 py-12">
            <div class="w-full max-w-md">
                <div
                    class="flex h-14 w-14 items-center justify-center rounded-full bg-indigo-100 text-indigo-700"
                >
                    <KeyRound class="h-7 w-7" />
                </div>
                <p
                    class="mt-6 text-xs font-black tracking-[0.2em] text-indigo-700 uppercase"
                >
                    Admin verification
                </p>
                <h2 class="mt-2 text-3xl font-black text-slate-950">
                    Enter your login code
                </h2>
                <p class="mt-3 text-sm leading-6 text-slate-600">
                    We sent a six-digit code to <strong>{{ email }}</strong
                    >. The code is bound to this login and expires in 10
                    minutes.
                </p>

                <div
                    v-if="flashSuccess"
                    class="mt-5 rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-700"
                >
                    {{ flashSuccess }}
                </div>

                <form class="mt-6" @submit.prevent="submit">
                    <label
                        for="admin-login-otp"
                        class="text-sm font-bold text-slate-700"
                        >Verification code</label
                    >
                    <div class="relative mt-2">
                        <Mail
                            class="absolute top-1/2 left-4 h-5 w-5 -translate-y-1/2 text-slate-400"
                        />
                        <input
                            id="admin-login-otp"
                            v-model="verifyForm.otp"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            pattern="[0-9]{6}"
                            maxlength="6"
                            required
                            autofocus
                            class="w-full rounded-md border border-slate-300 py-3 pr-4 pl-12 text-center text-2xl font-black tracking-[0.35em] outline-none focus:border-indigo-600 focus:ring-2 focus:ring-indigo-100"
                            placeholder="000000"
                        />
                    </div>
                    <p
                        v-if="verifyForm.errors.otp"
                        class="mt-2 text-sm font-semibold text-rose-700"
                        role="alert"
                    >
                        {{ verifyForm.errors.otp }}
                    </p>
                    <p
                        v-if="resendForm.errors.otp"
                        class="mt-2 text-sm font-semibold text-rose-700"
                        role="alert"
                    >
                        {{ resendForm.errors.otp }}
                    </p>
                    <p class="mt-3 text-xs text-slate-500">
                        Code expires in {{ expiryLabel }}
                    </p>
                    <button
                        type="submit"
                        :disabled="
                            verifyForm.processing || verifyForm.otp.length !== 6
                        "
                        class="mt-5 w-full rounded-md bg-indigo-700 px-5 py-3 font-black text-white hover:bg-indigo-800 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {{
                            verifyForm.processing
                                ? 'Verifying...'
                                : 'Verify and continue'
                        }}
                    </button>
                </form>

                <div
                    class="mt-5 flex items-center justify-between gap-4 text-sm"
                >
                    <button
                        type="button"
                        :disabled="resendForm.processing || resendIn > 0"
                        class="font-bold text-indigo-700 disabled:text-slate-400"
                        @click="resend"
                    >
                        {{
                            resendIn > 0
                                ? `Resend in ${resendIn}s`
                                : 'Resend code'
                        }}
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 font-bold text-slate-600"
                        @click="router.post(route('logout'))"
                    >
                        <LogOut class="h-4 w-4" /> Sign out
                    </button>
                </div>
            </div>
        </section>
    </main>
</template>
