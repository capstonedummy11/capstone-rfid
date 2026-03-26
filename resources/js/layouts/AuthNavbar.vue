<script setup>
import { Link } from '@inertiajs/vue3';
import Navlinks from '@/components/Auth/Navlinks.vue';
import ActivityLogs from '@/components/Icon/ActivityLogs.vue';
import Attendance from '@/components/Icon/Attendance.vue';
import Borrowing from '@/components/Icon/Borrowing.vue';
import Dashboard from '@/components/Icon/Dashboard.vue';
import Graduation from '@/components/Icon/Graduation.vue';
import Instructor from '@/components/Icon/Instructor.vue';
import Laboratory from '@/components/Icon/Laboratory.vue';
import LogoutIcon from '@/components/Icon/LogoutIcon.vue';
import Reports from '@/components/Icon/Reports.vue';
import RFID from '@/components/Icon/RFID.vue';
import Schedule from '@/components/Icon/Schedule.vue';
import Section from '@/components/Icon/Section.vue';
import Trash from '@/components/Icon/Trash.vue';
// import TwoPerson from '@/components/Icon/TwoPerson.vue';

const links = [
    {
        icon: Dashboard,
        text: 'Dashboard',
        route: route('admin.dashboard'),
    },
    {
        icon: Graduation,
        text: 'School Year',
        //route: route('admin.instructorsManagement'),
    },
    {
        icon: Laboratory,
        text: 'Laboratories',
        route: route('admin.laboratories'),
    },
    {
        icon: Schedule,
        text: 'Schedule',
    },
    {
        icon: Instructor,
        text: 'Instructor',
        route: route('admin.instructors.index'),
    },
    {
        icon: Instructor,
        text: 'Students',
        route: route('admin.students.index'),
    },
    {
        icon: Section,
        text: 'Section',
    },
    {
        icon: Attendance,
        text: 'Attendance',
    },
    {
        icon: Borrowing,
        text: 'Borrowing',
        route: route('admin.borrow'),
    },
    {
        icon: Reports,
        text: 'Reports',
    },
    {
        icon: ActivityLogs,
        text: 'Activity Logs',
    },
    {
        icon: Trash,
        text: 'Trash',
    },
    {
        icon: RFID,
        text: 'RFID Management',
        route: route('admin.rfid'),
    },
];
</script>

<template>
    <nav
        class="flex w-[250px] flex-col justify-between bg-white text-default drop-shadow-xl"
    >
        <div class="flex flex-col">
            <header class="p-4 text-nav-header">
                <h1 class="text-[18px]">Dashboard</h1>
            </header>

            <div class="mb-5">
                <Navlinks
                    v-for="(item, index) in links.slice(0, 4)"
                    :key="index"
                    :icon="item.icon"
                    :text="item.text"
                    :route="item.route"
                />
            </div>

            <header class="p-4 text-nav-header">
                <h1 class="text-[18px]">Management</h1>
            </header>

            <div>
                <Navlinks
                    v-for="(item, index) in links.slice(4, 8)"
                    :key="index"
                    :icon="item.icon"
                    :text="item.text"
                    :route="item.route"
                />
            </div>

            <header class="p-4 text-nav-header">
                <h1 class="text-[18px]">System</h1>
            </header>

            <div>
                <Navlinks
                    v-for="(item, index) in links.slice(8)"
                    :key="index"
                    :icon="item.icon"
                    :text="item.text"
                    :route="item.route"
                />
            </div>
        </div>

        <!-- Logout Button -->
        <Link
            :href="route('logout')"
            method="post"
            as="button"
            type="button"
            class="auth-nav-link group w-full border-t-2 text-left"
        >
            <LogoutIcon class="text-[#A3AED0] group-hover:text-brand" />
            <h1>Logout</h1>
        </Link>
    </nav>
</template>
