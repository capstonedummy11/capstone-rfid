<template>
  <div class="w-full">
    <div class="max-w-[1400px] mx-auto px-4">
      <!-- Header -->
      <div class="bg-white shadow-lg rounded-lg p-6 mb-6">
        <div class="flex justify-between items-center">
          <h1 class="text-3xl font-bold">Instructors Management</h1>
          <button @click="showAddInstructorModal = true" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg font-medium transition-colors">
            Add Instructor
          </button>
        </div>
      </div>

      <!-- Instructors Table -->
      <div class="bg-white shadow-lg rounded-lg p-6">
        <div class="overflow-x-auto">
          <table class="w-full table-auto border-collapse">
            <thead>
              <tr class="bg-gray-50">
                <th class="border border-gray-300 px-4 py-3 text-left font-medium">Name</th>
                <th class="border border-gray-300 px-4 py-3 text-left font-medium">Department</th>
                <th class="border border-gray-300 px-4 py-3 text-left font-medium">RFID</th>
                <th class="border border-gray-300 px-4 py-3 text-left font-medium">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="instructor in instructors" :key="instructor.id" class="hover:bg-gray-50">
                <td class="border border-gray-300 px-4 py-3">{{ instructor.name }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ instructor.department }}</td>
                <td class="border border-gray-300 px-4 py-3">
                  <span v-if="instructor.rfid" class="text-green-600">{{ instructor.rfid }}</span>
                  <span v-else class="text-red-600">Not Assigned</span>
                </td>
                <td class="border border-gray-300 px-4 py-3">
                  <div class="flex space-x-2">
                    <button @click="editInstructor(instructor)" class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded text-sm transition-colors">
                      Edit
                    </button>
                    <button @click="deleteInstructor(instructor)" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm transition-colors">
                      Delete
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- No instructors message -->
        <div v-if="instructors.length === 0" class="text-center py-8 text-gray-500">
          <p>No instructors found.</p>
        </div>
      </div>

      <!-- Add/Edit Instructor Modal -->
      <div v-if="showAddInstructorModal || showEditInstructorModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
          <h2 class="text-xl font-bold mb-4">{{ showEditInstructorModal ? 'Edit Instructor' : 'Add New Instructor' }}</h2>
          <form @submit.prevent="saveInstructor" class="space-y-4">
            <div>
              <label for="instructorName" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
              <input
                v-model="instructorForm.name"
                type="text"
                id="instructorName"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Enter instructor name"
              />
            </div>

            <div>
              <label for="instructorDepartment" class="block text-sm font-medium text-gray-700 mb-1">Department</label>
              <select
                v-model="instructorForm.department"
                id="instructorDepartment"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="">Select Department</option>
                <option value="IT Department">IT Department</option>
                <option value="Computer Science">Computer Science</option>
                <option value="Information Systems">Information Systems</option>
                <option value="Engineering">Engineering</option>
              </select>
            </div>

            <div>
              <label for="instructorSubjects" class="block text-sm font-medium text-gray-700 mb-1">Subjects</label>
              <textarea
                v-model="instructorForm.subjects"
                id="instructorSubjects"
                rows="3"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="List subjects separated by commas (e.g., Database Systems, Web Development)"
              ></textarea>
            </div>

            <div>
              <label for="instructorSchedule" class="block text-sm font-medium text-gray-700 mb-1">Schedule</label>
              <textarea
                v-model="instructorForm.schedule"
                id="instructorSchedule"
                rows="3"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Enter schedule details (e.g., Mon/Wed 8:00-10:00, Tue/Thu 1:00-3:00)"
              ></textarea>
            </div>

            <div class="flex space-x-4 pt-4">
              <button
                type="submit"
                class="flex-1 bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg font-medium transition-colors"
              >
                {{ showEditInstructorModal ? 'Update Instructor' : 'Add Instructor' }}
              </button>
              <button
                type="button"
                @click="closeModal"
                class="flex-1 bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium transition-colors"
              >
                Cancel
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const showAddInstructorModal = ref(false)
const showEditInstructorModal = ref(false)
const editingInstructor = ref(null)

const instructors = ref([
  {
    id: 1,
    name: 'Mr. Santos',
    department: 'IT Department',
    subjects: 'Database Systems, Web Development',
    schedule: 'Mon/Wed 8:00-10:00, Tue/Thu 1:00-3:00',
    rfid: 'RFID100'
  },
  {
    id: 2,
    name: 'Ms. Garcia',
    department: 'Computer Science',
    subjects: 'Programming, Data Structures',
    schedule: 'Mon/Fri 9:00-11:00, Wed 2:00-4:00',
    rfid: 'RFID101'
  },
  {
    id: 3,
    name: 'Dr. Reyes',
    department: 'Information Systems',
    subjects: 'System Analysis, Project Management',
    schedule: 'Tue/Thu 10:00-12:00, Fri 3:00-5:00',
    rfid: null
  }
])

const instructorForm = ref({
  name: '',
  department: '',
  subjects: '',
  schedule: ''
})

const editInstructor = (instructor) => {
  editingInstructor.value = instructor
  instructorForm.value = {
    name: instructor.name,
    department: instructor.department,
    subjects: instructor.subjects,
    schedule: instructor.schedule
  }
  showEditInstructorModal.value = true
}

const deleteInstructor = (instructor) => {
  if (confirm(`Are you sure you want to delete ${instructor.name}?`)) {
    const index = instructors.value.findIndex(i => i.id === instructor.id)
    if (index > -1) {
      instructors.value.splice(index, 1)
    }
  }
}

const saveInstructor = () => {
  if (showEditInstructorModal.value) {
    // Update existing instructor
    Object.assign(editingInstructor.value, instructorForm.value)
  } else {
    // Add new instructor
    const newInstructor = {
      id: Date.now(),
      ...instructorForm.value,
      rfid: null
    }
    instructors.value.push(newInstructor)
  }

  closeModal()
}

const closeModal = () => {
  showAddInstructorModal.value = false
  showEditInstructorModal.value = false
  editingInstructor.value = null
  instructorForm.value = {
    name: '',
    department: '',
    subjects: '',
    schedule: ''
  }
}
</script>

<style scoped>
/* Add any custom styles if needed */
</style>