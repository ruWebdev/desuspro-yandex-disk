<script setup>
import TablerLayout from '@/Layouts/TablerLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
  brand: { type: Object, required: true },
  tasks: { type: Array, default: () => [] },
  assignees: { type: Object, default: () => ({ Photographer: [], PhotoEditor: [] }) },
});

// Search / filter
const search = ref('');
const statusFilter = ref('all'); // all|created|assigned|done

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase();
  return props.tasks.filter((t) => {
    if (statusFilter.value !== 'all' && t.status !== statusFilter.value) return false;
    if (!q) return true;
    return (
      (t.name || '').toLowerCase().includes(q) ||
      (t.comment || '').toLowerCase().includes(q) ||
      (t.assignee?.name || '').toLowerCase().includes(q)
    );
  });
});

const displayed = computed(() => [...filtered.value].sort((a, b) => new Date(b.created_at) - new Date(a.created_at)));

// Create modal (match All.vue: only name, brand taken from page, server creates two subtasks)
const showCreate = ref(false);
const createForm = useForm({ name: '', brand_id: null });
const openCreate = () => { createForm.reset(); showCreate.value = true; };
const submitCreate = () => {
  if (!createForm.name) return;
  createForm.brand_id = props.brand.id;
  createForm.post(route('tasks.store'), {
    preserveScroll: true,
    onSuccess: () => { showCreate.value = false; createForm.reset(); },
  });
};

// Edit modal
const showEdit = ref(false);
const editing = ref(null);
const editForm = useForm({ name: '', status: 'created', ownership: 'Photographer', assignee_id: null, highlighted: false, comment: '' });
function startEdit(task) {
  editing.value = task;
  editForm.clearErrors();
  editForm.name = task.name;
  editForm.status = task.status;
  editForm.ownership = task.ownership;
  editForm.assignee_id = task.assignee_id || null;
  editForm.highlighted = !!task.highlighted;
  editForm.comment = task.comment || '';
  showEdit.value = true;
}
function submitEdit() {
  if (!editing.value) return;
  editForm.put(route('brands.tasks.update', { brand: props.brand.id, task: editing.value.id }), {
    preserveScroll: true,
    onSuccess: () => { showEdit.value = false; editing.value = null; },
  });
}

// Delete modal
const showDelete = ref(false);
const deleting = ref(null);
function askDelete(task) { deleting.value = task; showDelete.value = true; }
function doDelete() {
  if (!deleting.value) return;
  useForm({}).delete(route('brands.tasks.destroy', { brand: props.brand.id, task: deleting.value.id }), {
    preserveScroll: true,
    onSuccess: () => { showDelete.value = false; deleting.value = null; },
  });
}

// Files modals
const showUpload = ref(false);
const uploadTask = ref(null);
const uploadForm = useForm({ files: [] });
function openUpload(task) { uploadTask.value = task; uploadForm.clearErrors(); uploadForm.files = []; showUpload.value = true; }
function onFilesChange(e) { uploadForm.files = Array.from(e.target.files || []); }
function submitUpload() {
  if (!uploadTask.value) return;
  const formData = new FormData();
  uploadForm.files.forEach((f) => formData.append('files[]', f));
  uploadForm.post(route('brands.tasks.upload', { brand: props.brand.id, task: uploadTask.value.id }), {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: () => { showUpload.value = false; uploadTask.value = null; uploadForm.reset(); },
  });
}

function downloadAll(task) {
  window.location.href = route('brands.tasks.download', { brand: props.brand.id, task: task.id });
}

// Public link
function togglePublicLink(task) {
  if (task.public_link) {
    useForm({}).delete(route('brands.tasks.public_link.delete', { brand: props.brand.id, task: task.id }), { preserveScroll: true });
  } else {
    useForm({}).post(route('brands.tasks.public_link', { brand: props.brand.id, task: task.id }), { preserveScroll: true });
  }
}

const assigneeOptions = computed(() => ({
  Photographer: props.assignees.Photographer || [],
  PhotoEditor: props.assignees.PhotoEditor || [],
}));

// Offcanvas state and opener (match All.vue)
const offcanvasOpen = ref(false);
const oc = ref({ brandId: null, brandName: '', taskId: null, taskName: '', ownership: 'Photographer' });
function openOffcanvas(task, ownership) {
  oc.value = {
    brandId: task.brand_id,
    brandName: props.brand?.name || '',
    taskId: task.id,
    taskName: task.name,
    ownership,
  };
  offcanvasOpen.value = true;
}
</script>

<template>

  <Head :title="`Задачи — ${brand.name}`" />
  <TablerLayout>
    <template #header>
      Задачи — {{ brand.name }}
    </template>

    <div class="card">
      <div class="card-header">
        <div class="row w-full">
          <div class="col">
            <h3 class="card-title mb-0">Список задач</h3>
            <p class="text-secondary m-0">Создание, назначение, изменение статуса, файлы, публичная ссылка.</p>
          </div>
          <div class="col-md-auto col-sm-12">
            <div class="ms-auto d-flex flex-wrap btn-list">
              <div class="input-group input-group-flat w-auto me-2">
                <span class="input-group-text">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    class="icon icon-1">
                    <path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0" />
                    <path d="M21 21l-6 -6" />
                  </svg>
                </span>
                <input v-model="search" type="text" class="form-control" placeholder="Поиск..." />
              </div>
              <select v-model="statusFilter" class="form-select w-auto me-2">
                <option value="all">Все статусы</option>
                <option value="created">Создана</option>
                <option value="assigned">Назначена</option>
                <option value="done">Выполнена</option>
              </select>
              <button class="btn btn-primary" @click="openCreate">Создать задачу</button>
              <Link class="btn" :href="route('brands.index')">К брендам</Link>
            </div>
          </div>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-vcenter card-table">
          <thead>
            <tr>
              <th>Задание</th>
              <th>Бренд</th>
              <th>Ответственный</th>
              <th>Статус</th>
              <th class="w-1">ПОДЗАДАНИЯ</th>
              <th>Создан</th>
              <th class="w-1">ДЕЙСТВИЯ</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="displayed.length === 0">
              <td colspan="7" class="text-center text-secondary py-4">Задачи отсутствуют</td>
            </tr>
            <tr v-for="t in displayed" :key="t.id">
              <td>{{ t.name }}</td>
              <td>{{ brand.name }}</td>
              <td>{{ t.assignee?.name || '—' }}</td>
              <td>
                <span class="badge" :class="{
                  'bg-secondary': t.status === 'created' || !t.status,
                  'bg-blue': t.status === 'assigned',
                  'bg-green': t.status === 'done',
                }">{{ t.status || 'created' }}</span>
              </td>
              <td class="text-nowrap">
                <div class="btn-list d-flex flex-nowrap align-items-center gap-2" style="white-space: nowrap;">
                  <button class="btn btn-ghost-primary btn-sm" @click="openOffcanvas(t, 'Photographer')"
                    title="Открыть подзадачу: ФОТОГРАФ">ФОТОГРАФ</button>
                  <button class="btn btn-ghost-purple btn-sm" @click="openOffcanvas(t, 'PhotoEditor')"
                    title="Открыть подзадачу: ФОТОРЕДАКТОР">ФОТОРЕДАКТОР</button>
                </div>
              </td>
              <td>{{ new Date(t.created_at).toLocaleString('ru-RU') }}</td>
              <td class="text-nowrap">
                <div class="btn-list d-flex flex-nowrap align-items-center gap-2">
                  <button class="btn btn-icon btn-ghost-primary" title="Изменить" @click="startEdit(t)">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24"
                      stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                      <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                      <path d="M4 20h4l10.5 -10.5a2.828 2.828 0 1 0 -4 -4l-10.5 10.5v4" />
                      <path d="M13.5 6.5l4 4" />
                    </svg>
                  </button>
                  <button class="btn btn-icon btn-ghost-danger" title="Удалить" @click="askDelete(t)">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="16" height="16" viewBox="0 0 24 24"
                      stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                      <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                      <path d="M4 7h16" />
                      <path d="M10 11v6" />
                      <path d="M14 11v6" />
                      <path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" />
                      <path d="M9 7v-2a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v2" />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Right offcanvas -->
    <teleport to="body">
      <div class="offcanvas offcanvas-end" :class="{ show: offcanvasOpen }"
        :style="offcanvasOpen ? 'visibility: visible;' : ''" tabindex="-1" role="dialog">
        <div class="offcanvas-header">
          <h5 class="offcanvas-title">
            {{ oc.brandName }} / {{ oc.taskName }}<br />
            <span class="badge text-light" :class="{
              'bg-blue': oc.ownership === 'Photographer',
              'bg-purple': oc.ownership === 'PhotoEditor',
            }">
              {{ oc.ownership === 'Photographer' ? 'Фотограф' : 'Фоторедактор' }}
            </span>
          </h5>
          <button type="button" class="btn-close text-reset" aria-label="Close" @click="offcanvasOpen = false"></button>
        </div>
        <div class="offcanvas-body">
          <!-- TODO: content to be defined later -->
          <div class="text-secondary">Здесь будет содержимое подзадачи.</div>
        </div>
      </div>
    </teleport>

    <!-- Create Modal -->
    <teleport to="body">
      <div v-if="showCreate">
        <div class="modal modal-blur fade show d-block" tabindex="-1" role="dialog" style="z-index: 1050;">
          <div class="modal-dialog" role="document">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Создать задачу</h5>
                <button type="button" class="btn-close" aria-label="Close" @click="showCreate = false"></button>
              </div>
              <div class="modal-body">
                <form @submit.prevent="submitCreate">
                  <div class="mb-2">
                    <label class="form-label">Наименование</label>
                    <input v-model="createForm.name" type="text" class="form-control" required />
                  </div>
                  <div v-if="Object.keys(createForm.errors).length" class="text-danger small mt-2">
                    <div v-for="(err, key) in createForm.errors" :key="key">{{ err }}</div>
                  </div>
                </form>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn me-auto" @click="showCreate = false">Отмена</button>
                <button :disabled="createForm.processing || !createForm.name" type="button" class="btn btn-primary"
                  @click="submitCreate">
                  <span v-if="createForm.processing" class="spinner-border spinner-border-sm me-2" />Создать
                </button>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-backdrop fade show" style="z-index: 1040;" @click="showCreate = false"></div>
      </div>
    </teleport>

    <!-- Edit Modal -->
    <teleport to="body">
      <div v-if="showEdit && editing">
        <div class="modal modal-blur fade show d-block" tabindex="-1" role="dialog" style="z-index: 1050;">
          <div class="modal-dialog" role="document">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Редактирование задачи</h5>
                <button type="button" class="btn-close" aria-label="Close" @click="showEdit = false"></button>
              </div>
              <div class="modal-body">
                <form @submit.prevent="submitEdit">
                  <div class="mb-2">
                    <label class="form-label">Наименование</label>
                    <input v-model="editForm.name" type="text" class="form-control" required />
                  </div>
                  <div class="row g-2">
                    <div class="col-sm-6">
                      <label class="form-label">Статус</label>
                      <select v-model="editForm.status" class="form-select">
                        <option value="created">Создана</option>
                        <option value="assigned">Назначена</option>
                        <option value="done">Выполнена</option>
                      </select>
                    </div>
                    <div class="col-sm-6">
                      <label class="form-label">Принадлежность</label>
                      <select v-model="editForm.ownership" class="form-select">
                        <option value="Photographer">Фотограф</option>
                        <option value="PhotoEditor">Фоторедактор</option>
                      </select>
                    </div>
                  </div>
                  <div class="row g-2 mt-1">
                    <div class="col-sm-8">
                      <label class="form-label">Исполнитель</label>
                      <select v-model="editForm.assignee_id" class="form-select">
                        <option :value="null">— Не назначено —</option>
                        <option v-for="u in assigneeOptions[editForm.ownership]" :key="u.id" :value="u.id">{{ u.name }}
                        </option>
                      </select>
                    </div>
                    <div class="col-sm-4 d-flex align-items-end">
                      <label class="form-check">
                        <input v-model="editForm.highlighted" class="form-check-input" type="checkbox">
                        <span class="form-check-label">Выделена</span>
                      </label>
                    </div>
                  </div>
                  <div class="mt-2">
                    <label class="form-label">Комментарий</label>
                    <textarea v-model="editForm.comment" rows="3" class="form-control"
                      placeholder="Комментарий"></textarea>
                  </div>
                  <div v-if="Object.keys(editForm.errors).length" class="text-danger small mt-2">
                    <div v-for="(err, key) in editForm.errors" :key="key">{{ err }}</div>
                  </div>
                </form>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn me-auto" @click="showEdit = false">Отмена</button>
                <button :disabled="editForm.processing" type="button" class="btn btn-primary" @click="submitEdit">
                  <span v-if="editForm.processing" class="spinner-border spinner-border-sm me-2" />Сохранить
                </button>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-backdrop fade show" style="z-index: 1040;" @click="showEdit = false"></div>
      </div>
    </teleport>

    <!-- Delete Modal -->
    <teleport to="body">
      <div v-if="showDelete && deleting">
        <div class="modal modal-blur fade show d-block" tabindex="-1" role="dialog" style="z-index: 1050;">
          <div class="modal-dialog" role="document">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Удалить задачу</h5>
                <button type="button" class="btn-close" aria-label="Close" @click="showDelete = false"></button>
              </div>
              <div class="modal-body">Вы уверены, что хотите удалить задачу <strong>{{ deleting?.name }}</strong>?</div>
              <div class="modal-footer">
                <button type="button" class="btn me-auto" @click="showDelete = false">Отмена</button>
                <button type="button" class="btn btn-danger" @click="doDelete">Удалить</button>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-backdrop fade show" style="z-index: 1040;" @click="showDelete = false"></div>
      </div>
    </teleport>

    <!-- Upload Modal -->
    <teleport to="body">
      <div v-if="showUpload && uploadTask">
        <div class="modal modal-blur fade show d-block" tabindex="-1" role="dialog" style="z-index: 1050;">
          <div class="modal-dialog" role="document">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Загрузить файлы для «{{ uploadTask?.name }}»</h5>
                <button type="button" class="btn-close" aria-label="Close" @click="showUpload = false"></button>
              </div>
              <div class="modal-body">
                <input type="file" multiple class="form-control" @change="onFilesChange" />
                <div class="text-secondary small mt-2">Можно выбрать несколько файлов. Максимум 50 МБ на файл.</div>
                <div v-if="Object.keys(uploadForm.errors).length" class="text-danger small mt-2">
                  <div v-for="(err, key) in uploadForm.errors" :key="key">{{ err }}</div>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn me-auto" @click="showUpload = false">Отмена</button>
                <button :disabled="uploadForm.processing" type="button" class="btn btn-primary" @click="submitUpload">
                  <span v-if="uploadForm.processing" class="spinner-border spinner-border-sm me-2" />Загрузить
                </button>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-backdrop fade show" style="z-index: 1040;" @click="showUpload = false"></div>
      </div>
    </teleport>
  </TablerLayout>
</template>
