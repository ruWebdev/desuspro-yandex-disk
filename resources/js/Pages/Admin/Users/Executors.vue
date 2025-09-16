<script>

// Импорт разметки для проекта
import MainLayout from '@/Layouts/MainLayout.vue';
import axios from 'axios';

export default {
  layout: MainLayout
};

</script>

<script setup>
import { ref, computed } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import ContentLayout from '@/Layouts/ContentLayout.vue';

const props = defineProps({
  users: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
})

const search = ref(props.filters.search || '')

function onSearch() {
  router.get(route('users.executors.index'), { search: search.value }, { preserveState: true, replace: true })
}

const showCreate = ref(false)
const showEdit = ref(false)
const showDelete = ref(false)
const showDeleteRestricted = ref(false)
const selected = ref(null)

const createForm = useForm({
  name: '',
  email: '',
  password: '',
  last_name: '',
  first_name: '',
  middle_name: '',
  is_blocked: false,
})

const editForm = useForm({
  name: '',
  email: '',
  password: '',
  last_name: '',
  first_name: '',
  middle_name: '',
  is_blocked: false,
})

// Validation: all fields must be filled
const isFilled = (v) => typeof v === 'string' ? v.trim().length > 0 : !!v
const createInvalid = computed(() => !(
  isFilled(createForm.last_name) &&
  isFilled(createForm.first_name) &&
  isFilled(createForm.middle_name) &&
  isFilled(createForm.email) &&
  isFilled(createForm.password)
))
const editInvalid = computed(() => !(
  isFilled(editForm.last_name) &&
  isFilled(editForm.first_name) &&
  isFilled(editForm.middle_name) &&
  isFilled(editForm.email)
))

function openCreate() {
  createForm.reset()
  showCreate.value = true
}

function openEdit(user) {
  selected.value = user
  editForm.reset()
  editForm.email = user.email
  editForm.last_name = user.last_name
  editForm.first_name = user.first_name
  editForm.middle_name = user.middle_name
  editForm.is_blocked = !!user.is_blocked
  showEdit.value = true
}

function openDelete(user) {
  selected.value = user
  showDelete.value = true
}

function generatePassword(target) {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%'
  let pass = ''
  for (let i = 0; i < 12; i++) pass += chars[Math.floor(Math.random() * chars.length)]
  if (target === 'create') createForm.password = pass
  else editForm.password = pass
}

function submitCreate() {
  // derive display name from FIO
  createForm.name = [createForm.last_name, createForm.first_name, createForm.middle_name].filter(Boolean).join(' ').trim()
  createForm.post(route('users.executors.store'), {
    onSuccess: () => { showCreate.value = false; createForm.reset(); },
  })
}

function submitEdit() {
  if (!selected.value) return
  // derive display name from FIO
  const name = [editForm.last_name, editForm.first_name, editForm.middle_name].filter(Boolean).join(' ').trim()
  const payload = {
    name,
    email: editForm.email,
    last_name: editForm.last_name,
    first_name: editForm.first_name,
    middle_name: editForm.middle_name,
    is_blocked: editForm.is_blocked,
  }
  const pwd = (editForm.password || '').trim()
  if (pwd.length > 0) payload.password = pwd
  router.put(route('users.executors.update', selected.value.id), payload, {
    onSuccess: () => { showEdit.value = false },
  })
}

function submitDelete() {
  if (!selected.value) return
  router.delete(route('users.executors.destroy', selected.value.id), {
    onSuccess: () => { showDelete.value = false },
    onError: (errors) => {
      if (errors.delete) {
        showDelete.value = false
        showDeleteRestricted.value = true
      }
    },
  })
}
</script>

<template>

  <Head title="Исполнители" />
  <ContentLayout>

    <template #TopButtons>
      <div class="d-flex w-100">
        <div class="p-1 flex-fill">
          <input v-model="search" type="text" class="form-control" autocomplete="off"
            placeholder="Поиск по имени или e-mail..." @keyup.enter="onSearch" />
        </div>
        <div class="p-1">
          <button class="btn btn-primary" @click="openCreate">
            Новый исполнитель
          </button>
        </div>
      </div>
    </template>

    <div class="table-responsive">
      <table class="table table-vcenter">
        <thead>
          <tr>
            <th>ФИО</th>
            <th>E-mail</th>
            <th>Заблокирован</th>
            <th class="w-1"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="u in props.users" :key="u.id">
            <td>
              <div class="fw-bold">{{ u.name }}</div>
              <div class="text-secondary">
                {{ [u.last_name, u.first_name, u.middle_name].filter(Boolean).join(' ') }}
              </div>
            </td>
            <td>{{ u.email }}</td>
            <td>
              <span :class="['badge', 'text-light', u.is_blocked ? 'bg-red' : 'bg-green']">{{ u.is_blocked ? 'Да' :
                'Нет'
              }}</span>
            </td>
            <td class="text-end">
              <div class="btn-list flex-nowrap">
                <button class="btn btn-sm" @click="openEdit(u)"><i class="ti ti-edit"></i> Изменить</button>
                <button class="btn btn-sm btn-danger" @click="openDelete(u)"><i class="ti ti-trash"></i>
                  Удалить</button>
              </div>
            </td>
          </tr>
          <tr v-if="props.users.length === 0">
            <td colspan="4" class="text-center text-secondary py-5">Нет данных</td>
          </tr>
        </tbody>
      </table>
    </div>
  </ContentLayout>

  <!-- Create Modal -->
  <div class="modal modal-blur fade" :class="{ show: showCreate }" :style="showCreate ? 'display: block;' : ''"
    tabindex="-1" role="dialog" aria-hidden="true" id="modal-create-executor" v-if="showCreate">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Новый исполнитель</h5>
          <button type="button" class="btn-close" @click="showCreate = false"></button>
        </div>
        <div class="modal-body">
          <div class="row g-2">
            <div class="col-md-4">
              <label class="form-label">Фамилия</label>
              <input v-model="createForm.last_name" type="text" class="form-control" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Имя</label>
              <input v-model="createForm.first_name" type="text" class="form-control" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Отчество</label>
              <input v-model="createForm.middle_name" type="text" class="form-control" />
            </div>
            <div class="col-md-6">
              <label class="form-label">E-mail</label>
              <input v-model="createForm.email" type="email" class="form-control" />
            </div>
            <div class="col-md-6">
              <label class="form-label">Пароль</label>
              <div class="input-group">
                <input v-model="createForm.password" type="text" class="form-control" />
                <button class="btn btn-outline" @click="generatePassword('create')">Сгенерировать</button>
              </div>
            </div>
            <div class="col-md-12">
              <label class="form-check">
                <input class="form-check-input" type="checkbox" v-model="createForm.is_blocked" />
                <span class="form-check-label">Заблокирован</span>
              </label>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn me-auto" @click="showCreate = false">Отмена</button>
          <button class="btn btn-primary" @click="submitCreate" :disabled="createForm.processing || createInvalid">
            Создать
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Edit Modal -->
  <div class="modal modal-blur fade" :class="{ show: showEdit }" :style="showEdit ? 'display: block;' : ''"
    tabindex="-1" role="dialog" aria-hidden="true" id="modal-edit-executor" v-if="showEdit">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Изменить исполнителя</h5>
          <button type="button" class="btn-close" @click="showEdit = false"></button>
        </div>
        <div class="modal-body">
          <div class="row g-2">
            <div class="col-md-4">
              <label class="form-label">Фамилия</label>
              <input v-model="editForm.last_name" type="text" class="form-control" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Имя</label>
              <input v-model="editForm.first_name" type="text" class="form-control" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Отчество</label>
              <input v-model="editForm.middle_name" type="text" class="form-control" />
            </div>
            <div class="col-md-6">
              <label class="form-label">E-mail</label>
              <input v-model="editForm.email" type="email" class="form-control" />
            </div>
            <div class="col-md-6">
              <label class="form-label">Пароль</label>
              <div class="input-group">
                <input v-model="editForm.password" type="text" class="form-control" />
                <button class="btn btn-outline" @click="generatePassword('edit')">Сгенерировать</button>
              </div>
            </div>
            <div class="col-md-12">
              <label class="form-check">
                <input class="form-check-input" type="checkbox" v-model="editForm.is_blocked" />
                <span class="form-check-label">Заблокирован</span>
              </label>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn me-auto" @click="showEdit = false">Отмена</button>
          <button class="btn btn-primary" @click="submitEdit"
            :disabled="editForm.processing || editInvalid">Сохранить</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Delete Modal -->
  <div class="modal modal-blur fade" :class="{ show: showDelete }" :style="showDelete ? 'display: block;' : ''"
    tabindex="-1" role="dialog" aria-hidden="true" id="modal-delete-executor" v-if="showDelete">
    <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-body text-center py-4">
          <i class="ti ti-alert-triangle icon mb-2 text-warning" style="font-size: 2rem"></i>
          <p>Удалить пользователя «{{ selected?.name }}»?</p>
        </div>
        <div class="modal-footer">
          <button class="btn me-auto" @click="showDelete = false">Отмена</button>
          <button class="btn btn-danger" @click="submitDelete">Удалить</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Delete Restricted Modal -->
  <div class="modal modal-blur fade" :class="{ show: showDeleteRestricted }" style="display: block"
    v-if="showDeleteRestricted">
    <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-body text-center py-4">
          <i class="ti ti-alert-triangle icon mb-2 text-danger" style="font-size: 2rem"></i>
          <p>Невозможно удалить пользователя «{{ selected?.name }}», так как ему назначены задачи.</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-primary" @click="showDeleteRestricted = false">Закрыть</button>
        </div>
      </div>
    </div>
  </div>

</template>
