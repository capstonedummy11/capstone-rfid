<template>
    <StudentParentLayout>
        <div class="flex h-[calc(100vh-100px)] flex-col overflow-hidden">
            <header class="h-[75px] shrink-0 bg-brand"></header>
            <!-- Hero Section -->
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
                                RFID-Based Attendance <br />
                                Monitoring, Borrowing, and <br />
                                Inventory Management System
                            </p>
                            <p>
                                A smart and automated system designed to improve
                                efficiency, accuracy, <br />
                                and real-time tracking using RFID technology.
                            </p>
                        </div>

                        <div
                            data-aos="fade-right"
                            data-aos-delay="300"
                            data-aos-duration="1000"
                        >
                            <Button
                                label="GET STARTED TODAY"
                                class="border-white p-3 hover:bg-black/50"
                                is-blue="true"
                                :custom-width="'300px'"
                                @click.prevent="isLoginOpen = true"
                            />
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

        <!-- Feature section -->
        <section class="w-full overflow-hidden bg-[#FAFAFA] py-20">
            <div
                class="mx-auto flex max-w-[1400px] flex-wrap items-start justify-center gap-10 px-[50px] lg:flex-nowrap"
            >
                <FeatureCard
                    v-for="(card, index) in cards"
                    :key="index"
                    :title="card.title"
                    :image="card.image"
                    :sub="card.sub"
                    data-aos="fade-down"
                    data-aos-duration="1000"
                    :data-aos-delay="index * 300"
                />
            </div>
        </section>

        <!-- About Us Section -->
        <section class="w-full overflow-hidden pb-10" id="about_us">
            <div class="h-[60%]">
                <section class="flex h-full items-center justify-center gap-10">
                    <About_us_section
                        :has-image="true"
                        :image="Container4"
                        header="Smart Automation Through RFID Technology"
                        sub="The RFID-Based Attendance Monitoring, Borrowing, and Inventory System is designed to provide an efficient and automated solution for tracking attendance, managing borrowed items, and maintaining inventory records."
                    />
                </section>

                <section class="flex h-full items-center justify-center gap-10">
                    <About_us_section
                        header="More information about us"
                        header-size="25px"
                        sub="The RFID-Based Attendance Monitoring, Borrowing, and Inventory System is a web-based solution developed to automate manual processes using RFID technology. This system improves efficiency, accuracy, and security by providing real-time tracking of attendance records, borrowed items, and inventory data."
                        :has-image="false"
                        :image="Container4"
                        :has-button="true"
                    />
                </section>
            </div>
        </section>

        <!-- Call to Action Section -->
        <section class="w-full overflow-hidden">
            <CallToAction />
        </section>

        <!-- Showcase Section -->
        <section class="w-full overflow-hidden">
            <Showcase_section />
        </section>

        <section class="w-full overflow-hidden">
            <Journal />
        </section>

        <section class="w-full overflow-hidden">
            <Gallery />
        </section>

        <footer
            class="bg-azure-gradient flex w-full items-center justify-center p-5 md:h-[300px]"
        >
            <div
                class="flex w-full max-w-[1400px] flex-col gap-10 md:flex-row md:items-center md:justify-center md:gap-20 md:px-20"
            >
                <div
                    class="flex flex-col items-center justify-center text-white"
                >
                    <h1 class="font-bold md:text-[30px]">GET IN TOUCH</h1>
                    <ul class="list-inside list-disc p-2">
                        <li>Phone: + 63-912-345-6789</li>
                        <li>Fax: +1 496 457 654</li>
                        <li>Email: our-mail@example.com</li>
                        <li>Adress: samplestreet123</li>
                    </ul>
                </div>

                <div class="text-white">
                    <h1
                        class="text-center font-bold md:text-left md:text-[30px]"
                    >
                        NEWS LETTER
                    </h1>
                    <br />
                    <p>Sign up your newsletter</p>
                    <div class="mt-3 flex gap-3 md:w-[500px]">
                        <input
                            type="email"
                            class="h-[50px] w-full border-2 border-white bg-transparent p-2"
                            placeholder="Email Address"
                        />
                        <button
                            class="h-[50px] w-full cursor-pointer bg-[#F17A20]"
                        >
                            GO
                        </button>
                    </div>
                </div>
            </div>
        </footer>

        <footer
            class="flex h-[100px] flex-col items-center justify-center bg-[#002F5B] p-5 text-white"
        >
            <p>Blog | Contact Us</p>
            <br />
            <p class="text-center text-custom-gray md:text-left">
                © 2026 RFID - Attendance Monitoring/ Borrowing and Inventory
                System
            </p>
        </footer>
    </StudentParentLayout>
</template>

<script setup>
import StudentParentLayout from '@/layouts/StudentParentLayout.vue';
import Login from '@/components/Auth/Login.vue';
import philsca from '@/assets/images/philsca.png';
import Button from '@/components/Buttons/Button.vue';
import FeatureCard from '@/components/Cards/FeatureCard.vue';
import FeatureImage from '@/assets/images/Container.png';
import FeatureImage2 from '@/assets/images/Container 2.png';
import FeatureImage3 from '@/assets/images/Container 3.png';
import Container4 from '@/assets/images/Container 4.png';
import About_us_section from '@/components/LandingPage/about_us_section.vue';
import CallToAction from '@/components/LandingPage/CallToAction.vue';
import Showcase_section from '@/components/LandingPage/showcase_section.vue';
import Journal from '@/components/LandingPage/Journal.vue';
import Gallery from '@/components/LandingPage/Gallery.vue';
import { useLogin } from '@/composables/useLogin';

const { isLoginOpen } = useLogin();

const cards = [
    {
        image: FeatureImage,
        title: 'ATTENDANCE TRACKING',
        sub: 'Utilizes passive RFID tags for automated entry and exit logging. The system records user ID, timestamp, and location. This eliminates manual sign-ins and enhances overall data accuracy.',
    },
    {
        image: FeatureImage2,
        title: 'ASSET BORROWING',
        sub: "Manages the lending and return of equipment. Each item is tagged and linked to a borrower's profile. The system tracks due dates to reduce loss and improve accountability.",
    },
    {
        image: FeatureImage3,
        title: 'INVENTORY',
        sub: 'Provides a centralized database for all tagged items, conditions, and maintenance schedules. It ensures that asset tracking is accurate and up-to-date.',
    },
];
</script>
