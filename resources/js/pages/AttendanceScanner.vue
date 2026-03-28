<template>
  <div class="w-full">
    <div class="mx-auto max-w-screen-2xl px-4 py-6">
      <div class="mb-6 rounded-lg bg-white p-6 shadow-lg">
        <h1 class="mb-2 text-3xl font-bold">RFID Attendance Scanner</h1>
        <div class="flex flex-wrap items-center justify-between gap-3 text-gray-600">
          <div>{{ currentDate }}</div>
          <div>{{ currentTime }}</div>
          <div>{{ session ? 'Schedule loaded' : 'No active schedule' }}</div>
        </div>
      </div>

      <div class="mb-6 grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
        <section class="rounded-lg bg-white p-6 shadow-lg">
          <h2 class="mb-4 text-xl font-semibold">Attendance Session Status</h2>
          <div v-if="!session" class="space-y-2 text-center text-gray-600">
            <p class="text-lg">No schedule is available yet.</p>
            <p class="text-sm">Create a subject and schedule to populate this scanner view.</p>
          </div>
          <div v-else class="grid gap-3 md:grid-cols-2">
            <p><strong>Instructor:</strong> {{ session.instructor }}</p>
            <p><strong>Subject:</strong> {{ session.subject }}</p>
            <p><strong>Subject Code:</strong> {{ session.subjectCode ?? 'N/A' }}</p>
            <p><strong>Section:</strong> {{ session.section }}</p>
            <p><strong>Track:</strong> {{ session.strand ?? 'N/A' }}</p>
            <p><strong>Program:</strong> {{ session.strand ?? 'N/A' }}</p>
            <p><strong>Schedule:</strong> {{ session.weekdays }}</p>
            <p><strong>Time:</strong> {{ session.time }}</p>
            <p class="md:col-span-2"><strong>Room:</strong> {{ session.room }}</p>
          </div>
        </section>

        <section class="rounded-lg bg-white p-6 shadow-lg">
          <h2 class="mb-4 text-xl font-semibold">Scanner Input</h2>
          <p class="mb-4 text-sm text-gray-600">This page now reads real students and recent logs from the database. The button below still performs a local demo tap so you can test the UI without a hardware listener.</p>
          <div class="flex flex-col items-center gap-4 text-center">
            <button @click="simulateScan" class="rounded-lg bg-blue-600 px-6 py-3 text-lg font-medium text-white transition-colors hover:bg-blue-700">Simulate RFID Tap</button>
            <p class="text-sm text-gray-500">Registered RFID students: {{ registeredStudents.length }}</p>
          </div>
        </section>
      </div>

      <div class="mb-6 rounded-lg bg-white p-6 shadow-lg">
        <h2 class="mb-4 text-xl font-semibold">Scan Result Display</h2>
        <div v-if="lastScan" class="text-center">
          <p :class="lastScan.success ? 'text-xl font-medium text-green-600' : 'text-xl font-medium text-red-600'">{{ lastScan.message }}</p>
          <p v-if="lastScan.time" class="mt-2 text-gray-600">Time: {{ lastScan.time }}</p>
        </div>
        <div v-else class="text-center text-gray-500">
          <p>No recent local scans</p>
        </div>
      </div>

      <div class="rounded-lg bg-white p-6 shadow-lg">
        <h2 class="mb-4 text-xl font-semibold">Recent Attendance</h2>
        <div class="overflow-x-auto">
          <table class="w-full table-auto border-collapse">
            <thead>
              <tr class="bg-gray-50">
                <th class="border border-gray-300 px-4 py-3 text-left font-medium">Student Name</th>
                <th class="border border-gray-300 px-4 py-3 text-left font-medium">Student No.</th>
                <th class="border border-gray-300 px-4 py-3 text-left font-medium">Subject</th>
                <th class="border border-gray-300 px-4 py-3 text-left font-medium">Section</th>
                <th class="border border-gray-300 px-4 py-3 text-left font-medium">Time</th>
                <th class="border border-gray-300 px-4 py-3 text-left font-medium">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="student in attendanceList" :key="student.id" class="hover:bg-gray-50">
                <td class="border border-gray-300 px-4 py-3">{{ student.student }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ student.studentNumber ?? 'N/A' }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ student.subject ?? 'N/A' }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ student.section ?? 'N/A' }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ student.time ?? 'N/A' }}</td>
                <td class="border border-gray-300 px-4 py-3">
                  <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">{{ student.status }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="attendanceList.length === 0" class="py-8 text-center text-gray-500">
          <p>No attendance logs found.</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, onUnmounted, ref } from 'vue'

const props = defineProps({
  session: {
    type: Object,
    default: null,
  },
  recentScans: {
    type: Array,
    default: () => [],
  },
  registeredStudents: {
    type: Array,
    default: () => [],
  },
})

const currentDate = ref('')
const currentTime = ref('')
const lastScan = ref(null)
const attendanceList = ref([...props.recentScans])

let timer

onMounted(() => {
  updateDateTime()
  timer = setInterval(updateDateTime, 1000)
})

onUnmounted(() => {
  clearInterval(timer)
})

const updateDateTime = () => {
  const now = new Date()
  currentDate.value = now.toLocaleDateString('en-US', {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
    year: 'numeric',
  })
  currentTime.value = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })
}

const simulateScan = () => {
  const now = new Date()
  const time = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })

  if (props.registeredStudents.length === 0) {
    lastScan.value = {
      success: false,
      message: 'No registered RFID students found in the database.',
      time,
    }
    return
  }

  const nextStudent = props.registeredStudents[Math.floor(Math.random() * props.registeredStudents.length)]
  const nextLog = {
    id: `demo-${Date.now()}`,
    student: nextStudent.name,
    studentNumber: nextStudent.studentId,
    subject: props.session?.subject ?? 'Unassigned Subject',
    section: nextStudent.section ?? props.session?.section ?? 'N/A',
    time,
    status: 'Present',
  }

  lastScan.value = {
    success: true,
    message: `Attendance recorded for ${nextStudent.name}`,
    time,
  }

  attendanceList.value = [nextLog, ...attendanceList.value].slice(0, 20)
}
</script>