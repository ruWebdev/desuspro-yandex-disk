<script setup>
import { onMounted, ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import DashByteLayout from '@/Layouts/DashByteLayout.vue';

const items = ref([])
const loading = ref(false)
const createName = ref('')
const createPrefix = ref('')
const errors = ref({})
const showCreate = ref(false)

const editing = ref(null)
const editName = ref('')
const editPrefix = ref('')

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
      prefix: createPrefix.value.trim() || null
    })
    createName.value = ''
    createPrefix.value = ''
    showCreate.value = false
    await load()
  } catch (e) {
    if (e.response?.status === 422) errors.value = e.response.data.errors || {}
  }
}

function startEdit(item) {
  editing.value = item
  editName.value = item.name
  editPrefix.value = item.prefix || ''
}

function cancelEdit() {
  editing.value = null
  editName.value = ''
  editPrefix.value = ''
}

async function saveEdit() {
  if (!editing.value) return
  errors.value = {}
  try {
    await window.axios.put(route('task_types.update', editing.value.id), {
      name: editName.value.trim(),
      prefix: editPrefix.value.trim() || null
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
</script>

<template>

  <Head title="Типы задач" />
  <DashByteLayout>

    <div class="row row-deck">
      <div class="col-12">
        <div class="card">
          <div class="card-header">
            <div>
              <div class="card-title">Справочник типов задач</div>
              <div class="card-subtitle">Создание, редактирование и удаление типов задач.</div>
            </div>
            <div class="card-actions d-flex flex-wrap">
              <button class="btn btn-primary" @click="showCreate = true">
                <i class="ti ti-plus"></i>
                Новый тип задачи
              </button>
            </div>
          </div>

          <!-- creation moved to modal -->

          <div class="table-responsive">
            <table class="table table-vcenter">
              <thead>
                <tr>
                  <th class="w-1">#</th>
                  <th>Наименование</th>
                  <th>Префикс</th>
                  <th class="w-1"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="loading">
                  <td colspan="4" class="text-center text-secondary py-4">Загрузка...</td>
                </tr>
                <tr v-else-if="items.length === 0">
                  <td colspan="4" class="text-center text-secondary py-4">Нет данных</td>
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
        </div>
      </div>
    </div>
  </DashByteLayout>

  <!-- Create Modal -->
  <teleport to="body">
    <div v-if="showCreate">
      <div class="modal modal-blur fade show d-block" tabindex="-1" role="dialog" style="z-index: 1050;">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Новый тип задачи</h5>
              <button type="button" class="btn-close" aria-label="Close" @click="showCreate = false"></button>
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
            </div>
            <div class="modal-footer">
              <button type="button" class="btn me-auto" @click="showCreate = false">Отмена</button>
              <button type="button" class="btn btn-primary" :disabled="!createName.trim()"
                @click="createItem">Создать</button>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-backdrop fade show" style="z-index: 1040;" @click="showCreate = false"></div>
    </div>
  </teleport>
</template>
