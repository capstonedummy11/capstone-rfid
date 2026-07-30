<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    Bell,
    FileText,
    MessageSquare,
    MonitorCheck,
    PhoneCall,
    ScanFace,
    Settings,
    ShieldCheck,
    UserRound,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import Navlinks from '@/components/Auth/Navlinks.vue';
import ActivityLogs from '@/components/Icon/ActivityLogs.vue';
import Attendance from '@/components/Icon/Attendance.vue';
import Borrowing from '@/components/Icon/Borrowing.vue';
import Dashboard from '@/components/Icon/Dashboard.vue';
import Graduation from '@/components/Icon/Graduation.vue';
import Instructor from '@/components/Icon/Instructor.vue';
import LogoutIcon from '@/components/Icon/LogoutIcon.vue';
import Reports from '@/components/Icon/Reports.vue';
// import RFID from '@/components/Icon/RFID.vue';
import Schedule from '@/components/Icon/Schedule.vue';
import Section from '@/components/Icon/Section.vue';
import Inventory from '@/components/Icon/Inventory.vue';

const isNavOpen = defineModel('isNavOpen', { default: true });
const props = defineProps({
    unreadMessageCount: { type: Number, default: 0 },
});

const page = usePage();
const currentRole = computed(() =>
    String(page.props.auth?.user?.role ?? '').toLowerCase(),
);
const featureSettings = computed(() => page.props.featureSettings ?? {});
const canSee = (roles) => roles.includes(currentRole.value);
const isFeatureVisible = (featureKey) =>
    !featureKey || Boolean(featureSettings.value?.[featureKey]);

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
                icon: Dashboard,
                text: 'Registrar Dashboard',
                route: route('registrar.dashboard'),
                roles: ['registrar'],
            },
            // {
            //     icon: Graduation,
            //     text: 'School Year',
            //     roles: ['admin'],
            // },
            {
                icon: Graduation,
                text: 'Strands',
                route: route('admin.strands.index'),
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
                icon: ShieldCheck,
                text: 'User Management',
                route: route('admin.users.index'),
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
                route: route('admin.attendance.logs'),
                roles: ['admin', 'instructor'],
            },
            {
                icon: MessageSquare,
                text: 'Messages',
                route: route('messages.index'),
                roles: ['admin', 'instructor', 'clinic', 'registrar'],
            },
            {
                icon: MonitorCheck,
                text: 'Online Classes',
                route: route('admin.online-classes.index'),
                roles: ['admin', 'instructor'],
            },
        ],
    },
    {
        title: 'Student Portal',
        links: [
            {
                icon: Dashboard,
                text: 'My Dashboard',
                route: route('student-parent.dashboard'),
                roles: ['student', 'parent'],
            },
            {
                icon: UserRound,
                text: 'My Profile',
                route: route('student-parent.profile.show'),
                roles: ['student', 'parent'],
            },
            {
                icon: Attendance,
                text: 'My Attendance',
                route: route('student-parent.attendance'),
                roles: ['student', 'parent'],
            },
            {
                icon: MonitorCheck,
                text: 'Online Classes',
                route: route('student-parent.online-classes.index'),
                roles: ['student', 'parent'],
            },
            {
                icon: FileText,
                text: 'Excuse Letters',
                route: route('student-parent.excuse-letters.index'),
                roles: ['student', 'parent'],
            },
            {
                icon: MessageSquare,
                text: 'Messages',
                route: route('messages.index'),
                roles: ['student', 'parent'],
            },
            {
                icon: Bell,
                text: 'Notifications',
                route: route('student-parent.notifications.index'),
                roles: ['student', 'parent'],
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
                feature: 'borrowing_enabled',
            },
            {
                icon: Inventory,
                text: 'Inventory',
                route: route('admin.inventory'),
                roles: ['admin'],
                feature: 'inventory_enabled',
            },
            {
                icon: Reports,
                text: 'Reports',
                route: route('reports.index'),
                roles: ['admin', 'instructor', 'clinic', 'registrar'],
            },
            {
                icon: ActivityLogs,
                text: 'Activity Logs',
                route: route('admin.activity-logs.index'),
                roles: ['admin'],
            },
            {
                icon: ActivityLogs,
                text: 'Online Class Logs',
                route: route('admin.online-class-logs.index'),
                roles: ['admin'],
            },
            {
                icon: MonitorCheck,
                text: 'Laboratories & Devices',
                route: route('admin.active-devices.index'),
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
                icon: PhoneCall,
                text: 'Emergency Hotlines',
                route: route('clinic.emergency-hotlines.index'),
                roles: ['clinic'],
            },
            // RFID assignment is handled inside Student and Instructor
            // Management, so the standalone admin navigation item is hidden.
            // {
            //     icon: RFID,
            //     text: 'RFID',
            //     route: route('admin.rfid'),
            //     roles: ['admin'],
            // },
            {
                icon: Settings,
                text: 'Settings',
                route: route('admin.settings.edit'),
                roles: ['admin'],
            },
            {
                icon: Graduation,
                text: 'Student Biometric Enrollment',
                route: route('registrar.biometric-enrollment'),
                roles: ['registrar'],
            },
            {
                icon: ScanFace,
                text: 'Instructor Faces',
                route: route('registrar.instructor-face-enrollment'),
                roles: ['registrar'],
            },
        ],
    },
];

const visibleSections = computed(() =>
    sections
        .map((section) => ({
            ...section,
            links: section.links.filter(
                (link) => canSee(link.roles) && isFeatureVisible(link.feature),
            ),
        }))
        .filter((section) => section.links.length > 0),
);

const confirmLogout = async () => {
    const result = await Swal.fire({
        title: 'Log out?',
        text: 'You will be returned to the landing page.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, log out',
        cancelButtonText: 'Stay signed in',
        confirmButtonColor: '#2563eb',
        cancelButtonColor: '#64748b',
    });

    if (!result.isConfirmed) {
        return;
    }

    router.post(route('logout'));
};
</script>

<template>
    <nav
        class="flex h-screen shrink-0 flex-col bg-white text-default drop-shadow-xl"
        :class="isNavOpen ? 'w-[250px]' : 'w-0'"
    >
        <div class="min-h-0 flex-1 overflow-y-auto">
            <template v-for="section in visibleSections" :key="section.title">
                <header class="flex justify-between p-4 text-nav-header">
                    <h1 class="text-[18px]">{{ section.title }}</h1>
                </header>

                <div class="mb-5">
                    <Navlinks
                        v-for="item in section.links"
                        :key="item.text"
                        :icon="item.icon"
                        :text="isNavOpen ? item.text : ''"
                        :route="item.route"
                        :badge="
                            item.text === 'Messages'
                                ? props.unreadMessageCount
                                : 0
                        "
                        @click="isNavOpen = false"
                    />
                </div>
            </template>
        </div>

        <!-- Logout Button -->
        <button
            v-if="isNavOpen"
            type="button"
            @click="confirmLogout"
            class="auth-nav-link group w-full shrink-0 cursor-pointer border-t-2 text-left"
        >
            <LogoutIcon class="text-[#A3AED0] group-hover:text-brand" />
            <h1>Logout</h1>
        </button>
    </nav>
</template>
