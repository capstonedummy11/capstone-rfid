<template>
    <div class="relative h-full w-auto bg-white">
        <div
            class="absolute inset-0 flex h-full w-full items-center justify-center"
        >
            <form class="m-10 w-full" @submit.prevent="submit">
                <div class="m-5">
                    <header class="py-5">
                        <h1
                            class="text-center text-[30px] font-bold text-[#101828]"
                        >
                            Login to your account
                        </h1>
                    </header>
                    <div class="text-[#101828]">
                        <div class="my-5 flex flex-col gap-5">
                            <label for="email">Email: </label>
                            <input
                                type="email"
                                name="email"
                                v-model="form.email"
                                class="h-[50px] w-full rounded-[10px] border-2 p-2"
                            />
                        </div>
                        <div class="my-5 flex flex-col">
                            <label for="password">Password:</label>
                            <div class="relative gap-5">
                                <input
                                    :type="showPassword ? 'text' : 'password'"
                                    name="password"
                                    v-model="form.password"
                                    class="h-[50px] w-full rounded-[10px] border-2 p-2 pr-10"
                                />
                                <button
                                    type="button"
                                    @click="showPassword = !showPassword"
                                    class="absolute top-1/2 right-3 -translate-y-1/2"
                                >
                                    <!-- Eye open -->
                                    <EyeOn v-if="showPassword" />
                                    <!-- Eye off -->
                                    <EyeOff v-else />
                                </button>
                            </div>
                            <Link class="m-0 p-0 text-brand">
                                Forgot Password?
                            </Link>
                        </div>

                        <div class="space-y-5">
                            <LoginButton :disabled="form.processing" text="Login" />
                            <LoginButton :disabled="form.processing" text="Login as Admin" />
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>
<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import EyeOn from '../Icon/EyeOn.vue';
import EyeOff from '../Icon/EyeOff.vue';
import LoginButton from '../Buttons/LoginButton.vue';

const showPassword = ref(false);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/login');
};
</script>
