<template>
  <div class="w-full">
    <div class="max-w-[1400px] mx-auto px-4">
      <!-- Header -->
      <div class="bg-white shadow-lg rounded-lg p-6 mb-6">
        <h1 class="text-3xl font-bold mb-2">RFID Attendance Scanner</h1>
        <div class="flex justify-between items-center text-gray-600">
          <div>{{ currentDate }}</div>
          <div>{{ currentTime }}</div>
          <div>System Status: Online</div>
        </div>
      </div>

      <!-- Attendance Session Status -->
      <div class="bg-white shadow-lg rounded-lg p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Attendance Session Status</h2>
        <div v-if="!sessionActive" class="text-center">
          <p class="text-gray-600 text-lg">Waiting for Instructor RFID...</p>
          <p class="text-sm">Tap Instructor Card to Start Class</p>
        </div>
        <div v-else class="space-y-2">
          <p><strong>Instructor:</strong> {{ instructor.name }}</p>
          <p><strong>Subject:</strong> {{ instructor.subject }}</p>
          <p><strong>Section:</strong> {{ instructor.section }}</p>
          <p><strong>Time:</strong> {{ instructor.time }}</p>
        </div>
      </div>

      <!-- RFID Scanner Input -->
      <div class="bg-white shadow-lg rounded-lg p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">RFID Scanner Input</h2>
        <div class="text-center">
          <button @click="simulateScan" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg text-lg font-medium transition-colors">
            Tap RFID Card
          </button>
        </div>
      </div>

      <!-- Scan Result Display -->
      <div class="bg-white shadow-lg rounded-lg p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Scan Result Display</h2>
        <div v-if="lastScan" class="text-center">
          <p :class="lastScan.success ? 'text-green-600 text-xl font-medium' : 'text-red-600 text-xl font-medium'">
            {{ lastScan.message }}
          </p>
          <p v-if="lastScan.time" class="text-gray-600 mt-2">Time: {{ lastScan.time }}</p>
        </div>
        <div v-else class="text-center text-gray-500">
          <p>No recent scans</p>
        </div>
      </div>

      <!-- Live Attendance List -->
      <div class="bg-white shadow-lg rounded-lg p-6">
        <h2 class="text-xl font-semibold mb-4">Live Attendance List</h2>
        <div class="overflow-x-auto">
          <table class="w-full table-auto border-collapse">
            <thead>
              <tr class="bg-gray-50">
                <th class="border border-gray-300 px-4 py-3 text-left font-medium">Student Name</th>
                <th class="border border-gray-300 px-4 py-3 text-left font-medium">Time</th>
                <th class="border border-gray-300 px-4 py-3 text-left font-medium">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="student in attendanceList" :key="student.name" class="hover:bg-gray-50">
                <td class="border border-gray-300 px-4 py-3">{{ student.name }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ student.time }}</td>
                <td class="border border-gray-300 px-4 py-3">
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                    {{ student.status }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const currentDate = ref('')
const currentTime = ref('')
const sessionActive = ref(false)
const instructor = ref({
  name: 'Mr. Santos',
  subject: 'Database Systems',
  section: 'BSIT 3A',
  time: '8:00 - 10:00'
})
const lastScan = ref(null)
const attendanceList = ref([
  { name: 'Juan Cruz', time: '8:01', status: 'Present' },
  { name: 'Maria Santos', time: '8:03', status: 'Present' }
])

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
  currentDate.value = now.toLocaleDateString('en-US', { weekday: 'long' })
  currentTime.value = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })
}

const simulateScan = () => {
  const success = Math.random() > 0.5
  const now = new Date()
  const time = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })

  if (success) {
    const name = 'Student ' + Math.floor(Math.random() * 100)
    lastScan.value = { success: true, message: `✔ ${name} Attendance Recorded`, time }
    attendanceList.value.push({ name, time, status: 'Present' })
  } else {
    lastScan.value = { success: false, message: 'RFID Not Registered Please register card', time }
  }
}
</script>

<style scoped>
/* Add any custom styles if needed */
</style>