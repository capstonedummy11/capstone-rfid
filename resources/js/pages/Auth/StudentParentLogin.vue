<template>
    <StudentParentLayout>
        <div class="flex h-[calc(100vh-100px)] flex-col overflow-hidden">
            <header class="h-[75px] shrink-0 bg-brand"></header>
            <!-- Hero Section with Login -->
            <section
                class="relative min-h-[400px] flex-1 overflow-hidden md:h-screen"
            >
                <img
                    :src="philsca"
                    alt="Philsca"
                    class="absolute inset-0 h-full w-full object-cover"
                />

                <div
                    class="absolute inset-0 items-center bg-[#193153]/50 md:flex"
                >
                    <div
                        class="mx-auto w-full max-w-[1400px] px-[50px] font-raleway text-white"
                    >
                        <div class="py-10">
                            <p class="text-[25px] font-medium md:text-[50px]">
                                Student & Parent <br />
                                Portal
                            </p>
                            <p>
                                Secure login to access your attendance records,
                                <br />
                                borrowing history, and important updates.
                            </p>
                        </div>
                    </div>
                    <div
                        v-if="isLoginOpen"
                        class="absolute inset-0 h-full md:static md:inset-auto md:w-[700px]"
                        data-aos="fade-left"
                        data-aos-duration="500"
                    >
                        <Login @open-login="isLoginOpen = true" />
                    </div>
                </div>
            </section>
        </div>
    </StudentParentLayout>
</template>

<script setup>
import { ref } from 'vue';
import StudentParentLayout from '@/layouts/StudentParentLayout.vue';
import Login from '@/components/Auth/Login.vue';
import philsca from '@/assets/images/philsca.jpg';
import { useLogin } from '@/composables/useLogin';

const { isLoginOpen } = useLogin();
</script>
