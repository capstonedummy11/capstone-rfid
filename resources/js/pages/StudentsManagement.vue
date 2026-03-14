<template>
  <div class="w-full">
    <div class="max-w-[1400px] mx-auto px-4">
      <!-- Header -->
      <div class="bg-white shadow-lg rounded-lg p-6 mb-6">
        <div class="flex justify-between items-center">
          <h1 class="text-3xl font-bold">Students Management</h1>
          <div class="flex space-x-4">
            <button @click="showAddStudentModal = true" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg font-medium transition-colors">
              Add Student
            </button>
            <button class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg font-medium transition-colors">
              Import Students
            </button>
          </div>
        </div>
      </div>

      <!-- Search and Filters -->
      <div class="bg-white shadow-lg rounded-lg p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search by Name</label>
            <input
              v-model="searchQuery"
              type="text"
              id="search"
              placeholder="Enter student name..."
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          <div>
            <label for="courseFilter" class="block text-sm font-medium text-gray-700 mb-1">Filter by Course</label>
            <select
              v-model="courseFilter"
              id="courseFilter"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">All Courses</option>
              <option value="BSIT">BSIT</option>
              <option value="BSCS">BSCS</option>
              <option value="BSIS">BSIS</option>
            </select>
          </div>
          <div>
            <label for="yearFilter" class="block text-sm font-medium text-gray-700 mb-1">Filter by Year</label>
            <select
              v-model="yearFilter"
              id="yearFilter"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">All Years</option>
              <option value="1">1st Year</option>
              <option value="2">2nd Year</option>
              <option value="3">3rd Year</option>
              <option value="4">4th Year</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Students Table -->
      <div class="bg-white shadow-lg rounded-lg p-6">
        <div class="overflow-x-auto">
          <table class="w-full table-auto border-collapse">
            <thead>
              <tr class="bg-gray-50">
                <th class="border border-gray-300 px-4 py-3 text-left font-medium">Name</th>
                <th class="border border-gray-300 px-4 py-3 text-left font-medium">Student ID</th>
                <th class="border border-gray-300 px-4 py-3 text-left font-medium">Course</th>
                <th class="border border-gray-300 px-4 py-3 text-left font-medium">RFID</th>
                <th class="border border-gray-300 px-4 py-3 text-left font-medium">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="student in filteredStudents" :key="student.id" class="hover:bg-gray-50">
                <td class="border border-gray-300 px-4 py-3">{{ student.name }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ student.studentId }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ student.course }}</td>
                <td class="border border-gray-300 px-4 py-3">
                  <span v-if="student.rfid" class="text-green-600">{{ student.rfid }}</span>
                  <span v-else class="text-red-600">Not Assigned</span>
                </td>
                <td class="border border-gray-300 px-4 py-3">
                  <div class="flex space-x-2">
                    <button @click="editStudent(student)" class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded text-sm transition-colors">
                      Edit
                    </button>
                    <button @click="deleteStudent(student)" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm transition-colors">
                      Delete
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- No students message -->
        <div v-if="filteredStudents.length === 0" class="text-center py-8 text-gray-500">
          <p>No students found matching your criteria.</p>
        </div>
      </div>

      <!-- Add/Edit Student Modal -->
      <div v-if="showAddStudentModal || showEditStudentModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
          <h2 class="text-xl font-bold mb-4">{{ showEditStudentModal ? 'Edit Student' : 'Add New Student' }}</h2>
          <form @submit.prevent="saveStudent" class="space-y-4">
            <div>
              <label for="studentName" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
              <input
                v-model="studentForm.name"
                type="text"
                id="studentName"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Enter student name"
              />
            </div>

            <div>
              <label for="studentId" class="block text-sm font-medium text-gray-700 mb-1">Student ID</label>
              <input
                v-model="studentForm.studentId"
                type="text"
                id="studentId"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Enter student ID"
              />
            </div>

            <div>
              <label for="studentCourse" class="block text-sm font-medium text-gray-700 mb-1">Course</label>
              <select
                v-model="studentForm.course"
                id="studentCourse"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="">Select Course</option>
                <option value="BSIT">BSIT</option>
                <option value="BSCS">BSCS</option>
                <option value="BSIS">BSIS</option>
              </select>
            </div>

            <div>
              <label for="studentYear" class="block text-sm font-medium text-gray-700 mb-1">Year Level</label>
              <select
                v-model="studentForm.year"
                id="studentYear"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="">Select Year</option>
                <option value="1">1st Year</option>
                <option value="2">2nd Year</option>
                <option value="3">3rd Year</option>
                <option value="4">4th Year</option>
              </select>
            </div>

            <div>
              <label for="studentSection" class="block text-sm font-medium text-gray-700 mb-1">Section</label>
              <input
                v-model="studentForm.section"
                type="text"
                id="studentSection"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Enter section"
              />
            </div>

            <div class="flex space-x-4 pt-4">
              <button
                type="submit"
                class="flex-1 bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg font-medium transition-colors"
              >
                {{ showEditStudentModal ? 'Update Student' : 'Add Student' }}
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
import { ref, computed } from 'vue'

const searchQuery = ref('')
const courseFilter = ref('')
const yearFilter = ref('')
const showAddStudentModal = ref(false)
const showEditStudentModal = ref(false)
const editingStudent = ref(null)

const students = ref([
  {
    id: 1,
    name: 'Juan Cruz',
    studentId: '2023001',
    course: 'BSIT',
    year: '3',
    section: '3A',
    rfid: 'RFID200'
  },
  {
    id: 2,
    name: 'Maria Santos',
    studentId: '2023002',
    course: 'BSCS',
    year: '2',
    section: '2B',
    rfid: 'RFID201'
  },
  {
    id: 3,
    name: 'Pedro Reyes',
    studentId: '2023003',
    course: 'BSIT',
    year: '1',
    section: '1A',
    rfid: null
  }
])

const studentForm = ref({
  name: '',
  studentId: '',
  course: '',
  year: '',
  section: ''
})

const filteredStudents = computed(() => {
  return students.value.filter(student => {
    const matchesSearch = student.name.toLowerCase().includes(searchQuery.value.toLowerCase())
    const matchesCourse = !courseFilter.value || student.course === courseFilter.value
    const matchesYear = !yearFilter.value || student.year === yearFilter.value

    return matchesSearch && matchesCourse && matchesYear
  })
})

const editStudent = (student) => {
  editingStudent.value = student
  studentForm.value = {
    name: student.name,
    studentId: student.studentId,
    course: student.course,
    year: student.year,
    section: student.section
  }
  showEditStudentModal.value = true
}

const deleteStudent = (student) => {
  if (confirm(`Are you sure you want to delete ${student.name}?`)) {
    const index = students.value.findIndex(s => s.id === student.id)
    if (index > -1) {
      students.value.splice(index, 1)
    }
  }
}

const saveStudent = () => {
  if (showEditStudentModal.value) {
    // Update existing student
    Object.assign(editingStudent.value, studentForm.value)
  } else {
    // Add new student
    const newStudent = {
      id: Date.now(),
      ...studentForm.value,
      rfid: null
    }
    students.value.push(newStudent)
  }

  closeModal()
}

const closeModal = () => {
  showAddStudentModal.value = false
  showEditStudentModal.value = false
  editingStudent.value = null
  studentForm.value = {
    name: '',
    studentId: '',
    course: '',
    year: '',
    section: ''
  }
}
</script>

<style scoped>
/* Add any custom styles if needed */
</style>