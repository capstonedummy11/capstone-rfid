<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import logo from '@/assets/images/logo-only.jpg';
import EyeOff from '@/components/Icon/EyeOff.vue';
import EyeOn from '@/components/Icon/EyeOn.vue';
import {
    MIN_PASSWORD_LENGTH,
    PASSWORD_LENGTH_ERROR,
    PASSWORD_LENGTH_HELPER,
    isPasswordTooShort,
} from '@/lib/passwordPolicy';

const showPassword = ref(false);
const showPasswordConfirmation = ref(false);

const form = useForm({
    password: '',
    password_confirmation: '',
});
const isSubmitting = ref(false);

const submit = () => {
    if (isSubmitting.value) return;

    if (isPasswordTooShort(form.password)) {
        form.setError('password', PASSWORD_LENGTH_ERROR);
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
                            aria-describedby="first-login-password-requirement"
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
                    <span
                        id="first-login-password-requirement"
                        class="mt-1 block text-xs"
                        :class="
                            form.errors.password ||
                            isPasswordTooShort(form.password)
                                ? 'text-red-600'
                                : 'text-slate-500'
                        "
                        aria-live="polite"
                    >
                        {{
                            form.errors.password ||
                            (isPasswordTooShort(form.password)
                                ? PASSWORD_LENGTH_ERROR
                                : PASSWORD_LENGTH_HELPER)
                        }}
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
                    :disabled="isSubmitting || form.processing"
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
