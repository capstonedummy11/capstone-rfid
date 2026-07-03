<template>
    <div>
        <header class="relative w-full md:flex md:items-center">
            <nav class="flex w-full items-center justify-between p-5 md:px-20">
                <div>
                    <img :src="logo" alt="logo" class="h-auto w-[200px]" />
                </div>

                <div class="hidden items-center md:flex">
                    <NavButton
                        :href="route('landingPage')"
                        :label="'Home'"
                        class="m-5"
                    />
                    <NavButton
                        href="#about_us"
                        :label="'About Us'"
                        class="m-5"
                    />
                    <NavButton
                        :label="'Login'"
                        :is-blue="true"
                        class="m-5"
                        @click="handleLogin"
                    />
                </div>

                <Menu v-model="isMenuOpen" class="md:hidden" />
            </nav>

            <!-- Mobile dropdown -->
            <div
                v-if="isMenuOpen"
                class="absolute top-full right-0 left-0 z-50 flex flex-col items-center bg-white shadow-lg md:hidden"
            >
                <NavButton
                    :href="route('landingPage')"
                    :label="'Home'"
                    @click="isMenuOpen = false"
                    class="m-5"
                />
                <NavButton
                    href="#about_us"
                    :label="'About Us'"
                    class="m-5"
                    @click="isMenuOpen = false"
                />
                <NavButton
                    :label="'Login'"
                    :is-blue="true"
                    class="m-5"
                    @click="handleLogin"
                />
            </div>
        </header>

        <main>
            <slot />
        </main>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import Menu from '@/components/Buttons/Menu.vue';
import logo from '../assets/images/logo.png';
import NavButton from '@/components/Buttons/Button.vue';
import { useLogin } from '@/composables/useLogin';

const isMenuOpen = ref(false);
const { isLoginOpen } = useLogin();

function handleLogin() {
    isMenuOpen.value = false;
    isLoginOpen.value = true;
}
</script>
