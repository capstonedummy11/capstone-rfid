<template>
  <div class="w-full">
    <div class="max-w-[1400px] mx-auto px-4">
      <!-- Header -->
      <div class="bg-white shadow-lg rounded-lg p-6 mb-6">
        <h1 class="text-3xl font-bold mb-2">RFID Registration</h1>
        <p class="text-gray-600">Register new RFID cards for users</p>
      </div>

      <!-- RFID Detection -->
      <div class="bg-white shadow-lg rounded-lg p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">RFID Detection</h2>
        <div class="text-center">
          <div v-if="detectedRFID" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <p class="font-medium">RFID Detected!</p>
            <p>RFID UID: {{ detectedRFID }}</p>
          </div>
          <button v-else @click="simulateRFIDDetection" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg text-lg font-medium transition-colors">
            Detect RFID Card
          </button>
        </div>
      </div>

      <!-- User Information Form -->
      <div class="bg-white shadow-lg rounded-lg p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">User Information</h2>
        <form @submit.prevent="registerRFID" class="space-y-4">
          <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
            <input
              v-model="form.name"
              type="text"
              id="name"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Enter full name"
            />
          </div>

          <div>
            <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
            <select
              v-model="form.role"
              id="role"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">Select Role</option>
              <option value="student">Student</option>
              <option value="instructor">Instructor</option>
            </select>
          </div>

          <!-- Student Information -->
          <div v-if="form.role === 'student'" class="space-y-4 border-t pt-4">
            <h3 class="text-lg font-medium text-gray-900">Student Information</h3>

            <div>
              <label for="studentId" class="block text-sm font-medium text-gray-700 mb-1">Student ID</label>
              <input
                v-model="form.studentId"
                type="text"
                id="studentId"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Enter student ID"
              />
            </div>

            <div>
              <label for="course" class="block text-sm font-medium text-gray-700 mb-1">Course</label>
              <input
                v-model="form.course"
                type="text"
                id="course"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="e.g., BSIT, BSCS"
              />
            </div>

            <div>
              <label for="yearLevel" class="block text-sm font-medium text-gray-700 mb-1">Year Level</label>
              <select
                v-model="form.yearLevel"
                id="yearLevel"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="">Select Year Level</option>
                <option value="1">1st Year</option>
                <option value="2">2nd Year</option>
                <option value="3">3rd Year</option>
                <option value="4">4th Year</option>
              </select>
            </div>

            <div>
              <label for="section" class="block text-sm font-medium text-gray-700 mb-1">Section</label>
              <input
                v-model="form.section"
                type="text"
                id="section"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="e.g., 3A, 2B"
              />
            </div>
          </div>

          <!-- Instructor Information -->
          <div v-if="form.role === 'instructor'" class="space-y-4 border-t pt-4">
            <h3 class="text-lg font-medium text-gray-900">Instructor Information</h3>

            <div>
              <label for="department" class="block text-sm font-medium text-gray-700 mb-1">Department</label>
              <input
                v-model="form.department"
                type="text"
                id="department"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="e.g., Computer Science, Information Technology"
              />
            </div>

            <div>
              <label for="subjects" class="block text-sm font-medium text-gray-700 mb-1">Subjects</label>
              <textarea
                v-model="form.subjects"
                id="subjects"
                rows="3"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="List subjects separated by commas (e.g., Database Systems, Web Development)"
              ></textarea>
            </div>

            <div>
              <label for="schedule" class="block text-sm font-medium text-gray-700 mb-1">Schedule</label>
              <textarea
                v-model="form.schedule"
                id="schedule"
                rows="3"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Enter schedule details (e.g., Mon/Wed 8:00-10:00, Tue/Thu 1:00-3:00)"
              ></textarea>
            </div>
          </div>

          <!-- Buttons -->
          <div class="flex space-x-4 pt-4">
            <button
              type="submit"
              :disabled="!detectedRFID || !form.name || !form.role"
              class="flex-1 bg-green-500 hover:bg-green-600 disabled:bg-gray-300 disabled:cursor-not-allowed text-white px-6 py-3 rounded-lg text-lg font-medium transition-colors"
            >
              Register RFID
            </button>
            <button
              type="button"
              @click="cancelRegistration"
              class="flex-1 bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-lg text-lg font-medium transition-colors"
            >
              Cancel
            </button>
          </div>
        </form>
      </div>

      <!-- Registration Status -->
      <div v-if="registrationMessage" class="bg-white shadow-lg rounded-lg p-6">
        <div :class="registrationSuccess ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'" class="px-4 py-3 rounded">
          <p class="font-medium">{{ registrationMessage }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const detectedRFID = ref('')
const registrationMessage = ref('')
const registrationSuccess = ref(false)

const form = ref({
  name: '',
  role: '',
  studentId: '',
  course: '',
  yearLevel: '',
  section: '',
  department: '',
  subjects: '',
  schedule: ''
})

const simulateRFIDDetection = () => {
  // Simulate RFID detection
  detectedRFID.value = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15)
}

const registerRFID = () => {
  // Simulate registration process
  console.log('Registering RFID:', {
    rfid: detectedRFID.value,
    ...form.value
  })

  // Simulate success/failure
  const success = Math.random() > 0.2 // 80% success rate
  if (success) {
    registrationMessage.value = `Successfully registered RFID for ${form.value.name}!`
    registrationSuccess.value = true
    // Reset form after successful registration
    resetForm()
  } else {
    registrationMessage.value = 'Registration failed. Please try again.'
    registrationSuccess.value = false
  }
}

const cancelRegistration = () => {
  resetForm()
  detectedRFID.value = ''
  registrationMessage.value = ''
}

const resetForm = () => {
  form.value = {
    name: '',
    role: '',
    studentId: '',
    course: '',
    yearLevel: '',
    section: '',
    department: '',
    subjects: '',
    schedule: ''
  }
}
</script>

<style scoped>
/* Add any custom styles if needed */
</style>