<template>
  <div class="w-full">
    <div class="max-w-[1400px] mx-auto px-4">
      <!-- Header -->
      <div class="bg-white shadow-lg rounded-lg p-6 mb-6">
        <h1 class="text-3xl font-bold">Attendance Logs</h1>
        <p class="text-gray-600">View and filter attendance records</p>
      </div>

      <!-- Filters -->
      <div class="bg-white shadow-lg rounded-lg p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label for="dateFilter" class="block text-sm font-medium text-gray-700 mb-1">Date</label>
            <input
              v-model="dateFilter"
              type="date"
              id="dateFilter"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          <div>
            <label for="subjectFilter" class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
            <select
              v-model="subjectFilter"
              id="subjectFilter"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">All Subjects</option>
              <option value="Programming 2">Programming 2</option>
              <option value="Database Systems">Database Systems</option>
              <option value="Web Development">Web Development</option>
            </select>
          </div>
          <div>
            <label for="sectionFilter" class="block text-sm font-medium text-gray-700 mb-1">Section</label>
            <select
              v-model="sectionFilter"
              id="sectionFilter"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">All Sections</option>
              <option value="BSIT 3A">BSIT 3A</option>
              <option value="BSCS 2A">BSCS 2A</option>
            </select>
          </div>
          <div>
            <label for="instructorFilter" class="block text-sm font-medium text-gray-700 mb-1">Instructor</label>
            <select
              v-model="instructorFilter"
              id="instructorFilter"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">All Instructors</option>
              <option value="Mr. Cruz">Mr. Cruz</option>
              <option value="Mr. Santos">Mr. Santos</option>
              <option value="Ms. Garcia">Ms. Garcia</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Attendance Table -->
      <div class="bg-white shadow-lg rounded-lg p-6">
        <div class="overflow-x-auto">
          <table class="w-full table-auto border-collapse">
            <thead>
              <tr class="bg-gray-50">
                <th class="border border-gray-300 px-4 py-3 text-left font-medium">Student</th>
                <th class="border border-gray-300 px-4 py-3 text-left font-medium">Subject</th>
                <th class="border border-gray-300 px-4 py-3 text-left font-medium">Date</th>
                <th class="border border-gray-300 px-4 py-3 text-left font-medium">Time</th>
                <th class="border border-gray-300 px-4 py-3 text-left font-medium">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="log in filteredLogs" :key="log.id" class="hover:bg-gray-50">
                <td class="border border-gray-300 px-4 py-3">{{ log.student }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ log.subject }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ log.date }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ log.time }}</td>
                <td class="border border-gray-300 px-4 py-3">
                  <span :class="log.status === 'Present' ? 'text-green-600' : 'text-red-600'">
                    {{ log.status }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- No logs message -->
        <div v-if="filteredLogs.length === 0" class="text-center py-8 text-gray-500">
          <p>No attendance logs found matching your criteria.</p>
        </div>

        <!-- Summary -->
        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="bg-green-50 border border-green-200 rounded-lg p-4">
            <h3 class="text-lg font-semibold text-green-800">Present</h3>
            <p class="text-2xl font-bold text-green-600">{{ getStatusCount('Present') }}</p>
          </div>
          <div class="bg-red-50 border border-red-200 rounded-lg p-4">
            <h3 class="text-lg font-semibold text-red-800">Absent</h3>
            <p class="text-2xl font-bold text-red-600">{{ getStatusCount('Absent') }}</p>
          </div>
          <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h3 class="text-lg font-semibold text-blue-800">Total Records</h3>
            <p class="text-2xl font-bold text-blue-600">{{ filteredLogs.length }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const dateFilter = ref('')
const subjectFilter = ref('')
const sectionFilter = ref('')
const instructorFilter = ref('')

const attendanceLogs = ref([
  {
    id: 1,
    student: 'Maria Santos',
    subject: 'Programming 2',
    section: 'BSIT 3A',
    instructor: 'Mr. Cruz',
    date: '2024-10-01',
    time: '8:01',
    status: 'Present'
  },
  {
    id: 2,
    student: 'Juan Cruz',
    subject: 'Programming 2',
    section: 'BSIT 3A',
    instructor: 'Mr. Cruz',
    date: '2024-10-01',
    time: '8:02',
    status: 'Present'
  },
  {
    id: 3,
    student: 'Pedro Reyes',
    subject: 'Programming 2',
    section: 'BSIT 3A',
    instructor: 'Mr. Cruz',
    date: '2024-10-01',
    time: '8:05',
    status: 'Present'
  },
  {
    id: 4,
    student: 'Maria Santos',
    subject: 'Database Systems',
    section: 'BSIT 3A',
    instructor: 'Mr. Santos',
    date: '2024-10-02',
    time: '1:01',
    status: 'Present'
  },
  {
    id: 5,
    student: 'Juan Cruz',
    subject: 'Database Systems',
    section: 'BSIT 3A',
    instructor: 'Mr. Santos',
    date: '2024-10-02',
    time: '1:03',
    status: 'Absent'
  },
  {
    id: 6,
    student: 'Ana Lopez',
    subject: 'Web Development',
    section: 'BSCS 2A',
    instructor: 'Ms. Garcia',
    date: '2024-10-03',
    time: '10:01',
    status: 'Present'
  },
  {
    id: 7,
    student: 'Carlos Mendoza',
    subject: 'Web Development',
    section: 'BSCS 2A',
    instructor: 'Ms. Garcia',
    date: '2024-10-03',
    time: '10:02',
    status: 'Present'
  }
])

const filteredLogs = computed(() => {
  return attendanceLogs.value.filter(log => {
    const matchesDate = !dateFilter.value || log.date === dateFilter.value
    const matchesSubject = !subjectFilter.value || log.subject === subjectFilter.value
    const matchesSection = !sectionFilter.value || log.section === sectionFilter.value
    const matchesInstructor = !instructorFilter.value || log.instructor === instructorFilter.value

    return matchesDate && matchesSubject && matchesSection && matchesInstructor
  })
})

const getStatusCount = (status) => {
  return filteredLogs.value.filter(log => log.status === status).length
}
</script>

<style scoped>
/* Add any custom styles if needed */
</style>