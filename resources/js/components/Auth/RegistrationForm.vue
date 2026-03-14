<script setup>
import { useForm } from '@inertiajs/vue3';
import RegistrationInput from './RegistrationInput.vue';
import PasswordField from './PasswordField.vue';

const form = useForm({
    name: null,
    email: null,
    password: null,
    password_confirmation: null,
    role: null,
});

const submit = () => {
    form.post(route('register'), {
        onError: () => form.reset('password', 'password_confirmation'),
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <div class="h-auto w-[600px] rounded-2xl bg-white">
        <div class="p-5">
            <header>
                <h1 class="text-center text-[30px] font-bold text-[#101828]">
                    Register
                </h1>
            </header>

            <div class="text-[#101828]">
                <form @submit.prevent="submit">
                    <RegistrationInput
                        label="Name"
                        v-model="form.name"
                        :message="form.errors.name"
                    />

                    <RegistrationInput
                        type="email"
                        label="Email"
                        v-model="form.email"
                        :message="form.errors.email"
                    />

                    <!-- Password -->
                    <PasswordField
                        v-model="form.password"
                        label="Password"
                        :error="form.errors.password"
                    />

                    <!-- Confirm Password -->
                    <PasswordField
                        v-model="form.password_confirmation"
                        label="Confirm Password"
                        :error="form.errors.password_confirmation"
                    />

                    <div class="my-5">
                        <label for="role">Role:</label>
                        <select
                            v-model="form.role"
                            class="h-[50px] w-full rounded-[10px] border-2 p-2"
                        >
                            <option value="" disabled selected>
                                -- Select Role --
                            </option>
                            <option value="admin">Admin</option>
                            <option value="instructor">Instructor</option>
                        </select>
                        <span
                            v-if="form.errors.role"
                            class="text-sm text-red-500"
                            >{{ form.errors.role }}</span
                        >
                    </div>

                    <div>
                        <button
                            :disabled="form.processing"
                            class="h-[50px] w-full cursor-pointer rounded-[10px] bg-brand text-white disabled:opacity-50"
                        >
                            {{
                                form.processing ? 'Registering...' : 'Register'
                            }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
