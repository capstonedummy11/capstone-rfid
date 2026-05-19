<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
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
import Inventory from '@/components/Icon/Inventory.vue';

const page = usePage();
const currentRole = computed(() =>
    String(page.props.auth?.user?.role ?? '').toLowerCase(),
);
const canSee = (roles) => roles.includes(currentRole.value);

const sections = [
    {
        title: 'Dashboard',
        links: [
            {
                icon: Dashboard,
                text: 'Dashboard',
                route: route('admin.dashboard'),
                roles: ['admin', 'instructor'],
            },
            {
                icon: Dashboard,
                text: 'Clinic Dashboard',
                route: route('clinic.dashboard'),
                roles: ['clinic'],
            },
            {
                icon: Graduation,
                text: 'School Year',
                roles: ['admin'],
                //route: route('admin.instructorsManagement'),
            },
            {
                icon: Graduation,
                text: 'Strands',
                route: route('admin.strands.index'),
                roles: ['admin'],
            },
            {
                icon: Laboratory,
                text: 'Laboratories',
                route: route('admin.laboratories'),
                roles: ['admin'],
            },
        ],
    },
    {
        title: 'Management',
        links: [
            {
                icon: Schedule,
                text: 'Schedule',
                route: route('admin.schedules.index'),
                roles: ['admin', 'instructor'],
            },
            {
                icon: Instructor,
                text: 'Instructor',
                route: route('admin.instructors.index'),
                roles: ['admin'],
            },
            {
                icon: Instructor,
                text: 'Students',
                route: route('admin.students.index'),
                roles: ['admin', 'instructor'],
            },
            {
                icon: Section,
                text: 'Section',
                route: route('admin.sections.index'),
                roles: ['admin'],
            },
            {
                icon: Attendance,
                text: 'Attendance',
                route: route('admin.attendance.scanner'),
                roles: ['admin', 'instructor'],
            },
        ],
    },
    {
        title: 'System',
        links: [
            {
                icon: Graduation,
                text: 'Subjects',
                route: route('admin.subjects.index'),
                roles: ['admin'],
            },
            {
                icon: Borrowing,
                text: 'Borrowing',
                route: route('admin.borrow'),
                roles: ['admin'],
            },
            {
                icon: Inventory,
                text: 'Inventory',
                route: route('admin.inventory'),
                roles: ['admin'],
            },
            {
                icon: Reports,
                text: 'Reports',
                roles: ['admin'],
            },
            {
                icon: ActivityLogs,
                text: 'Activity Logs',
                route: route('admin.activity-logs.index'),
                roles: ['admin'],
            },
            {
                icon: ActivityLogs,
                text: 'Case Logs',
                route: route('clinic.case-logs'),
                roles: ['clinic'],
            },
            {
                icon: Instructor,
                text: 'Patient History',
                route: route('clinic.patient-history'),
                roles: ['clinic'],
            },
            {
                icon: Reports,
                text: 'Reports',
                route: route('clinic.reports'),
                roles: ['clinic'],
            },
            {
                icon: Trash,
                text: 'Trash',
                roles: ['admin'],
            },
            {
                icon: RFID,
                text: 'RFID',
                route: route('admin.rfid'),
                roles: ['admin'],
            },
        ],
    },
];

const visibleSections = computed(() =>
    sections
        .map((section) => ({
            ...section,
            links: section.links.filter((link) => canSee(link.roles)),
        }))
        .filter((section) => section.links.length > 0),
);
</script>

<template>
    <nav
        class="flex w-[250px] flex-col justify-between bg-white text-default drop-shadow-xl"
    >
        <div class="flex flex-col">
            <template v-for="section in visibleSections" :key="section.title">
                <header class="p-4 text-nav-header">
                    <h1 class="text-[18px]">{{ section.title }}</h1>
                </header>

                <div class="mb-5">
                    <Navlinks
                        v-for="item in section.links"
                        :key="item.text"
                        :icon="item.icon"
                        :text="item.text"
                        :route="item.route"
                    />
                </div>
            </template>
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
