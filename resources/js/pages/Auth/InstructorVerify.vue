<script setup>
import CameraCapture from '@/components/CameraCapture.vue';
import logo from '@/assets/images/logo.png';
import schoolPhoto from '@/assets/images/philsca.png';
import { router, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    HelpCircle,
    KeyRound,
    LogOut,
    Mail,
    ScanFace,
    ShieldCheck,
} from 'lucide-vue-next';
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
    security_question:
        props.securityQuestions[0]?.question || props.securityQuestion || '',
    security_answer: '',
});

const methodOptions = computed(() => [
    {
        mode: 'face',
        label: 'Face',
        description: 'Verify with enrolled face image',
        icon: ScanFace,
        disabled: !props.hasFace || !props.hasSecurityQuestion,
        visible: props.hasSecurityQuestion,
    },
    {
        mode: 'otp',
        label: 'OTP',
        description: 'Send a one-time email code',
        icon: Mail,
        disabled: !props.hasSecurityQuestion,
        visible: props.hasSecurityQuestion,
    },
    {
        mode: 'security',
        label: 'Security',
        description: 'Answer your saved question',
        icon: HelpCircle,
        disabled: !props.hasSecurityQuestion,
        visible: props.hasSecurityQuestion,
    },
    {
        mode: 'setup',
        label: 'First Login',
        description: 'Set your recovery questions',
        icon: KeyRound,
        disabled: false,
        visible: !props.hasSecurityQuestion,
    },
]);

const availableSetupQuestions = (index) => {
    const currentQuestion = setupForm.questions[index]?.question;
    const selectedQuestions = new Set(
        setupForm.questions
            .map((question, questionIndex) =>
                questionIndex === index ? '' : question.question,
            )
            .filter(Boolean),
    );

    return props.availableSecurityQuestions.filter(
        (question) =>
            question === currentQuestion || !selectedQuestions.has(question),
    );
};

const setupQuestionError = (index, field) =>
    setupForm.errors[`questions.${index}.${field}`];

const verifyFace = () => {
    const image = cameraRef.value?.captureFrame();
    if (!image) return;
    faceForm.image = image;
    faceForm.post(route('instructor.verify.face'), { preserveScroll: true });
};

const sendOtp = () => {
    otpSendForm.post(route('instructor.verify.otp.send'), {
        preserveScroll: true,
    });
};

const submitOtp = () => {
    otpForm.post(route('instructor.verify.otp'), { preserveScroll: true });
};

const setupSecurity = () => {
    setupForm.clearErrors();

    const selectedQuestions = setupForm.questions.map((question) =>
        question.question.trim(),
    );
    const answers = setupForm.questions.map((question) =>
        question.answer.trim(),
    );

    if (selectedQuestions.some((question) => !question)) {
        setupForm.setError(
            'questions',
            'Please choose all three security questions.',
        );
        return;
    }

    if (new Set(selectedQuestions).size !== selectedQuestions.length) {
        setupForm.setError(
            'questions',
            'Please choose three different security questions.',
        );
        return;
    }

    if (answers.some((answer) => !answer)) {
        setupForm.setError(
            'questions',
            'Please answer all three security questions.',
        );
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
    securityForm.post(route('instructor.verify.security'), {
        preserveScroll: true,
    });
};

const logout = () => {
    router.post(route('logout'));
};
</script>

<template>
    <div class="min-h-screen bg-white p-2">
        <main
            class="grid min-h-[calc(100vh-1rem)] overflow-hidden bg-white lg:grid-cols-[42%_58%]"
        >
            <section
                class="relative hidden overflow-hidden bg-slate-900 lg:block"
            >
                <img
                    :src="schoolPhoto"
                    alt="School building"
                    class="absolute inset-0 h-full w-full object-cover"
                />
                <div class="absolute inset-0 bg-[#082f49]/40" />

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
                        Instructor identity check
                    </div>
                    <h1 class="mt-5 max-w-lg text-4xl leading-tight font-black">
                        One more verification before opening instructor tools.
                    </h1>
                    <p class="mt-4 max-w-md text-sm leading-6 text-white/85">
                        Choose face verification, email OTP, or your saved
                        security question to continue securely.
                    </p>
                </div>

                <div
                    class="absolute right-0 bottom-8 left-0 flex justify-center gap-2"
                >
                    <span class="h-3 w-6 rounded-full bg-sky-500" />
                    <span class="h-3 w-3 rounded-full bg-white" />
                    <span class="h-3 w-3 rounded-full bg-white" />
                </div>
            </section>

            <section class="flex items-center justify-center px-6 py-10">
                <div class="w-full max-w-2xl">
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
                                Instructor verification
                            </p>
                        </div>
                    </div>

                    <header>
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p
                                    class="text-xs font-black tracking-[0.22em] text-blue-600 uppercase"
                                >
                                    Secure Verification
                                </p>
                                <h1
                                    class="mt-3 text-3xl font-black text-slate-900"
                                >
                                    Instructor Verification
                                </h1>
                                <p
                                    class="mt-2 text-sm font-semibold text-slate-500"
                                >
                                    Verify your identity before opening
                                    instructor tools.
                                </p>
                            </div>

                            <button
                                type="button"
                                class="inline-flex h-10 shrink-0 items-center gap-2 rounded-md border border-slate-300 px-3 text-xs font-bold text-slate-600 transition hover:border-red-200 hover:bg-red-50 hover:text-red-600"
                                @click="logout"
                            >
                                <LogOut class="h-4 w-4" />
                                Logout
                            </button>
                        </div>
                    </header>

                    <p
                        v-if="flashSuccess"
                        class="mt-5 rounded-md bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700"
                    >
                        {{ flashSuccess }}
                    </p>

                    <div class="mt-8 grid gap-3 sm:grid-cols-3">
                        <button
                            v-for="option in methodOptions.filter(
                                (item) => item.visible,
                            )"
                            :key="option.mode"
                            type="button"
                            class="rounded-md border p-3 text-left transition duration-200 disabled:cursor-not-allowed disabled:opacity-45"
                            :class="
                                mode === option.mode
                                    ? 'border-blue-600 bg-blue-50 text-blue-700 shadow-sm'
                                    : 'border-slate-200 bg-white text-slate-700 hover:border-sky-300 hover:bg-slate-50'
                            "
                            :disabled="option.disabled"
                            @click="mode = option.mode"
                        >
                            <component :is="option.icon" class="h-5 w-5" />
                            <span class="mt-3 block text-sm font-black">
                                {{ option.label }}
                            </span>
                            <span
                                class="mt-1 block text-xs leading-5 text-slate-500"
                            >
                                {{ option.description }}
                            </span>
                        </button>
                    </div>

                    <Transition
                        mode="out-in"
                        enter-active-class="transition duration-200 ease-out"
                        enter-from-class="translate-y-3 opacity-0"
                        enter-to-class="translate-y-0 opacity-100"
                        leave-active-class="transition duration-150 ease-in"
                        leave-from-class="translate-y-0 opacity-100"
                        leave-to-class="-translate-y-2 opacity-0"
                    >
                        <form
                            v-if="mode === 'setup'"
                            key="setup"
                            class="mt-8 space-y-4 rounded-md border border-slate-200 bg-white p-5 shadow-sm"
                            @submit.prevent="setupSecurity"
                        >
                            <div>
                                <h2 class="text-lg font-black text-slate-900">
                                    First Login Setup
                                </h2>
                                <p class="mt-1 text-sm text-slate-500">
                                    Choose three preset questions used by common
                                    account recovery systems.
                                </p>
                            </div>

                            <div
                                v-for="(_, index) in setupForm.questions"
                                :key="index"
                                class="rounded-md border border-slate-200 p-3"
                            >
                                <label class="block">
                                    <span
                                        class="mb-1 block text-xs font-semibold text-slate-500 uppercase"
                                    >
                                        Question {{ index + 1 }}
                                    </span>
                                    <select
                                        v-model="
                                            setupForm.questions[index].question
                                        "
                                        class="h-11 w-full rounded-md border border-slate-300 px-3 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
                                        required
                                    >
                                        <option value="" disabled>
                                            Select a security question
                                        </option>
                                        <option
                                            v-for="question in availableSetupQuestions(
                                                index,
                                            )"
                                            :key="question"
                                            :value="question"
                                        >
                                            {{ question }}
                                        </option>
                                    </select>
                                </label>
                                <p
                                    v-if="setupQuestionError(index, 'question')"
                                    class="mt-2 text-sm text-red-600"
                                >
                                    {{ setupQuestionError(index, 'question') }}
                                </p>
                                <label class="mt-3 block">
                                    <span
                                        class="mb-1 block text-xs font-semibold text-slate-500 uppercase"
                                    >
                                        Answer
                                    </span>
                                    <input
                                        v-model="
                                            setupForm.questions[index].answer
                                        "
                                        type="text"
                                        class="h-11 w-full rounded-md border border-slate-300 px-3 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
                                        required
                                    />
                                </label>
                                <p
                                    v-if="setupQuestionError(index, 'answer')"
                                    class="mt-2 text-sm text-red-600"
                                >
                                    {{ setupQuestionError(index, 'answer') }}
                                </p>
                            </div>

                            <p
                                v-if="setupForm.errors.questions"
                                class="text-sm text-red-600"
                            >
                                {{ setupForm.errors.questions }}
                            </p>
                            <button
                                type="submit"
                                :disabled="setupForm.processing"
                                class="inline-flex h-12 items-center gap-3 rounded-md bg-blue-600 px-6 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {{
                                    setupForm.processing
                                        ? 'Saving...'
                                        : 'Save Security Questions'
                                }}
                                <ArrowRight class="h-4 w-4" />
                            </button>
                        </form>

                        <div
                            v-else-if="mode === 'face'"
                            key="face"
                            class="mt-8 rounded-md border border-slate-200 bg-white p-5 shadow-sm"
                        >
                            <h2 class="text-lg font-black text-slate-900">
                                Facial Recognition
                            </h2>
                            <p class="mt-1 text-sm text-slate-500">
                                Center your face in the camera frame, then
                                verify to continue.
                            </p>

                            <div
                                v-if="hasFace"
                                class="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1fr)_220px]"
                            >
                                <CameraCapture ref="cameraRef" />
                                <div class="flex flex-col justify-center gap-3">
                                    <button
                                        type="button"
                                        :disabled="faceForm.processing"
                                        class="inline-flex h-12 items-center justify-center gap-3 rounded-md bg-blue-600 px-5 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 disabled:opacity-60"
                                        @click="verifyFace"
                                    >
                                        {{
                                            faceForm.processing
                                                ? 'Verifying...'
                                                : 'Verify Face'
                                        }}
                                        <ArrowRight class="h-4 w-4" />
                                    </button>
                                    <button
                                        type="button"
                                        class="h-12 rounded-md border border-slate-300 px-4 text-sm font-bold text-slate-700 transition hover:bg-slate-50"
                                        @click="mode = 'otp'"
                                    >
                                        Camera not available
                                    </button>
                                    <p
                                        v-if="faceForm.errors.face"
                                        class="text-sm text-red-600"
                                    >
                                        {{ faceForm.errors.face }}
                                    </p>
                                </div>
                            </div>
                            <p
                                v-else
                                class="mt-5 rounded-md bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-700"
                            >
                                No instructor face image is enrolled yet. Use
                                OTP or security question.
                            </p>
                        </div>

                        <form
                            v-else-if="mode === 'otp'"
                            key="otp"
                            class="mt-8 space-y-4 rounded-md border border-slate-200 bg-white p-5 shadow-sm"
                            @submit.prevent="submitOtp"
                        >
                            <div>
                                <h2 class="text-lg font-black text-slate-900">
                                    OTP Email
                                </h2>
                                <p class="mt-1 text-sm text-slate-500">
                                    Send a one-time code to {{ email }}.
                                </p>
                            </div>
                            <button
                                type="button"
                                :disabled="otpSendForm.processing"
                                class="h-11 rounded-md border border-slate-300 px-4 text-sm font-bold text-slate-700 transition hover:bg-slate-50 disabled:opacity-60"
                                @click="sendOtp"
                            >
                                {{
                                    otpSendForm.processing
                                        ? 'Sending...'
                                        : 'Send OTP'
                                }}
                            </button>
                            <input
                                v-model="otpForm.otp"
                                type="text"
                                maxlength="6"
                                class="block h-12 w-full max-w-xs rounded-md border border-slate-300 px-3 text-sm tracking-[0.35em] outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
                                placeholder="000000"
                            />
                            <p
                                v-if="otpForm.errors.otp"
                                class="text-sm text-red-600"
                            >
                                {{ otpForm.errors.otp }}
                            </p>
                            <button
                                type="submit"
                                :disabled="otpForm.processing"
                                class="inline-flex h-12 items-center gap-3 rounded-md bg-blue-600 px-6 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 disabled:opacity-60"
                            >
                                {{
                                    otpForm.processing
                                        ? 'Verifying...'
                                        : 'Verify OTP'
                                }}
                                <ArrowRight class="h-4 w-4" />
                            </button>
                        </form>

                        <form
                            v-else
                            key="security"
                            class="mt-8 space-y-4 rounded-md border border-slate-200 bg-white p-5 shadow-sm"
                            @submit.prevent="verifySecurity"
                        >
                            <div>
                                <h2 class="text-lg font-black text-slate-900">
                                    Security Question
                                </h2>
                                <p class="mt-1 text-sm text-slate-500">
                                    Answer one of your saved recovery questions.
                                </p>
                            </div>
                            <select
                                v-if="securityQuestions.length > 1"
                                v-model="securityForm.security_question"
                                class="block h-11 w-full max-w-md rounded-md border border-slate-300 px-3 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
                                required
                            >
                                <option
                                    v-for="question in securityQuestions"
                                    :key="question.question"
                                    :value="question.question"
                                >
                                    {{ question.question }}
                                </option>
                            </select>
                            <p
                                v-else
                                class="rounded-md bg-slate-50 px-3 py-3 text-sm font-semibold text-slate-700"
                            >
                                {{ securityForm.security_question }}
                            </p>
                            <input
                                v-model="securityForm.security_answer"
                                type="text"
                                class="block h-12 w-full max-w-md rounded-md border border-slate-300 px-3 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
                                placeholder="Answer"
                            />
                            <p
                                v-if="securityForm.errors.security_question"
                                class="text-sm text-red-600"
                            >
                                {{ securityForm.errors.security_question }}
                            </p>
                            <p
                                v-if="securityForm.errors.security_answer"
                                class="text-sm text-red-600"
                            >
                                {{ securityForm.errors.security_answer }}
                            </p>
                            <button
                                type="submit"
                                :disabled="securityForm.processing"
                                class="inline-flex h-12 items-center gap-3 rounded-md bg-blue-600 px-6 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 disabled:opacity-60"
                            >
                                {{
                                    securityForm.processing
                                        ? 'Verifying...'
                                        : 'Verify Answer'
                                }}
                                <ArrowRight class="h-4 w-4" />
                            </button>
                        </form>
                    </Transition>
                </div>
            </section>
        </main>
    </div>
</template>
