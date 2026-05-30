<template>
  <div class="w-full bg-slate-50">
    <div class="mx-auto max-w-none px-4 py-4">
      <div class="mb-4 rounded-lg bg-white p-5 shadow-sm">
        <h1 class="text-2xl font-bold text-slate-800">Attendance Logs</h1>
        <p class="text-sm text-slate-500">
          {{ canInspectAllAttendance ? 'Filter by subject, attendance date, and instructor RFID.' : 'View attendance records for your assigned classes only.' }}
        </p>
      </div>

      <div class="mb-4 rounded-lg bg-white p-4 shadow-sm">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
          <div class="md:col-span-2">
            <label for="subjectFilter" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Subject</label>
            <select v-model="subjectFilter" id="subjectFilter" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">All Subjects</option>
              <option v-for="subject in subjectOptions" :key="subject.value" :value="String(subject.value)">{{ subject.label }}</option>
            </select>
          </div>

          <div>
            <label for="dateFilter" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Attendance Date</label>
            <select v-model="dateFilter" id="dateFilter" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">All Dates</option>
              <option v-for="date in dateOptions" :key="date.value" :value="date.value">{{ date.label }}</option>
            </select>
          </div>

          <div v-if="canInspectAllAttendance">
            <label for="instructorFilter" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Instructor</label>
            <select v-model="instructorFilter" id="instructorFilter" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" @change="instructorRfidFilter = ''">
              <option value="">All Instructors</option>
              <option v-for="instructor in instructorOptions" :key="instructor.value" :value="String(instructor.value)">
                {{ instructor.label }}
              </option>
            </select>
          </div>
        </div>

        <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-[1fr_auto_auto]">
          <div v-if="canInspectAllAttendance">
            <label for="instructorRfidFilter" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Scan Instructor RFID</label>
            <input
              v-model="instructorRfidFilter"
              id="instructorRfidFilter"
              type="text"
              placeholder="Scan or type instructor RFID, then press Enter"
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              @keyup.enter="applyInstructorRfidFilter"
            />
            <p class="mt-1 text-xs text-slate-400">Admin only: scan an instructor RFID to show only that instructor's sessions.</p>
          </div>
          <div v-else></div>
          <button type="button" class="self-end rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700" @click="applyFilters">
            Apply Filters
          </button>
          <button type="button" class="self-end rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="resetFilters">
            Reset
          </button>
        </div>
      </div>

      <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-4">
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
          <h3 class="text-sm font-semibold text-emerald-800">Present</h3>
          <p class="text-2xl font-bold text-emerald-600">{{ getStatusCount('Present') }}</p>
        </div>
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
          <h3 class="text-sm font-semibold text-amber-800">Late</h3>
          <p class="text-2xl font-bold text-amber-600">{{ getStatusCount('Late') }}</p>
        </div>
        <div class="rounded-lg border border-sky-200 bg-sky-50 p-4">
          <h3 class="text-sm font-semibold text-sky-800">Completed</h3>
          <p class="text-2xl font-bold text-sky-600">{{ getStatusCount('Completed') }}</p>
        </div>
        <div class="rounded-lg border border-blue-200 bg-blue-50 p-4">
          <h3 class="text-sm font-semibold text-blue-800">Total Records</h3>
          <p class="text-2xl font-bold text-blue-600">{{ logs.length }}</p>
        </div>
      </div>

      <div class="space-y-4">
        <section v-for="group in groupedLogs" :key="group.key" class="overflow-hidden rounded-lg bg-white shadow-sm">
          <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 bg-slate-50 px-4 py-3">
            <div>
              <h2 class="text-sm font-bold text-slate-800">{{ group.date }} · {{ group.room }}</h2>
              <p class="text-xs text-slate-500">{{ group.instructor }} · {{ group.items.length }} record(s)</p>
            </div>
            <div class="text-xs text-slate-500">
              Present {{ group.counts.Present ?? 0 }} · Late {{ group.counts.Late ?? 0 }} · Completed {{ group.counts.Completed ?? 0 }}
            </div>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full table-auto border-collapse text-sm">
              <thead>
                <tr class="bg-white">
                  <th class="border-b border-slate-200 px-4 py-3 text-left font-medium text-slate-600">Student</th>
                  <th class="border-b border-slate-200 px-4 py-3 text-left font-medium text-slate-600">Subject</th>
                  <th class="border-b border-slate-200 px-4 py-3 text-left font-medium text-slate-600">Time In</th>
                  <th class="border-b border-slate-200 px-4 py-3 text-left font-medium text-slate-600">Time Out</th>
                  <th class="border-b border-slate-200 px-4 py-3 text-left font-medium text-slate-600">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="log in group.items" :key="log.id" class="hover:bg-slate-50">
                  <td class="border-b border-slate-100 px-4 py-3">{{ log.student }}</td>
                  <td class="border-b border-slate-100 px-4 py-3">{{ log.subject }}</td>
                  <td class="border-b border-slate-100 px-4 py-3">{{ log.time }}</td>
                  <td class="border-b border-slate-100 px-4 py-3">{{ log.time_out || '-' }}</td>
                  <td class="border-b border-slate-100 px-4 py-3">
                    <span :class="statusClass(log.status)">{{ log.status }}</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <div v-if="groupedLogs.length === 0" class="rounded-lg bg-white p-8 text-center text-slate-500 shadow-sm">
          No attendance logs found matching your filters.
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const props = defineProps({
  logs: { type: Array, default: () => [] },
  filters: {
    type: Object,
    default: () => ({
      attendance_id: '',
      date: '',
      subject: '',
      instructor: '',
      instructor_rfid: '',
    }),
  },
  attendanceSessionOptions: { type: Array, default: () => [] },
  subjectOptions: { type: Array, default: () => [] },
  dateOptions: { type: Array, default: () => [] },
  instructorOptions: { type: Array, default: () => [] },
  currentUserRole: { type: String, default: '' },
  canInspectAllAttendance: { type: Boolean, default: false },
})

const page = usePage()
const currentRole = computed(() => String(props.currentUserRole || page.props.auth?.user?.role || '').toLowerCase())
const canInspectAllAttendance = computed(() => props.canInspectAllAttendance || currentRole.value === 'admin')
const logs = computed(() => props.logs)

const dateFilter = ref(props.filters.date ?? '')
const subjectFilter = ref(props.filters.subject ?? '')
const instructorFilter = ref(props.filters.instructor ?? '')
const instructorRfidFilter = ref(props.filters.instructor_rfid ?? '')

const groupedLogs = computed(() => {
  const groups = new Map()

  logs.value.forEach((log) => {
    const date = log.date || 'No date'
    const room = log.room || 'No room'
    const instructor = log.instructor || 'Unassigned Instructor'
    const subject = log.subject || 'N/A'
    const sessionTime = log.session_time || 'N/A'
    const key = `${log.session_id || ''}|${date}|${room}|${instructor}`

    if (!groups.has(key)) {
      groups.set(key, {
        key,
        date,
        room,
        instructor,
        subject,
        sessionTime,
        items: [],
        counts: {},
      })
    }

    const group = groups.get(key)
    group.items.push(log)
    group.counts[log.status] = (group.counts[log.status] ?? 0) + 1
  })

  return Array.from(groups.values())
})

const applyFilters = () => {
  router.get(route('admin.attendance.logs'), {
    attendance_id: '',
    date: dateFilter.value,
    subject: subjectFilter.value,
    instructor: canInspectAllAttendance.value ? instructorFilter.value : '',
    instructor_rfid: canInspectAllAttendance.value ? instructorRfidFilter.value : '',
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

const applyInstructorRfidFilter = () => {
  instructorFilter.value = ''
  applyFilters()
}

const resetFilters = () => {
  dateFilter.value = ''
  subjectFilter.value = ''
  instructorFilter.value = ''
  instructorRfidFilter.value = ''
  applyFilters()
}

const getStatusCount = (status) => logs.value.filter((log) => log.status === status).length

const statusClass = (status) => {
  if (status === 'Present') return 'font-medium text-emerald-600'
  if (status === 'Completed') return 'font-medium text-sky-600'
  if (status === 'Absent') return 'font-medium text-red-600'
  return 'font-medium text-amber-600'
}
</script>
