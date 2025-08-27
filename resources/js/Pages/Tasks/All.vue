<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import TablerLayout from '@/Layouts/TablerLayout.vue';

const props = defineProps({
  tasks: { type: Array, required: true },
  brands: { type: Array, required: true },
  performers: { type: Array, default: () => [] },
  taskTypes: { type: Array, default: () => [] },
});

// Filters
const search = ref('');
const brandFilter = ref(''); // brand id as string for select

const displayedTasks = computed(() => {
  const q = search.value.trim().toLowerCase();
  const brandId = brandFilter.value ? Number(brandFilter.value) : null;
  return props.tasks.filter(t => {
    const byBrand = brandId ? t.brand_id === brandId : true;
    const byQuery = q ? String(t.name || '').toLowerCase().includes(q) : true;
    return byBrand && byQuery;
  });
});

// Bulk selection state
const selectedIds = ref([]);
const isSelected = (id) => selectedIds.value.includes(id);
function toggleRow(id) {
  if (isSelected(id)) selectedIds.value = selectedIds.value.filter(x => x !== id);
  else selectedIds.value = [...selectedIds.value, id];
}
const selectAllVisible = computed({
  get() {
    const ids = displayedTasks.value.map(t => t.id);
    return ids.length > 0 && ids.every(id => selectedIds.value.includes(id));
  },
  set(val) {
    const ids = displayedTasks.value.map(t => t.id);
    if (val) {
      const set = new Set(selectedIds.value);
      ids.forEach(id => set.add(id));
      selectedIds.value = Array.from(set);
    } else {
      selectedIds.value = selectedIds.value.filter(id => !ids.includes(id));
    }
  }
});
const anySelected = computed(() => selectedIds.value.length > 0);
const selectedTasks = computed(() => props.tasks.filter(t => selectedIds.value.includes(t.id)));
function clearSelection() { selectedIds.value = []; }

// Status helpers
function statusLabel(status) {
  if (status === 'assigned') return 'Назначено';
  if (status === 'done') return 'Готово';
  return 'Создано';
}
function statusClass(status) {
  if (status === 'assigned') return 'bg-primary';
  if (status === 'done') return 'bg-success';
  return 'bg-secondary';
}

function updateListSubtask(taskId, ownership, patch) {
  const t = props.tasks.find(x => x.id === taskId);
  if (!t) return;
  if (!Array.isArray(t.subtasks)) t.subtasks = [];
  let st = t.subtasks.find(s => s.ownership === ownership);
  if (!st) {
    st = { ownership, id: patch?.id ?? null, status: patch?.status ?? null, assignee_id: patch?.assignee_id ?? null };
    t.subtasks.push(st);
  }
  Object.assign(st, patch || {});
}

// Per-task executor assignment modal
const showAssignModal = ref(false);
const assigningTask = ref(null);
const assignUserId = ref(null);
function openAssign(task) { assigningTask.value = task; assignUserId.value = task.assignee_id || null; showAssignModal.value = true; }
function closeAssign() { showAssignModal.value = false; assigningTask.value = null; assignUserId.value = null; }
async function submitAssign() {
  if (!assigningTask.value) return;
  const payload = { assignee_id: assignUserId.value ? Number(assignUserId.value) : null, status: assignUserId.value ? 'assigned' : 'created' };
  await router.put(route('brands.tasks.update', { brand: assigningTask.value.brand_id, task: assigningTask.value.id }), payload, { preserveScroll: true });
  closeAssign();
}

// Bulk delete
const showBulkDelete = ref(false);
function openBulkDelete() { showBulkDelete.value = true; }
function closeBulkDelete() { showBulkDelete.value = false; }
async function confirmBulkDelete() {
  const ids = [...selectedIds.value];
  for (const id of ids) {
    const t = props.tasks.find(x => x.id === id);
    if (!t) continue;
    await router.delete(route('brands.tasks.destroy', { brand: t.brand_id, task: t.id }), { preserveScroll: true });
  }
  closeBulkDelete();
  clearSelection();
}

// Create task modal/form
const modalOpen = ref(false);
const createForm = ref({ name: '', brand_id: '', task_type_id: '', article_id: '' });
const brandArticles = ref([]);
const creating = ref(false);

async function loadArticlesForBrand(brandId) {
  brandArticles.value = [];
  if (!brandId) return;
  try {
    const url = route('brands.articles.index', { brand: Number(brandId) });
    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
    const data = await res.json();
    brandArticles.value = Array.isArray(data?.data) ? data.data : [];
  } catch (e) { console.error(e); }
}

function openCreate() {
  createForm.value = { name: '', brand_id: '', task_type_id: '', article_id: '' };
  brandArticles.value = [];
  modalOpen.value = true;
}

function openUploader() {
  uploadError.value = '';
  if (fileInputRef.value) fileInputRef.value.value = null; // reset
  fileInputRef.value?.click();
}

async function onFilesChosen(e) {
  const files = e.target?.files;
  if (!files || !files.length) return;
  await uploadFiles(Array.from(files));
}

async function uploadFiles(files) {
  const folder = yandexFolderPath();
  if (!folder) return;
  uploading.value = true;
  uploadError.value = '';
  try {
    for (const f of files) {
      const fd = new FormData();
      fd.append('path', `${folder}/${f.name}`);
      fd.append('file', f, f.name);
      const res = await fetch(route('integrations.yandex.upload'), {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') },
        body: fd,
      });
      if (!res.ok) {
        const text = await res.text();
        throw new Error(`Upload failed (${res.status}): ${text}`);
      }
    }
    await loadYandexFiles();
  } catch (e) {
    console.error(e);
    uploadError.value = 'Ошибка загрузки файлов. Попробуйте ещё раз.';
  } finally {
    uploading.value = false;
  }
}

function openFolderUrl() {
  const url = folderPath.value;
  if (!url) return;
  window.open(url, '_blank');
}

async function submitCreate() {
  const payload = {
    brand_id: createForm.value.brand_id ? Number(createForm.value.brand_id) : null,
    task_type_id: createForm.value.task_type_id ? Number(createForm.value.task_type_id) : null,
    article_id: createForm.value.article_id ? Number(createForm.value.article_id) : null,
    name: createForm.value.name?.trim() || undefined,
  };
  if (!payload.brand_id || !payload.task_type_id || !payload.article_id) return;
  creating.value = true;
  router.post(route('tasks.store'), payload, {
    onSuccess: () => { modalOpen.value = false; },
    onFinish: () => { creating.value = false; },
  });
}

// Removed offcanvas, comments, and Yandex files for simplified single-assignment flow

// Row actions: edit / delete
// Rename modal state
const showRename = ref(false);
const renaming = ref(null);
const renameName = ref('');
function onEditTask(t) {
  renaming.value = t;
  renameName.value = t?.name || '';
  showRename.value = true;
}
function cancelRename() { showRename.value = false; renaming.value = null; renameName.value = ''; }
function submitRename() {
  if (!renaming.value) return;
  const name = (renameName.value || '').trim();
  if (!name) return;
  router.put(route('brands.tasks.update', { brand: renaming.value.brand_id, task: renaming.value.id }), { name }, {
    preserveScroll: true,
    onSuccess: () => { cancelRename(); },
  });
}

function onDeleteTask(t) {
  deleting.value = t;
  showDelete.value = true;
}

// Delete modal state/handlers
const showDelete = ref(false);
const deleting = ref(null);
function cancelDelete() {
  showDelete.value = false;
  deleting.value = null;
}
function submitDelete() {
  if (!deleting.value) return;
  router.delete(route('brands.tasks.destroy', { brand: deleting.value.brand_id, task: deleting.value.id }), {
    onSuccess: () => { cancelDelete(); },
    preserveScroll: true,
  });
}
</script>

<template>
  <TablerLayout>

    <Head title="Все задания" />
    <template #header>Все задания</template>

    <div class="row row-deck">
      <div class="col-12">
        <div class="card">
          <div class="card-table">
            <!-- Card Header with Filters -->
            <div class="card-header">
              <div class="row w-full">
                <div class="col">
                  <h3 class="card-title mb-0">Список заданий</h3>
                  <p class="text-secondary m-0">Просмотр и управление всеми заданиями.</p>
                </div>
                <div class="col-md-auto col-sm-12">
                  <div class="ms-auto d-flex flex-wrap btn-list">
                    <!-- Search input -->
                    <div class="input-group input-group-flat w-auto me-2">
                      <span class="input-group-text">
                        <i class="ti ti-search"></i>
                      </span>
                      <input type="text" class="form-control" v-model="search" placeholder="Поиск по названию..."
                        autocomplete="off" />
                    </div>

                    <!-- Brand filter -->
                    <select class="form-select w-auto me-2" v-model="brandFilter">
                      <option value="">Все бренды</option>
                      <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>

                    <!-- Action buttons -->
                    <button class="btn btn-primary" @click="openCreate">
                      <i class="ti ti-plus"></i> Новое задание
                    </button>
                  </div>
                </div>
              </div>

              <!-- Bulk actions (only delete now) -->
              <div v-if="anySelected" class="row mt-3">
                <div class="col-12 d-flex align-items-center">
                  <div class="me-3">
                    <i class="ti ti-selector me-1"></i> Выбрано: {{ selectedIds.length }}
                  </div>
                  <div class="ms-auto">
                    <button class="btn btn-sm btn-outline-secondary me-2" @click="clearSelection">
                      <i class="ti ti-x me-1"></i> Снять выделение
                    </button>
                    <button class="btn btn-sm btn-outline-danger" @click="openBulkDelete">
                      <i class="ti ti-trash me-1"></i> Удалить
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Table -->
            <div class="table-responsive">
              <table class="table table-vcenter card-table">
                <thead>
                  <tr>
                    <th class="w-1"><input type="checkbox" class="form-check-input" v-model="selectAllVisible" /></th>
                    <th>Бренд</th>
                    <th>Артикул</th>
                    <th>Тип задачи</th>
                    <th>Исполнитель</th>
                    <th>Статус</th>
                    <th class="w-1">Действия</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="displayedTasks.length === 0">
                    <td colspan="7" class="text-center text-secondary py-4">Нет заданий</td>
                  </tr>
                  <tr v-for="t in displayedTasks" :key="t.id">
                    <td>
                      <input type="checkbox" class="form-check-input" :checked="isSelected(t.id)"
                        @change="toggleRow(t.id)" />
                    </td>
                    <td>{{t.brand?.name || (brands.find(b => b.id === t.brand_id)?.name)}}</td>
                    <td>{{ t.article?.name || '' }}</td>
                    <td>{{ t.type?.name || '' }}</td>
                    <td>
                      <div class="d-flex align-items-center gap-2">
                        <span v-if="t.assignee?.name" class="text-secondary">{{ t.assignee.name }}</span>
                        <button class="btn btn-sm btn-outline-primary" @click="openAssign(t)">
                          {{ t.assignee?.name ? 'Изменить' : 'Назначить' }}
                        </button>
                      </div>
                    </td>
                    <td>
                      <span class="badge" :class="statusClass(t.status)">{{ statusLabel(t.status) }}</span>
                    </td>
                    <td class="text-nowrap">
                      <div class="btn-list d-flex flex-nowrap align-items-center gap-2">
                        <button class="btn btn-icon btn-ghost-primary" @click="onEditTask(t)" title="Редактировать">
                          <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24"
                            viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" />
                            <path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z" />
                            <path d="M16 5l3 3" />
                          </svg>
                        </button>
                        <button class="btn btn-icon btn-ghost-danger" @click="onDeleteTask(t)" title="Удалить">
                          <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24"
                            viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M4 7l16 0" />
                            <path d="M10 11l0 6" />
                            <path d="M14 11l0 6" />
                            <path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" />
                            <path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" />
                          </svg>
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Create Modal -->
    <teleport to="body">
      <div class="modal modal-blur fade" :class="{ show: modalOpen }" :style="modalOpen ? 'display: block;' : ''"
        tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Создать задание</h5>
              <button type="button" class="btn-close" @click="modalOpen = false" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Бренд</label>
                  <select class="form-select" v-model="createForm.brand_id"
                    @change="loadArticlesForBrand(createForm.brand_id)">
                    <option value="">Выберите бренд</option>
                    <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Тип задачи</label>
                  <select class="form-select" v-model="createForm.task_type_id">
                    <option value="">Выберите тип</option>
                    <option v-for="tt in taskTypes" :key="tt.id" :value="tt.id">{{ tt.name }}</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Артикул</label>
                  <select class="form-select" v-model="createForm.article_id" :disabled="!createForm.brand_id">
                    <option value="">Выберите артикул</option>
                    <option v-for="a in brandArticles" :key="a.id" :value="a.id">{{ a.name }}</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Название (необязательно)</label>
                  <input type="text" class="form-control" v-model="createForm.name"
                    placeholder="По умолчанию — название статьи" />
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn me-auto" @click="modalOpen = false">Отмена</button>
              <button type="button" class="btn btn-primary"
                :disabled="creating || !createForm.brand_id || !createForm.task_type_id || !createForm.article_id"
                @click="submitCreate">
                <span v-if="creating" class="spinner-border spinner-border-sm me-2"></span>
                Создать
              </button>
            </div>
          </div>
        </div>
      </div>
    </teleport>

    <!-- Assign Executor Modal -->
    <teleport to="body">
      <div class="modal modal-blur fade" :class="{ show: showAssignModal }"
        :style="showAssignModal ? 'display: block;' : ''" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Назначить исполнителя</h5>
              <button type="button" class="btn-close" @click="closeAssign" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <label class="form-label">Исполнитель</label>
              <select class="form-select" v-model="assignUserId">
                <option :value="null">Не назначен</option>
                <option v-for="u in performers" :key="u.id" :value="u.id">{{ u.name }}<span v-if="u.is_blocked"> —
                    ЗАБЛОКИРОВАН</span></option>
              </select>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn me-auto" @click="closeAssign">Отмена</button>
              <button type="button" class="btn btn-primary" @click="submitAssign">Сохранить</button>
            </div>
          </div>
        </div>
      </div>
    </teleport>
  </TablerLayout>
</template>
