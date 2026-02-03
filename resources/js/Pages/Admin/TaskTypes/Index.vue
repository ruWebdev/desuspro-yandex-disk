<script>

// Импорт разметки для проекта
import MainLayout from '@/Layouts/MainLayout.vue';
import axios from 'axios';

export default {
  layout: MainLayout
};

</script>

<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import ContentLayout from '@/Layouts/ContentLayout.vue';

const items = ref([])
const loading = ref(false)
const createName = ref('')
const createPrefix = ref('')
const createEmptyFolder = ref(false)
const errors = ref({})
const showCreate = ref(false)

const editing = ref(null)
const editName = ref('')
const editPrefix = ref('')
const editEmptyFolder = ref(false)

// Bootstrap 5 Modal handling
const createModalEl = ref(null)
let createModalInstance = null

async function load() {
  loading.value = true
  try {
    const { data } = await window.axios.get(route('task_types.index'))
    items.value = data?.data || []
  } finally {
    loading.value = false
  }
}

async function createItem() {
  errors.value = {}
  try {
    await window.axios.post(route('task_types.store'), {
      name: createName.value.trim(),
      prefix: createPrefix.value.trim() || null,
      create_empty_folder: !!createEmptyFolder.value
    })
    createName.value = ''
    createPrefix.value = ''
    createEmptyFolder.value = false
    closeCreateModal()
    await load()
  } catch (e) {
    if (e.response?.status === 422) errors.value = e.response.data.errors || {}
  }
}

function startEdit(item) {
  editing.value = item
  editName.value = item.name
  editPrefix.value = item.prefix || ''
  editEmptyFolder.value = !!item.create_empty_folder
}

function cancelEdit() {
  editing.value = null
  editName.value = ''
  editPrefix.value = ''
  editEmptyFolder.value = false
}

async function saveEdit() {
  if (!editing.value) return
  errors.value = {}
  try {
    await window.axios.put(route('task_types.update', editing.value.id), {
      name: editName.value.trim(),
      prefix: editPrefix.value.trim() || null,
      create_empty_folder: !!editEmptyFolder.value
    })
    cancelEdit()
    await load()
  } catch (e) {
    if (e.response?.status === 422) errors.value = e.response.data.errors || {}
  }
}

async function remove(item) {
  await window.axios.delete(route('task_types.destroy', item.id))
  await load()
}

onMounted(load)

function ensureCreateModal() {
  if (!createModalInstance && createModalEl.value && window.bootstrap?.Modal) {
    createModalInstance = new window.bootstrap.Modal(createModalEl.value, {
      backdrop: true,
      focus: true
    })
  }
}

function openCreateModal() {
  showCreate.value = true
  ensureCreateModal()
  if (createModalInstance) createModalInstance.show()
}

function closeCreateModal() {
  showCreate.value = false
  if (createModalInstance) createModalInstance.hide()
}

onBeforeUnmount(() => {
  if (createModalInstance) {
    createModalInstance.hide()
    createModalInstance.dispose?.()
    createModalInstance = null
  }
})
</script>

<template>

  <Head title="Типы задач" />
  <ContentLayout>

    <template #TopButtons>
      <div class="d-flex w-100">
        <div class="p-1 flex-fill">
          <input v-model="search" type="text" class="form-control" autocomplete="off" placeholder="Поиск по названию..."
            @keyup.enter="onSearch" />
        </div>
        <div class="p-1">
          <button class="btn btn-primary" @click="openCreateModal">
            <i class="ti ti-plus"></i>
            Новый тип задачи
          </button>
        </div>
      </div>
    </template>


    <div class="table-responsive">
      <table class="table table-vcenter">
        <thead>
          <tr>
            <th class="w-1">#</th>
            <th>Наименование</th>
            <th>Префикс</th>
            <th>Папка</th>
            <th class="w-1"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="5" class="text-center text-secondary py-4">Загрузка...</td>
          </tr>
          <tr v-else-if="items.length === 0">
            <td colspan="5" class="text-center text-secondary py-4">Нет данных</td>
          </tr>
          <tr v-for="(it, i) in items" :key="it.id">
            <td>{{ i + 1 }}</td>
            <td>
              <template v-if="editing?.id === it.id">
                <input v-model="editName" type="text" class="form-control" @keyup.enter="saveEdit" />
                <div class="text-danger small" v-if="errors.name">{{ errors.name }}</div>
              </template>
              <template v-else>
                {{ it.name }}
              </template>
            </td>
            <td>
              <template v-if="editing?.id === it.id">
                <input v-model="editPrefix" type="text" class="form-control" placeholder="Префикс" />
                <div class="text-danger small" v-if="errors.prefix">{{ errors.prefix }}</div>
              </template>
              <template v-else>
                {{ it.prefix || '—' }}
              </template>
            </td>
            <td>
              <template v-if="editing?.id === it.id">
                <div class="form-check">
                  <input :id="`edit-empty-folder-${it.id}`" class="form-check-input" type="checkbox" v-model="editEmptyFolder">
                  <label class="form-check-label" :for="`edit-empty-folder-${it.id}`">Создавать пустую папку</label>
                </div>
              </template>
              <template v-else>
                {{ it.create_empty_folder ? 'Да' : 'Нет' }}
              </template>
            </td>
            <td class="text-end">
              <div class="btn-list flex-nowrap">
                <template v-if="editing?.id === it.id">
                  <button class="btn btn-sm btn-primary" @click="saveEdit">Сохранить</button>
                  <button class="btn btn-sm" @click="cancelEdit">Отмена</button>
                </template>
                <template v-else>
                  <button class="btn btn-sm" @click="startEdit(it)">Изменить</button>
                  <button class="btn btn-sm btn-danger" @click="remove(it)">Удалить</button>
                </template>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>


  </ContentLayout>

  <!-- Create Modal (Bootstrap 5 managed) -->
  <teleport to="body">
    <div class="modal modal-blur fade" tabindex="-1" role="dialog" ref="createModalEl" id="taskTypeCreateModal">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Новый тип задачи</h5>
            <button type="button" class="btn-close" aria-label="Close" @click="closeCreateModal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Наименование</label>
              <input v-model="createName" type="text" class="form-control" placeholder="Например: Ретушь"
                @keyup.enter="createItem" />
              <div class="text-danger small mt-1" v-if="errors.name">{{ errors.name }}</div>
            </div>
            <div class="mb-3">
              <label class="form-label">Префикс</label>
              <input v-model="createPrefix" type="text" class="form-control" placeholder="Например: R"
                maxlength="10" />
              <div class="text-danger small mt-1" v-if="errors.prefix">{{ errors.prefix }}</div>
              <small class="text-secondary">Максимум 10 символов</small>
            </div>
            <div class="mb-2">
              <div class="form-check">
                <input id="create-empty-folder" class="form-check-input" type="checkbox" v-model="createEmptyFolder">
                <label class="form-check-label" for="create-empty-folder">Создавать пустую папку</label>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn me-auto" @click="closeCreateModal">Отмена</button>
            <button type="button" class="btn btn-primary" :disabled="!createName.trim()"
              @click="createItem">Создать</button>
          </div>
        </div>
      </div>
    </div>
  </teleport>
</template>
