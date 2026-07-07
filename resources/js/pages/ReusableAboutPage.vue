<template>
    <div class="min-h-screen bg-white font-raleway text-default">
        <header
            class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur"
        >
            <nav
                class="mx-auto flex w-full max-w-[1400px] items-center justify-between px-5 py-4 md:px-12"
            >
                <a :href="homePath" class="flex items-center gap-3">
                    <img :src="logo" alt="RFID logo" class="h-auto w-[170px]" />
                </a>

                <div
                    class="hidden items-center gap-7 text-sm font-semibold text-default md:flex"
                >
                    <a :href="homePath" class="transition hover:text-brand"
                        >Home</a
                    >
                    <a :href="aboutPath" class="transition hover:text-brand"
                        >About Us</a
                    >
                </div>

                <button
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center border border-slate-300 text-default md:hidden"
                    aria-label="Open menu"
                    @click="isMenuOpen = !isMenuOpen"
                >
                    <span class="text-2xl leading-none">{{
                        isMenuOpen ? 'x' : '='
                    }}</span>
                </button>
            </nav>

            <div
                v-if="isMenuOpen"
                class="border-t border-slate-200 bg-white px-5 py-4 md:hidden"
            >
                <div
                    class="flex flex-col gap-4 text-sm font-semibold text-default"
                >
                    <a :href="homePath" @click="isMenuOpen = false">Home</a>
                    <a :href="aboutPath" @click="isMenuOpen = false"
                        >About Us</a
                    >
                </div>
            </div>
        </header>

        <main>
            <section class="relative overflow-hidden bg-[#002F5B] text-white">
                <img
                    :src="philsca"
                    alt="Philsca campus"
                    class="absolute inset-0 h-full w-full object-cover opacity-25"
                />
                <div
                    class="relative mx-auto max-w-[1400px] px-7 py-24 md:px-12 md:py-32"
                >
                    <p
                        class="text-sm font-bold tracking-[0.28em] text-white/70 uppercase"
                    >
                        About Us
                    </p>
                    <h1
                        class="mt-5 max-w-4xl text-4xl leading-tight font-bold md:text-6xl"
                    >
                        RFID technology for faster, clearer school operations.
                    </h1>
                    <p
                        class="mt-6 max-w-3xl text-base leading-8 text-white/85 md:text-lg"
                    >
                        This system brings attendance monitoring, item
                        borrowing, and inventory tracking into one connected
                        workflow for real-time visibility and accountability.
                    </p>
                </div>
            </section>

            <section class="bg-white py-20">
                <div
                    class="mx-auto grid max-w-[1400px] items-center gap-12 px-7 md:px-12 lg:grid-cols-2"
                >
                    <div>
                        <p
                            class="text-sm font-bold tracking-[0.25em] text-brand uppercase"
                        >
                            What It Does
                        </p>
                        <h2
                            class="mt-4 text-3xl leading-tight font-bold text-default md:text-4xl"
                        >
                            One RFID-based system for attendance, borrowing, and
                            inventory.
                        </h2>
                        <p class="mt-5 text-base leading-8 text-custom-gray">
                            The platform helps reduce manual encoding, improve
                            record accuracy, and make equipment movement easier
                            to monitor. RFID taps can identify users and items
                            quickly, while the dashboard gives administrators a
                            central place to review activity.
                        </p>
                    </div>
                    <img
                        :src="container4"
                        alt="RFID automation"
                        class="w-full rounded-lg object-cover shadow-lg"
                    />
                </div>
            </section>

            <section class="bg-[#FAFAFA] py-20">
                <div class="mx-auto max-w-[1400px] px-7 md:px-12">
                    <div class="max-w-3xl">
                        <p
                            class="text-sm font-bold tracking-[0.25em] text-brand uppercase"
                        >
                            Core Modules
                        </p>
                        <h2 class="mt-4 text-3xl font-bold text-default">
                            Designed around the daily flow of the laboratory.
                        </h2>
                    </div>

                    <div class="mt-10 grid gap-7 md:grid-cols-3">
                        <article
                            v-for="module in modules"
                            :key="module.title"
                            class="rounded-lg border border-slate-200 bg-white p-7 shadow-sm"
                        >
                            <div class="text-4xl font-bold text-brand">
                                {{ module.number }}
                            </div>
                            <h3 class="mt-5 text-xl font-bold text-default">
                                {{ module.title }}
                            </h3>
                            <p class="mt-3 text-sm leading-7 text-custom-gray">
                                {{ module.text }}
                            </p>
                        </article>
                    </div>
                </div>
            </section>

            <section class="bg-white py-20">
                <div
                    class="mx-auto grid max-w-[1400px] gap-8 px-7 md:px-12 lg:grid-cols-2"
                >
                    <article
                        class="rounded-lg border border-slate-200 p-8 shadow-sm"
                    >
                        <h2 class="text-2xl font-bold text-default">Purpose</h2>
                        <p class="mt-4 text-base leading-8 text-custom-gray">
                            The system supports school staff by making common
                            records easier to capture and verify. It helps teams
                            spend less time on repetitive manual tracking and
                            more time maintaining accurate operations.
                        </p>
                    </article>

                    <article
                        class="rounded-lg border border-slate-200 p-8 shadow-sm"
                    >
                        <h2 class="text-2xl font-bold text-default">Benefit</h2>
                        <p class="mt-4 text-base leading-8 text-custom-gray">
                            RFID-based identification helps connect people,
                            locations, and assets with timestamps. This creates
                            a stronger activity trail for attendance, borrowed
                            equipment, and inventory updates.
                        </p>
                    </article>
                </div>
            </section>
        </main>

        <footer class="bg-azure-gradient p-7 text-white md:py-16">
            <div
                class="mx-auto flex max-w-[1400px] flex-col gap-10 md:flex-row md:items-start md:justify-center md:gap-20"
            >
                <div>
                    <h2 class="text-2xl font-bold">GET IN TOUCH</h2>
                    <ul class="mt-4 list-inside list-disc leading-8">
                        <li>Phone: +63-912-345-6789</li>
                        <li>Fax: +1 496 457 654</li>
                        <li>Email: our-mail@example.com</li>
                        <li>Address: samplestreet123</li>
                    </ul>
                </div>

                <div class="w-full max-w-[520px]">
                    <h2 class="text-2xl font-bold">NEWS LETTER</h2>
                    <p class="mt-4">Sign up your newsletter</p>
                    <form class="mt-4 flex flex-col gap-3 sm:flex-row">
                        <input
                            type="email"
                            class="h-[50px] w-full border-2 border-white bg-transparent p-3 text-white placeholder:text-white/70"
                            placeholder="Email Address"
                        />
                        <button
                            type="button"
                            class="h-[50px] bg-[#F17A20] px-10 font-bold text-white"
                        >
                            GO
                        </button>
                    </form>
                </div>
            </div>
        </footer>

        <footer
            class="flex min-h-[100px] flex-col items-center justify-center bg-[#002F5B] p-5 text-white"
        >
            <p>Blog | Contact Us</p>
            <p class="mt-3 text-center text-custom-gray">
                Copyright 2026 RFID - Attendance Monitoring/Borrowing and
                Inventory System
            </p>
        </footer>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import logo from '@/assets/images/logo.png';
import philsca from '@/assets/images/philsca.png';
import container4 from '@/assets/images/Container 4.png';

defineOptions({
    layout: null,
});

const homePath = '/';
const aboutPath = '/about';
const isMenuOpen = ref(false);

const modules = [
    {
        number: '01',
        title: 'Attendance Monitoring',
        text: 'RFID taps help record student presence with timestamps and reduce manual sign-in errors.',
    },
    {
        number: '02',
        title: 'Borrowing Control',
        text: 'Borrowed equipment can be linked to a borrower profile, item record, due date, and status.',
    },
    {
        number: '03',
        title: 'Inventory Tracking',
        text: 'Tagged assets can be monitored through centralized records for faster checking and reporting.',
    },
];
</script>
