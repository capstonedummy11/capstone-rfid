<template>
    <div class="min-h-screen bg-white font-raleway text-default">
        <header
            class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur"
        >
            <nav
                class="mx-auto flex w-full max-w-[1400px] items-center justify-between px-5 py-4 md:px-12"
            >
                <a href="/" class="flex items-center gap-3">
                    <img :src="logo" alt="RFID logo" class="h-auto w-[170px]" />
                </a>

                <div
                    class="hidden items-center gap-7 text-sm font-semibold text-default md:flex"
                >
                    <a href="/" class="transition hover:text-brand">Home</a>
                    <a href="#about_us" class="transition hover:text-brand"
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
                    <a href="/" @click="isMenuOpen = false">Home</a>
                    <a href="#about_us" @click="isMenuOpen = false">About Us</a>
                </div>
            </div>
        </header>

        <main>
            <section
                id="student_login"
                class="relative min-h-[calc(100vh-74px)] overflow-hidden"
            >
                <img
                    :src="philsca"
                    alt="Philsca campus"
                    class="absolute inset-0 h-full w-full object-cover"
                />
                <div class="absolute inset-0 bg-[#193153]/55"></div>

                <div
                    class="relative mx-auto flex min-h-[calc(100vh-74px)] w-full max-w-[1400px] items-center px-7 py-16 text-white md:px-12"
                >
                    <div class="max-w-4xl">
                        <h1
                            class="text-[34px] leading-tight font-semibold md:text-[54px]"
                        >
                            RFID-Based Attendance
                            <br />
                            Monitoring, Borrowing, and
                            <br />
                            Inventory Management System
                        </h1>
                        <p
                            class="mt-6 max-w-2xl text-base leading-7 text-white/90 md:text-lg"
                        >
                            A smart and automated system designed to improve
                            efficiency, accuracy, and real-time tracking using
                            RFID technology.
                        </p>
                        <a
                            href="/login"
                            class="mt-8 inline-flex w-full max-w-[300px] items-center justify-center border-2 border-white bg-brand px-6 py-3 text-sm font-bold tracking-wide text-white transition hover:bg-black/50 sm:w-[300px]"
                        >
                            GET STARTED TODAY
                        </a>
                    </div>
                </div>
            </section>

            <section class="bg-[#FAFAFA] py-20">
                <div
                    class="mx-auto grid max-w-[1400px] gap-8 px-7 md:px-12 lg:grid-cols-3"
                >
                    <article
                        v-for="card in featureCards"
                        :key="card.title"
                        class="overflow-hidden rounded-lg bg-white shadow-lg"
                    >
                        <img
                            :src="card.image"
                            :alt="card.title"
                            class="h-56 w-full object-cover"
                        />
                        <div class="p-7">
                            <h2 class="text-xl font-bold text-default">
                                {{ card.title }}
                            </h2>
                            <p class="mt-4 text-sm leading-7 text-custom-gray">
                                {{ card.sub }}
                            </p>
                        </div>
                    </article>
                </div>
            </section>

            <section id="about_us" class="bg-white py-20">
                <div
                    class="mx-auto grid max-w-[1400px] items-center gap-12 px-7 md:px-12 lg:grid-cols-2"
                >
                    <img
                        :src="container4"
                        alt="RFID automation"
                        class="w-full rounded-lg object-cover shadow-lg"
                    />
                    <div>
                        <p
                            class="text-sm font-bold tracking-[0.25em] text-brand uppercase"
                        >
                            About the System
                        </p>
                        <h2
                            class="mt-4 text-3xl leading-tight font-bold text-default md:text-4xl"
                        >
                            Smart Automation Through RFID Technology
                        </h2>
                        <p class="mt-5 text-base leading-8 text-custom-gray">
                            The RFID-Based Attendance Monitoring, Borrowing, and
                            Inventory System is designed to provide an efficient
                            and automated solution for tracking attendance,
                            managing borrowed items, and maintaining inventory
                            records.
                        </p>
                    </div>
                </div>

                <div
                    class="mx-auto mt-16 max-w-[1100px] px-7 text-center md:px-12"
                >
                    <h2 class="text-2xl font-bold text-default">
                        More information about us
                    </h2>
                    <p class="mt-5 text-base leading-8 text-custom-gray">
                        This web-based solution automates manual processes using
                        RFID technology. It improves efficiency, accuracy, and
                        security by providing real-time tracking of attendance
                        records, borrowed items, and inventory data.
                    </p>
                    <a
                        href="/about"
                        class="mt-7 inline-flex border-2 border-brand px-8 py-3 text-sm font-bold text-brand transition hover:bg-brand hover:text-white"
                    >
                        LEARN MORE
                    </a>
                </div>
            </section>

            <section class="bg-azure-gradient py-16 text-white">
                <div
                    class="mx-auto flex max-w-[1400px] flex-col gap-7 px-7 md:flex-row md:items-center md:justify-between md:px-12"
                >
                    <div>
                        <h2 class="text-3xl font-bold">
                            Ready to modernize your RFID workflows?
                        </h2>
                        <p class="mt-3 max-w-2xl text-white/85">
                            Manage attendance, borrowing, and inventory records
                            through one connected system.
                        </p>
                    </div>
                    <a
                        href="/login"
                        class="inline-flex w-full max-w-[260px] justify-center border-2 border-white px-6 py-3 text-sm font-bold transition hover:bg-white hover:text-brand"
                    >
                        ACCESS SYSTEM
                    </a>
                </div>
            </section>

            <section class="bg-white py-20">
                <div class="mx-auto max-w-[1400px] px-7 md:px-12">
                    <div class="max-w-3xl">
                        <p
                            class="text-sm font-bold tracking-[0.25em] text-brand uppercase"
                        >
                            System Showcase
                        </p>
                        <h2 class="mt-4 text-3xl font-bold text-default">
                            Built for daily school operations
                        </h2>
                    </div>

                    <div class="mt-10 grid gap-7 md:grid-cols-3">
                        <article
                            v-for="item in showcaseItems"
                            :key="item.title"
                            class="border border-slate-200 bg-white p-6 shadow-sm"
                        >
                            <div class="text-4xl font-bold text-brand">
                                {{ item.number }}
                            </div>
                            <h3 class="mt-5 text-xl font-bold text-default">
                                {{ item.title }}
                            </h3>
                            <p class="mt-3 text-sm leading-7 text-custom-gray">
                                {{ item.text }}
                            </p>
                        </article>
                    </div>
                </div>
            </section>

            <section class="bg-[#FAFAFA] py-20">
                <div class="mx-auto max-w-[1400px] px-7 md:px-12">
                    <div
                        class="flex flex-col justify-between gap-5 md:flex-row md:items-end"
                    >
                        <div>
                            <p
                                class="text-sm font-bold tracking-[0.25em] text-brand uppercase"
                            >
                                Gallery
                            </p>
                            <h2 class="mt-4 text-3xl font-bold text-default">
                                RFID modules and assets
                            </h2>
                        </div>
                    </div>

                    <div class="mt-10 grid gap-5 md:grid-cols-3">
                        <img
                            v-for="image in galleryImages"
                            :key="image.alt"
                            :src="image.src"
                            :alt="image.alt"
                            class="h-72 w-full rounded-lg object-cover shadow-md"
                        />
                    </div>
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
import featureImage from '@/assets/images/Container.png';
import featureImage2 from '@/assets/images/Container 2.png';
import featureImage3 from '@/assets/images/Container 3.png';
import container4 from '@/assets/images/Container 4.png';
import item1 from '@/assets/images/Item 1.png';
import item2 from '@/assets/images/Item 2.png';
import item3 from '@/assets/images/Item 3.png';

defineOptions({
    layout: null,
});

const isMenuOpen = ref(false);

const featureCards = [
    {
        image: featureImage,
        title: 'ATTENDANCE TRACKING',
        sub: 'Utilizes passive RFID tags for automated entry and exit logging. The system records user ID, timestamp, and location. This eliminates manual sign-ins and enhances overall data accuracy.',
    },
    {
        image: featureImage2,
        title: 'ASSET BORROWING',
        sub: "Manages the lending and return of equipment. Each item is tagged and linked to a borrower's profile. The system tracks due dates to reduce loss and improve accountability.",
    },
    {
        image: featureImage3,
        title: 'INVENTORY',
        sub: 'Provides a centralized database for all tagged items, conditions, and maintenance schedules. It ensures that asset tracking is accurate and up-to-date.',
    },
];

const showcaseItems = [
    {
        number: '01',
        title: 'Attendance Monitoring',
        text: 'RFID taps can record student time-in and time-out events while reducing manual encoding work.',
    },
    {
        number: '02',
        title: 'Borrowing Records',
        text: 'Borrowers and tagged items can be connected to keep equipment activity clear and accountable.',
    },
    {
        number: '03',
        title: 'Inventory Visibility',
        text: 'Tagged inventory items can be monitored through centralized records for faster checking and reporting.',
    },
];

const galleryImages = [
    {
        src: item1,
        alt: 'RFID item preview one',
    },
    {
        src: item2,
        alt: 'RFID item preview two',
    },
    {
        src: item3,
        alt: 'RFID item preview three',
    },
];
</script>
