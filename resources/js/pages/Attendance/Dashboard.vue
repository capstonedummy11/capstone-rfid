<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    subject: { type: Object, required: true },
    overview: { type: Object, required: true },
    sessions: { type: Array, default: () => [] },
    currentUserRole: { type: String, required: true },
});
</script>

<template>
    <Head :title="`${subject.name} Attendance`" />
    <main class="min-h-screen bg-slate-50 px-4 py-6 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl">
            <Link
                :href="route('admin.attendance.logs')"
                class="text-sm font-bold text-blue-700 hover:text-blue-900"
                >← All subjects</Link
            >
            <header
                class="mt-4 rounded-3xl bg-gradient-to-r from-slate-950 via-blue-950 to-blue-700 p-7 text-white shadow-xl sm:p-9"
            >
                <div class="flex flex-wrap items-end justify-between gap-5">
                    <div>
                        <p
                            class="text-xs font-black tracking-[0.2em] text-blue-200 uppercase"
                        >
                            {{ subject.code }} · {{ subject.section }}
                        </p>
                        <h1 class="mt-2 text-3xl font-black">
                            {{ subject.name }}
                        </h1>
                        <p class="mt-2 text-sm text-blue-100">
                            {{ subject.instructor }} ·
                            {{ subject.school_year }} · {{ subject.semester }}
                        </p>
                    </div>
                    <div
                        class="rounded-2xl bg-white/10 px-5 py-3 text-right ring-1 ring-white/20 backdrop-blur"
                    >
                        <p class="text-xs text-blue-200">Attendance sessions</p>
                        <p class="text-3xl font-black">
                            {{ overview.total_sessions }}
                        </p>
                    </div>
                </div>
            </header>

            <section class="mt-6">
                <div class="mb-3">
                    <h2 class="text-lg font-black text-slate-900">
                        Overall attendance overview
                    </h2>
                    <p class="text-sm text-slate-500">
                        Cumulative totals for this subject.
                    </p>
                </div>
                <div
                    class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-6"
                >
                    <div
                        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                    >
                        <p class="text-xs font-bold text-slate-500 uppercase">
                            Students
                        </p>
                        <p class="mt-2 text-3xl font-black text-slate-900">
                            {{ overview.total_students }}
                        </p>
                    </div>
                    <div
                        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                    >
                        <p class="text-xs font-bold text-slate-500 uppercase">
                            Sessions
                        </p>
                        <p class="mt-2 text-3xl font-black text-slate-900">
                            {{ overview.total_sessions }}
                        </p>
                    </div>
                    <div
                        v-for="(count, status) in overview.statuses"
                        :key="status"
                        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                    >
                        <p
                            class="truncate text-xs font-bold text-slate-500 uppercase"
                        >
                            {{ status }}
                        </p>
                        <p class="mt-2 text-3xl font-black text-blue-700">
                            {{ count }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="mt-8">
                <h2 class="text-lg font-black text-slate-900">
                    Attendance analytics and sessions
                </h2>
                <p class="text-sm text-slate-500">
                    Start with the cumulative student summary or open a specific
                    attendance sheet.
                </p>
                <div class="mt-4 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    <Link
                        :href="route('admin.attendance.summary', subject.id)"
                        class="group min-h-52 rounded-2xl bg-gradient-to-br from-blue-700 to-cyan-500 p-6 text-white shadow-lg transition hover:-translate-y-1 hover:shadow-xl"
                    >
                        <div class="flex h-full flex-col justify-between">
                            <span class="text-4xl">👥</span>
                            <div>
                                <p
                                    class="text-xs font-bold tracking-[0.18em] text-blue-100 uppercase"
                                >
                                    Primary analytics
                                </p>
                                <h3 class="mt-2 text-2xl font-black">
                                    Student Attendance Summary
                                </h3>
                                <p class="mt-2 text-sm text-blue-50">
                                    Compare every student's cumulative
                                    attendance and drill into complete history.
                                </p>
                            </div>
                        </div>
                    </Link>
                    <Link
                        v-for="session in sessions"
                        :key="session.id"
                        :href="
                            route('admin.attendance.session', [
                                subject.id,
                                session.id,
                            ])
                        "
                        class="group min-h-52 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:border-blue-300 hover:shadow-xl"
                    >
                        <div class="flex h-full flex-col justify-between">
                            <div class="flex items-start justify-between">
                                <span class="text-4xl">📄</span>
                                <span
                                    class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600"
                                    >{{ session.completion }}% complete</span
                                >
                            </div>
                            <div>
                                <p
                                    class="text-xs font-bold tracking-wide text-blue-700 uppercase"
                                >
                                    Attendance session
                                </p>
                                <h3
                                    class="mt-2 text-xl font-black text-slate-900"
                                >
                                    {{ session.date_label }}
                                </h3>
                                <p class="mt-2 text-sm text-slate-500">
                                    {{ session.schedule }} ·
                                    {{ subject.section }}
                                </p>
                                <p class="mt-1 text-xs text-slate-400">
                                    {{ session.total_students }} students ·
                                    {{ session.room }}
                                </p>
                            </div>
                        </div>
                    </Link>
                </div>
            </section>
        </div>
    </main>
</template>
