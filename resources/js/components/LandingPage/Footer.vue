<script setup>
const currentYear = new Date().getFullYear();
</script>

<template>
    <footer class="bg-[#002F5B] px-5 py-10 text-white">
        <div
            class="mx-auto flex max-w-[1400px] flex-col items-center gap-5 text-center"
        >
            <h2 class="text-xl font-bold">Contact Us</h2>

            <address
                class="flex flex-col items-center gap-2 text-sm leading-6 not-italic text-white/80 sm:flex-row sm:flex-wrap sm:justify-center sm:gap-x-6"
            >
                <a
                    href="tel:09608343291"
                    class="transition hover:text-white hover:underline"
                >
                    09608343291
                </a>
                <a
                    href="mailto:bwct101519@gmail.com"
                    class="transition hover:text-white hover:underline"
                >
                    bwct101519@gmail.com
                </a>
                <span>Piccio, Villamor, Pasay City</span>
            </address>

            <p class="text-sm text-custom-gray">
                Copyright {{ currentYear }} RFID - Attendance
                Monitoring/Borrowing and Inventory System
            </p>
        </div>
    </footer>
</template>
