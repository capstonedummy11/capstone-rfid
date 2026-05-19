<template>
  <div class="w-full">
    <div class="mx-auto max-w-screen-2xl px-4 py-6">
      <div class="mb-6 rounded-lg bg-white p-6 shadow-lg">
        <h1 class="text-3xl font-bold">Attendance Logs</h1>
        <p class="text-gray-600">{{ canInspectAllAttendance ? 'View and filter all attendance records.' : 'View attendance records for your handled sections.' }}</p>
      </div>

      <div class="mb-6 rounded-lg bg-white p-6 shadow-lg">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-5">
          <div>
            <label for="dateFilter" class="mb-1 block text-sm font-medium text-gray-700">Date</label>
            <input v-model="dateFilter" type="date" id="dateFilter" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" @change="applyFilters" />
          </div>
          <div>
            <label for="subjectFilter" class="mb-1 block text-sm font-medium text-gray-700">Subject</label>
            <select v-model="subjectFilter" id="subjectFilter" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" @change="applyFilters">
              <option value="">All Subjects</option>
              <option v-for="subject in subjectOptions" :key="subject.value" :value="String(subject.value)">{{ subject.label }}</option>
            </select>
          </div>
          <div>
            <label for="sectionFilter" class="mb-1 block text-sm font-medium text-gray-700">Section</label>
            <select v-model="sectionFilter" id="sectionFilter" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" @change="applyFilters">
              <option value="">{{ canInspectAllAttendance ? 'All Sections' : 'Select Section' }}</option>
              <option v-for="section in sectionOptions" :key="section.value" :value="String(section.value)">{{ section.label }}</option>
            </select>
          </div>
          <div>
            <label for="schoolYearFilter" class="mb-1 block text-sm font-medium text-gray-700">School Year</label>
            <select v-model="schoolYearFilter" id="schoolYearFilter" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" @change="applyFilters">
              <option value="">All School Years</option>
              <option v-for="schoolYear in schoolYearOptions" :key="schoolYear" :value="schoolYear">{{ schoolYear }}</option>
            </select>
          </div>
          <div v-if="canInspectAllAttendance">
            <label for="instructorFilter" class="mb-1 block text-sm font-medium text-gray-700">Instructor</label>
            <select v-model="instructorFilter" id="instructorFilter" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" @change="applyFilters">
              <option value="">All Instructors</option>
              <option v-for="instructor in instructorOptions" :key="instructor.value" :value="String(instructor.value)">{{ instructor.label }}</option>
            </select>
          </div>
        </div>
      </div>

      <div class="rounded-lg bg-white p-6 shadow-lg">
        <div class="overflow-x-auto">
          <table class="w-full table-auto border-collapse">
            <thead>
              <tr class="bg-gray-50">
                <th class="border border-gray-300 px-4 py-3 text-left font-medium">Student</th>
                <th class="border border-gray-300 px-4 py-3 text-left font-medium">Subject</th>
                <th class="border border-gray-300 px-4 py-3 text-left font-medium">Section</th>
                <th class="border border-gray-300 px-4 py-3 text-left font-medium">School Year</th>
                <th class="border border-gray-300 px-4 py-3 text-left font-medium">Instructor</th>
                <th class="border border-gray-300 px-4 py-3 text-left font-medium">Date</th>
                <th class="border border-gray-300 px-4 py-3 text-left font-medium">Time</th>
                <th class="border border-gray-300 px-4 py-3 text-left font-medium">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="log in filteredLogs" :key="log.id" class="hover:bg-gray-50">
                <td class="border border-gray-300 px-4 py-3">{{ log.student }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ log.subject }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ log.section }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ log.school_year ?? 'N/A' }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ log.instructor }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ log.date }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ log.time }}</td>
                <td class="border border-gray-300 px-4 py-3">
                  <span :class="log.status === 'Present' ? 'text-green-600' : log.status === 'Absent' ? 'text-red-600' : 'text-amber-600'">{{ log.status }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="filteredLogs.length === 0" class="py-8 text-center text-gray-500">
          <p>No attendance logs found matching your criteria.</p>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-3">
          <div class="rounded-lg border border-green-200 bg-green-50 p-4">
            <h3 class="text-lg font-semibold text-green-800">Present</h3>
            <p class="text-2xl font-bold text-green-600">{{ getStatusCount('Present') }}</p>
          </div>
          <div class="rounded-lg border border-red-200 bg-red-50 p-4">
            <h3 class="text-lg font-semibold text-red-800">Absent</h3>
            <p class="text-2xl font-bold text-red-600">{{ getStatusCount('Absent') }}</p>
          </div>
          <div class="rounded-lg border border-blue-200 bg-blue-50 p-4">
            <h3 class="text-lg font-semibold text-blue-800">Total Records</h3>
            <p class="text-2xl font-bold text-blue-600">{{ filteredLogs.length }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const props = defineProps({
  logs: {
    type: Array,
    default: () => [],
  },
  filters: {
    type: Object,
    default: () => ({ date: '', subject: '', section: '', school_year: '', instructor: '' }),
  },
  subjectOptions: {
    type: Array,
    default: () => [],
  },
  sectionOptions: {
    type: Array,
    default: () => [],
  },
  instructorOptions: {
    type: Array,
    default: () => [],
  },
  schoolYearOptions: {
    type: Array,
    default: () => [],
  },
  currentUserRole: {
    type: String,
    default: '',
  },
  canInspectAllAttendance: {
    type: Boolean,
    default: false,
  },
})

const page = usePage()
const currentRole = computed(() => String(props.currentUserRole || page.props.auth?.user?.role || '').toLowerCase())
const canInspectAllAttendance = computed(() => props.canInspectAllAttendance || currentRole.value === 'admin')
const dateFilter = ref(props.filters.date ?? '')
const subjectFilter = ref(props.filters.subject ?? '')
const sectionFilter = ref(props.filters.section ?? '')
const schoolYearFilter = ref(props.filters.school_year ?? '')
const instructorFilter = ref(props.filters.instructor ?? '')

const filteredLogs = computed(() => {
  return props.logs.filter((log) => {
    const matchesDate = !dateFilter.value || log.date === dateFilter.value
    const matchesSubject = !subjectFilter.value || String(log.subject ?? '').length > 0
    const matchesSection = !sectionFilter.value || String(log.section ?? '').length > 0
    const matchesSchoolYear = !schoolYearFilter.value || log.school_year === schoolYearFilter.value
    const matchesInstructor = !canInspectAllAttendance.value || !instructorFilter.value || String(log.instructor ?? '').length > 0

    return matchesDate && matchesSubject && matchesSection && matchesSchoolYear && matchesInstructor
  })
})

const applyFilters = () => {
  router.get(route('admin.attendance.logs'), {
    date: dateFilter.value,
    subject: subjectFilter.value,
    section: sectionFilter.value,
    school_year: schoolYearFilter.value,
    instructor: canInspectAllAttendance.value ? instructorFilter.value : '',
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

const getStatusCount = (status) => filteredLogs.value.filter((log) => log.status === status).length
</script>
