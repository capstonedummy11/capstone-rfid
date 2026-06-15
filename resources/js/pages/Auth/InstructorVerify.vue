<script setup>
import CameraCapture from '@/components/CameraCapture.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineOptions({ layout: null });

const props = defineProps({
    hasFace: { type: Boolean, default: false },
    hasSecurityQuestion: { type: Boolean, default: false },
    securityQuestion: { type: String, default: '' },
    securityQuestions: { type: Array, default: () => [] },
    availableSecurityQuestions: { type: Array, default: () => [] },
    email: { type: String, default: '' },
    status: { type: String, default: '' },
});

const page = usePage();
const cameraRef = ref(null);
const mode = ref(props.hasSecurityQuestion ? 'face' : 'setup');
const flashSuccess = computed(() => page.props.flash?.success || props.status);

const faceForm = useForm({ image: '' });
const otpSendForm = useForm({});
const otpForm = useForm({ otp: '' });
const setupForm = useForm({
    questions: [
        { question: '', answer: '' },
        { question: '', answer: '' },
        { question: '', answer: '' },
    ],
});
const securityForm = useForm({
    security_question: props.securityQuestions[0]?.question || props.securityQuestion || '',
    security_answer: '',
});

const verifyFace = () => {
    const image = cameraRef.value?.captureFrame();
    if (!image) return;
    faceForm.image = image;
    faceForm.post(route('instructor.verify.face'), { preserveScroll: true });
};

const sendOtp = () => {
    otpSendForm.post(route('instructor.verify.otp.send'), { preserveScroll: true });
};

const submitOtp = () => {
    otpForm.post(route('instructor.verify.otp'), { preserveScroll: true });
};

const setupSecurity = () => {
    setupForm.post(route('instructor.verify.security.setup'), {
        preserveScroll: true,
        onSuccess: () => {
            mode.value = props.hasFace ? 'face' : 'security';
            setupForm.reset();
        },
    });
};

const verifySecurity = () => {
    securityForm.post(route('instructor.verify.security'), { preserveScroll: true });
};
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-slate-100 p-4">
        <section class="w-full max-w-4xl overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 p-5">
                <h1 class="text-2xl font-bold text-slate-900">Instructor Verification</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Verify your identity before opening instructor tools.
                </p>
                <p v-if="flashSuccess" class="mt-3 rounded-md bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700">
                    {{ flashSuccess }}
                </p>
            </div>

            <div class="grid grid-cols-1 gap-0 md:grid-cols-[260px_minmax(0,1fr)]">
                <aside class="border-b border-slate-100 bg-slate-50 p-4 md:border-r md:border-b-0">
                    <div class="flex flex-col gap-2">
                        <button
                            type="button"
                            class="rounded-md px-3 py-2 text-left text-sm font-semibold"
                            :class="mode === 'face' ? 'bg-brand text-white' : 'bg-white text-slate-700'"
                            :disabled="!hasFace || !hasSecurityQuestion"
                            @click="mode = 'face'"
                        >
                            Facial Recognition
                        </button>
                        <button
                            type="button"
                            class="rounded-md px-3 py-2 text-left text-sm font-semibold"
                            :class="mode === 'otp' ? 'bg-brand text-white' : 'bg-white text-slate-700'"
                            :disabled="!hasSecurityQuestion"
                            @click="mode = 'otp'"
                        >
                            OTP Email
                        </button>
                        <button
                            type="button"
                            class="rounded-md px-3 py-2 text-left text-sm font-semibold"
                            :class="mode === 'security' ? 'bg-brand text-white' : 'bg-white text-slate-700'"
                            :disabled="!hasSecurityQuestion"
                            @click="mode = 'security'"
                        >
                            Security Question
                        </button>
                        <button
                            v-if="!hasSecurityQuestion"
                            type="button"
                            class="rounded-md bg-amber-50 px-3 py-2 text-left text-sm font-semibold text-amber-700"
                            @click="mode = 'setup'"
                        >
                            First Login Setup
                        </button>
                    </div>
                </aside>

                <div class="p-5">
                    <form v-if="mode === 'setup'" class="space-y-4" @submit.prevent="setupSecurity">
                        <h2 class="text-lg font-bold text-slate-900">Set Security Question</h2>
                        <p class="text-sm text-slate-500">
                            Choose three preset questions used by common account recovery systems.
                        </p>
                        <div v-for="(_, index) in setupForm.questions" :key="index" class="rounded-md border border-slate-200 p-3">
                            <label class="block">
                                <span class="mb-1 block text-xs font-semibold uppercase text-slate-500">Question {{ index + 1 }}</span>
                                <select v-model="setupForm.questions[index].question" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                                    <option value="" disabled>Select a security question</option>
                                    <option v-for="question in availableSecurityQuestions" :key="question" :value="question">
                                        {{ question }}
                                    </option>
                                </select>
                            </label>
                            <label class="mt-3 block">
                                <span class="mb-1 block text-xs font-semibold uppercase text-slate-500">Answer</span>
                                <input v-model="setupForm.questions[index].answer" type="password" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required />
                            </label>
                        </div>
                        <p v-if="setupForm.errors.questions" class="text-sm text-red-600">{{ setupForm.errors.questions }}</p>
                        <button type="submit" class="rounded-md bg-brand px-4 py-2 text-sm font-bold text-white">
                            Save Security Questions
                        </button>
                    </form>

                    <div v-else-if="mode === 'face'" class="space-y-4">
                        <h2 class="text-lg font-bold text-slate-900">Facial Recognition</h2>
                        <div v-if="hasFace" class="flex items-center gap-5">
                            <CameraCapture ref="cameraRef" />
                            <div>
                                <button type="button" class="rounded-md bg-brand px-4 py-2 text-sm font-bold text-white" @click="verifyFace">
                                    Verify Face
                                </button>
                                <button type="button" class="ml-2 rounded-md border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700" @click="mode = 'otp'">
                                    Camera not available
                                </button>
                                <p v-if="faceForm.errors.face" class="mt-2 text-sm text-red-600">{{ faceForm.errors.face }}</p>
                            </div>
                        </div>
                        <p v-else class="rounded-md bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-700">
                            No instructor face image is enrolled yet. Use OTP or security question.
                        </p>
                    </div>

                    <form v-else-if="mode === 'otp'" class="space-y-4" @submit.prevent="submitOtp">
                        <h2 class="text-lg font-bold text-slate-900">OTP Email</h2>
                        <p class="text-sm text-slate-500">Send a one-time code to {{ email }}.</p>
                        <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700" @click="sendOtp">
                            Send OTP
                        </button>
                        <input v-model="otpForm.otp" type="text" maxlength="6" class="block w-full max-w-xs rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="6-digit OTP" />
                        <p v-if="otpForm.errors.otp" class="text-sm text-red-600">{{ otpForm.errors.otp }}</p>
                        <button type="submit" class="rounded-md bg-brand px-4 py-2 text-sm font-bold text-white">
                            Verify OTP
                        </button>
                    </form>

                    <form v-else class="space-y-4" @submit.prevent="verifySecurity">
                        <h2 class="text-lg font-bold text-slate-900">Security Question</h2>
                        <select
                            v-if="securityQuestions.length > 1"
                            v-model="securityForm.security_question"
                            class="block w-full max-w-md rounded-md border border-slate-300 px-3 py-2 text-sm"
                            required
                        >
                            <option v-for="question in securityQuestions" :key="question.question" :value="question.question">
                                {{ question.question }}
                            </option>
                        </select>
                        <p v-else class="rounded-md bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700">
                            {{ securityForm.security_question }}
                        </p>
                        <input v-model="securityForm.security_answer" type="password" class="block w-full max-w-md rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Answer" />
                        <p v-if="securityForm.errors.security_question" class="text-sm text-red-600">{{ securityForm.errors.security_question }}</p>
                        <p v-if="securityForm.errors.security_answer" class="text-sm text-red-600">{{ securityForm.errors.security_answer }}</p>
                        <button type="submit" class="rounded-md bg-brand px-4 py-2 text-sm font-bold text-white">
                            Verify Answer
                        </button>
                    </form>
                </div>
            </div>
        </section>
    </div>
</template>
