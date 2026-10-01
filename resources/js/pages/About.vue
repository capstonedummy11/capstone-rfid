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
                    <a href="/about" class="text-brand">About Us</a>
                </div>

                <button
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center border border-slate-300 text-default md:hidden"
                    aria-label="Open menu"
                    :aria-expanded="isMenuOpen"
                    @click="isMenuOpen = !isMenuOpen"
                >
                    <span class="text-2xl leading-none">{{
                        isMenuOpen ? '×' : '='
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
                    <a href="/about" @click="isMenuOpen = false">About Us</a>
                </div>
            </div>
        </header>

        <main>
            <section class="bg-[#071052] px-6 py-16 text-white md:py-24">
                <div class="mx-auto max-w-[1400px] text-center">
                    <p
                        class="text-sm font-bold tracking-[0.28em] text-white/70 uppercase"
                    >
                        Meet the team
                    </p>
                    <h1 class="mt-4 text-4xl font-bold md:text-6xl">
                        About Us
                    </h1>
                    <p
                        class="mx-auto mt-6 max-w-2xl text-base leading-8 text-white/80 md:text-lg"
                    >
                        We are the team behind the RFID Attendance and School
                        Operations System, working together to make everyday
                        school operations clearer, faster, and easier to manage.
                    </p>
                </div>
            </section>

            <section class="bg-white px-6 py-16 md:py-24">
                <div class="mx-auto max-w-5xl">
                    <div class="mb-12 text-center">
                        <p
                            class="text-sm font-bold tracking-[0.25em] text-brand uppercase"
                        >
                            The developers
                        </p>
                        <h2
                            class="mt-4 text-3xl font-bold text-default md:text-4xl"
                        >
                            The people behind the system
                        </h2>
                    </div>

                    <div class="space-y-6">
                        <article
                            v-for="developer in developers"
                            :key="developer.name"
                            class="grid items-center gap-7 border-t-2 border-[#071052] py-6 md:grid-cols-[180px_1fr] md:gap-10 md:py-8"
                        >
                            <button
                                type="button"
                                class="group mx-auto block rounded-full focus:outline-none focus-visible:ring-4 focus-visible:ring-brand/30 md:mx-0"
                                :aria-label="`View ${developer.name}'s developer photo`"
                                @click="revealEasterEgg(developer)"
                            >
                                <img
                                    :src="developer.image"
                                    :alt="`${developer.name} portrait`"
                                    class="h-36 w-36 rounded-full object-cover shadow-md transition duration-300 group-hover:scale-105 md:h-40 md:w-40"
                                    :class="
                                        developer.revealed
                                            ? 'border-0'
                                            : 'border-[7px] border-[#071052]'
                                    "
                                />
                            </button>

                            <div class="text-center md:text-left">
                                <h3
                                    class="text-xl font-semibold tracking-wide text-default uppercase md:text-2xl"
                                >
                                    {{ developer.name }}
                                </h3>
                                <p
                                    class="mt-3 text-base leading-8 text-custom-gray"
                                >
                                    {{ developer.description }}
                                </p>
                                <p
                                    v-if="developer.revealed"
                                    class="mt-3 text-xs font-semibold tracking-[0.18em] text-brand uppercase"
                                >
                                    Easter egg unlocked
                                </p>
                            </div>
                        </article>
                    </div>
                </div>
            </section>

            <section class="relative overflow-hidden bg-[#071052]">
                <img
                    :src="teamPhoto"
                    alt="The four developers together outside PhilSCA"
                    class="h-[420px] w-full object-cover object-[center_58%] opacity-80 md:h-[560px] md:object-[center_60%]"
                />
                <div
                    class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-[#071052] via-[#071052]/70 to-transparent px-6 pt-24 pb-10 text-center text-white"
                >
                    <p class="text-2xl font-bold md:text-4xl">
                        Built with purpose. Shaped by teamwork.
                    </p>
                </div>
            </section>
        </main>

        <Footer />
    </div>
</template>

<script setup>
import { ref } from 'vue';
import logo from '@/assets/images/logo.png';
import Footer from '@/components/LandingPage/Footer.vue';
import teamPhoto from '@/assets/images/Developers/About Us (Bottom Picture).jpg';
import piandreaImage from '@/assets/images/Developers/PIANDREA CUYACOT.png';
import lizetteImage from '@/assets/images/Developers/LIZETTE TAPAO.png';
import laizelImage from '@/assets/images/Developers/LAIZEL WONG.png';
import norhataImage from '@/assets/images/Developers/NORHATA BADELLES.png';
import piandreaEasterEgg from '@/assets/images/Developers/EasterEgg/Cuyacot.png';
import lizetteEasterEgg from '@/assets/images/Developers/EasterEgg/Tapao.jpg';
import laizelEasterEgg from '@/assets/images/Developers/EasterEgg/Wong.jpg';
import norhataEasterEgg from '@/assets/images/Developers/EasterEgg/Badelles.png';

defineOptions({
    layout: null,
});

const isMenuOpen = ref(false);
const EASTER_EGG_DURATION_MS = 15_000;

const developers = ref([
    {
        name: 'Piandrea Luisa Cuyacot',
        image: piandreaImage,
        easterEggImage: piandreaEasterEgg,
        description:
            'The team member who keeps everyone moving forward, helping with planning, decision-making, and making sure the project stays on track.',
        clicks: 0,
        revealed: false,
    },
    {
        name: 'Lizette May Tapao',
        image: lizetteImage,
        easterEggImage: lizetteEasterEgg,
        description:
            "The creative mind behind the team's ideas and designs, focusing on making the project simple, appealing, and easy to use.",
        clicks: 0,
        revealed: false,
    },
    {
        name: 'Laizel Ann Wong',
        image: laizelImage,
        easterEggImage: laizelEasterEgg,
        description:
            'The detail-oriented member who focuses on research, documentation, testing, and making sure everything is properly organized.',
        clicks: 0,
        revealed: false,
    },
    {
        name: 'Norhata Badelles',
        image: norhataImage,
        easterEggImage: norhataEasterEgg,
        description:
            'The team member who helps bring ideas to life, contributing to development, improvements, and making sure the final system works as intended.',
        clicks: 0,
        revealed: false,
    },
]);

function revealEasterEgg(developer) {
    if (developer.revealed) {
        return;
    }

    developer.clicks += 1;

    if (developer.clicks === 3) {
        const originalImage = developer.image;

        developer.image = developer.easterEggImage;
        developer.revealed = true;

        window.setTimeout(() => {
            developer.image = originalImage;
            developer.clicks = 0;
            developer.revealed = false;
        }, EASTER_EGG_DURATION_MS);
    }
}
</script>
