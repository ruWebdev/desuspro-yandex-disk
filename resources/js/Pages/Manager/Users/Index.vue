<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { ref, computed } from 'vue'

const props = defineProps({
  managedUsers: { type: Array, default: () => [] },
  assignableUsers: { type: Array, default: () => [] },
  roles: { type: Array, default: () => ['Photographer', 'PhotoEditor'] },
})

const createForm = useForm({
  name: '',
  email: '',
  password: '',
  role: 'Photographer',
})

function submitCreate() {
  createForm.post(route('manager.users.store'))
}

function attachUser(userId) {
  router.post(route('manager.users.attach', { user: userId }))
}

function detachUser(userId) {
  router.post(route('manager.users.detach', { user: userId }))
}
</script>

<template>
  <Head title="User Management" />
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">User Management</h2>
    </template>

    <div class="py-6">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
        <!-- Create new user -->
        <div class="bg-white shadow sm:rounded p-6">
          <h3 class="text-lg font-medium mb-4">Create Photographer / Photo Editor</h3>
          <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div class="md:col-span-2">
              <label class="block text-sm font-medium mb-1">Name</label>
              <input v-model="createForm.name" class="form-input w-full" type="text" />
              <div class="text-sm text-red-600" v-if="createForm.errors.name">{{ createForm.errors.name }}</div>
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium mb-1">Email</label>
              <input v-model="createForm.email" class="form-input w-full" type="email" />
              <div class="text-sm text-red-600" v-if="createForm.errors.email">{{ createForm.errors.email }}</div>
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Role</label>
              <select v-model="createForm.role" class="form-select w-full">
                <option v-for="r in props.roles" :key="r" :value="r">{{ r }}</option>
              </select>
              <div class="text-sm text-red-600" v-if="createForm.errors.role">{{ createForm.errors.role }}</div>
            </div>
            <div class="md:col-span-5">
              <label class="block text-sm font-medium mb-1">Password (optional, default: password)</label>
              <input v-model="createForm.password" class="form-input w-full" type="password" />
              <div class="text-sm text-red-600" v-if="createForm.errors.password">{{ createForm.errors.password }}</div>
            </div>
          </div>
          <div class="mt-4">
            <button class="btn btn-primary" :disabled="createForm.processing" @click="submitCreate">Create & Assign</button>
          </div>
        </div>

        <!-- Managed users -->
        <div class="bg-white shadow sm:rounded p-6">
          <h3 class="text-lg font-medium mb-4">Your managed users</h3>
          <div class="overflow-x-auto">
            <table class="table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Roles</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="u in props.managedUsers" :key="u.id">
                  <td>{{ u.name }}</td>
                  <td>{{ u.email }}</td>
                  <td>
                    <span v-for="r in u.roles" :key="r.id" class="badge bg-blue-lt me-1">{{ r.name }}</span>
                  </td>
                  <td class="text-right">
                    <button class="btn btn-outline-danger btn-sm" @click="detachUser(u.id)">Detach</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Assignable users -->
        <div class="bg-white shadow sm:rounded p-6">
          <h3 class="text-lg font-medium mb-4">Assignable users</h3>
          <div class="overflow-x-auto">
            <table class="table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Roles</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="u in props.assignableUsers" :key="u.id">
                  <td>{{ u.name }}</td>
                  <td>{{ u.email }}</td>
                  <td>
                    <span v-for="r in u.roles" :key="r.id" class="badge bg-blue-lt me-1">{{ r.name }}</span>
                  </td>
                  <td class="text-right">
                    <button class="btn btn-outline-primary btn-sm" @click="attachUser(u.id)">Attach</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
