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

const availableSetupQuestions = (index) => {
    const currentQuestion = setupForm.questions[index]?.question;
    const selectedQuestions = new Set(
        setupForm.questions
            .map((question, questionIndex) => (questionIndex === index ? '' : question.question))
            .filter(Boolean),
    );

    return props.availableSecurityQuestions.filter((question) => question === currentQuestion || !selectedQuestions.has(question));
};

const setupQuestionError = (index, field) => setupForm.errors[`questions.${index}.${field}`];

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
    setupForm.clearErrors();

    const selectedQuestions = setupForm.questions.map((question) => question.question.trim());
    const answers = setupForm.questions.map((question) => question.answer.trim());

    if (selectedQuestions.some((question) => !question)) {
        setupForm.setError('questions', 'Please choose all three security questions.');
        return;
    }

    if (new Set(selectedQuestions).size !== selectedQuestions.length) {
        setupForm.setError('questions', 'Please choose three different security questions.');
        return;
    }

    if (answers.some((answer) => !answer)) {
        setupForm.setError('questions', 'Please answer all three security questions.');
        return;
    }

    const firstQuestion = selectedQuestions[0];

    setupForm
        .transform((data) => ({
            questions: data.questions.map((question) => ({
                question: question.question.trim(),
                answer: question.answer.trim(),
            })),
        }))
        .post(route('instructor.verify.security.setup'), {
        preserveScroll: true,
        onSuccess: () => {
            securityForm.security_question = firstQuestion;
            securityForm.security_answer = '';
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
                                    <option v-for="question in availableSetupQuestions(index)" :key="question" :value="question">
                                        {{ question }}
                                    </option>
                                </select>
                            </label>
                            <p v-if="setupQuestionError(index, 'question')" class="mt-2 text-sm text-red-600">
                                {{ setupQuestionError(index, 'question') }}
                            </p>
                            <label class="mt-3 block">
                                <span class="mb-1 block text-xs font-semibold uppercase text-slate-500">Answer</span>
                                <input v-model="setupForm.questions[index].answer" type="text" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required />
                            </label>
                            <p v-if="setupQuestionError(index, 'answer')" class="mt-2 text-sm text-red-600">
                                {{ setupQuestionError(index, 'answer') }}
                            </p>
                        </div>
                        <p v-if="setupForm.errors.questions" class="text-sm text-red-600">{{ setupForm.errors.questions }}</p>
                        <button type="submit" :disabled="setupForm.processing" class="rounded-md bg-brand px-4 py-2 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-60">
                            {{ setupForm.processing ? 'Saving...' : 'Save Security Questions' }}
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
                        <input v-model="securityForm.security_answer" type="text" class="block w-full max-w-md rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Answer" />
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
