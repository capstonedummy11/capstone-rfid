<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import logo from '@/assets/images/logo-only.jpg';
import EyeOff from '@/components/Icon/EyeOff.vue';
import EyeOn from '@/components/Icon/EyeOn.vue';
import {
    MIN_PASSWORD_LENGTH,
    PASSWORD_LENGTH_ERROR,
    evaluatePasswordRequirements,
    isPasswordTooShort,
    meetsPasswordRequirements,
} from '@/lib/passwordPolicy';

const showPassword = ref(false);
const showPasswordConfirmation = ref(false);

const form = useForm({
    password: '',
    password_confirmation: '',
});
const isSubmitting = ref(false);
const passwordRequirements = computed(() =>
    evaluatePasswordRequirements(form.password),
);
const passwordRequirementsMet = computed(() =>
    meetsPasswordRequirements(form.password),
);
const passwordStrength = computed(() => {
    if (!form.password) {
        return {
            label: 'Enter a password',
            widthClass: 'w-0',
            colorClass: 'bg-slate-300',
            value: 0,
        };
    }

    const metCount = passwordRequirements.value.filter(
        (requirement) => requirement.met,
    ).length;
    const levels = [
        {
            label: 'Weak',
            widthClass: 'w-1/4',
            colorClass: 'bg-red-500',
        },
        {
            label: 'Fair',
            widthClass: 'w-2/4',
            colorClass: 'bg-amber-500',
        },
        {
            label: 'Strong',
            widthClass: 'w-3/4',
            colorClass: 'bg-blue-500',
        },
        {
            label: 'Very strong',
            widthClass: 'w-full',
            colorClass: 'bg-emerald-600',
        },
    ];

    return {
        ...levels[Math.max(0, metCount - 1)],
        value: metCount,
    };
});

const submit = () => {
    if (isSubmitting.value) return;

    if (!passwordRequirementsMet.value) {
        form.setError(
            'password',
            isPasswordTooShort(form.password)
                ? PASSWORD_LENGTH_ERROR
                : 'Password must meet all requirements below.',
        );
        return;
    }

    form.clearErrors('password');
    isSubmitting.value = true;
    form.put(route('password.first-login.update'), {
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};
</script>

<template>
    <Head title="Change Temporary Password" />
    <main
        class="flex min-h-screen items-center justify-center bg-slate-100 p-4"
    >
        <section
            class="w-full max-w-md rounded-xl border-t-4 border-blue-600 bg-white p-6 shadow-lg"
        >
            <img
                :src="logo"
                alt="Pasay City South High School seal"
                class="mx-auto mb-4 h-20 w-20 rounded-full object-cover shadow"
            />
            <h1 class="text-2xl font-bold text-slate-900">
                Create your private password
            </h1>
            <p class="mt-2 text-sm text-slate-600">
                This is a new account using a temporary password. You must
                replace it before accessing the system.
            </p>
            <form class="mt-5 space-y-4" novalidate @submit.prevent="submit">
                <label class="block text-sm font-semibold text-slate-700">
                    New password
                    <div class="relative mt-1">
                        <input
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            required
                            :minlength="MIN_PASSWORD_LENGTH"
                            autofocus
                            autocomplete="new-password"
                            aria-describedby="first-login-password-requirements first-login-password-error"
                            :aria-invalid="
                                Boolean(form.errors.password) ||
                                isPasswordTooShort(form.password)
                            "
                            class="w-full rounded-md border border-slate-300 px-3 py-2 pr-10"
                            @input="form.clearErrors('password')"
                        />
                        <button
                            type="button"
                            class="absolute top-1/2 right-3 -translate-y-1/2 text-slate-500 hover:text-slate-700 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            :aria-label="
                                showPassword
                                    ? 'Hide new password'
                                    : 'Show new password'
                            "
                            :aria-pressed="showPassword"
                            @click="showPassword = !showPassword"
                        >
                            <EyeOn v-if="showPassword" aria-hidden="true" />
                            <EyeOff v-else aria-hidden="true" />
                        </button>
                    </div>
                    <div
                        id="first-login-password-requirements"
                        class="mt-3 rounded-lg border border-slate-200 bg-slate-50 p-3"
                    >
                        <div
                            class="flex items-center justify-between gap-3 text-xs font-semibold"
                        >
                            <span class="text-slate-600"
                                >Password strength</span
                            >
                            <span
                                :class="
                                    passwordStrength.value === 4
                                        ? 'text-emerald-700'
                                        : 'text-slate-700'
                                "
                                aria-live="polite"
                            >
                                {{ passwordStrength.label }}
                            </span>
                        </div>
                        <div
                            class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200"
                            role="progressbar"
                            aria-label="Password strength"
                            :aria-valuenow="passwordStrength.value"
                            aria-valuemin="0"
                            aria-valuemax="4"
                        >
                            <div
                                class="h-full rounded-full transition-all duration-300"
                                :class="[
                                    passwordStrength.widthClass,
                                    passwordStrength.colorClass,
                                ]"
                            ></div>
                        </div>

                        <p class="mt-3 text-xs font-semibold text-slate-700">
                            New password must contain:
                        </p>
                        <ul class="mt-2 space-y-1.5">
                            <li
                                v-for="requirement in passwordRequirements"
                                :key="requirement.key"
                                class="flex items-center gap-2 text-xs"
                                :class="
                                    requirement.met
                                        ? 'text-emerald-700'
                                        : 'text-slate-600'
                                "
                            >
                                <span
                                    class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full text-[10px] font-bold"
                                    :class="
                                        requirement.met
                                            ? 'bg-emerald-600 text-white'
                                            : 'border border-slate-300 bg-white text-transparent'
                                    "
                                    aria-hidden="true"
                                >
                                    ✓
                                </span>
                                {{ requirement.label }}
                            </li>
                        </ul>
                    </div>
                    <span
                        v-if="form.errors.password"
                        id="first-login-password-error"
                        class="mt-2 block text-xs font-semibold text-red-600"
                        role="alert"
                    >
                        {{ form.errors.password }}
                    </span>
                </label>
                <label class="block text-sm font-semibold text-slate-700">
                    Confirm new password
                    <div class="relative mt-1">
                        <input
                            v-model="form.password_confirmation"
                            :type="
                                showPasswordConfirmation ? 'text' : 'password'
                            "
                            required
                            autocomplete="new-password"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 pr-10"
                        />
                        <button
                            type="button"
                            class="absolute top-1/2 right-3 -translate-y-1/2 text-slate-500 hover:text-slate-700 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            :aria-label="
                                showPasswordConfirmation
                                    ? 'Hide password confirmation'
                                    : 'Show password confirmation'
                            "
                            :aria-pressed="showPasswordConfirmation"
                            @click="
                                showPasswordConfirmation =
                                    !showPasswordConfirmation
                            "
                        >
                            <EyeOn
                                v-if="showPasswordConfirmation"
                                aria-hidden="true"
                            />
                            <EyeOff v-else aria-hidden="true" />
                        </button>
                    </div>
                </label>
                <div
                    v-if="
                        Object.keys(form.errors).some(
                            (field) => field !== 'password',
                        )
                    "
                    class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-600"
                >
                    <p
                        v-for="(error, field) in form.errors"
                        :key="field"
                        v-show="field !== 'password'"
                    >
                        {{ error }}
                    </p>
                </div>
                <button
                    type="submit"
                    :disabled="
                        isSubmitting ||
                        form.processing ||
                        !passwordRequirementsMet
                    "
                    class="w-full rounded-md bg-blue-600 px-4 py-2 font-bold text-white disabled:opacity-60"
                >
                    {{
                        isSubmitting || form.processing
                            ? 'Saving...'
                            : 'Save password and continue'
                    }}
                </button>
            </form>
        </section>
    </main>
</template>
