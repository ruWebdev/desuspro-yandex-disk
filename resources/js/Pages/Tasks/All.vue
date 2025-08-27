<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import TablerLayout from '@/Layouts/TablerLayout.vue';

const props = defineProps({
  tasks: { type: Array, required: true },
  brands: { type: Array, required: true },
  assignees: { type: Object, default: () => ({ Photographer: [], PhotoEditor: [] }) },
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

// Subtask helpers and status mapping
function subtaskByOwnership(task, ownership) {
  return (task?.subtasks || []).find(s => s.ownership === ownership) || null;
}

function statusLabel(status, hasAssignee) {
  // If explicit states
  if (status === 'on_review') return 'На проверку';
  if (status === 'accepted') return 'Принята';
  if (status === 'rejected') return 'Не принята';
  // Derive from assignee when status not in explicit terminal states
  if (!hasAssignee) return 'Не назначено';
  return 'Назначено';
}

function statusClass(status, hasAssignee) {
  if (status === 'on_review') return 'bg-warning';
  if (status === 'accepted') return 'bg-success';
  if (status === 'rejected') return 'bg-danger';
  return hasAssignee ? 'bg-primary' : 'bg-secondary';
}

// Find assignee FIO by id per ownership
function assigneeNameById(ownership, id) {
  if (!id) return '';
  const list = ownership === 'Photographer' ? (props.assignees?.Photographer || []) : (props.assignees?.PhotoEditor || []);
  const u = list.find(x => x.id === id);
  return u?.name || '';
}

function updateListSubtask(taskId, ownership, patch) {
  const t = props.tasks.find(x => x.id === taskId);
  if (!t || !Array.isArray(t.subtasks)) return;
  const st = t.subtasks.find(s => s.ownership === ownership);
  if (!st) return;
  Object.assign(st, patch || {});
}

// Create task modal/form
const modalOpen = ref(false);
const createForm = ref({ name: '', brand_id: '', ownership: 'Photographer' });

function openCreate() {
  createForm.value = { name: '', brand_id: '', ownership: 'Photographer' };
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

function submitCreate() {
  if (!createForm.value.name || !createForm.value.brand_id) return;
  router.post(route('tasks.store'), createForm.value, {
    onSuccess: () => { modalOpen.value = false; },
  });
}

// Offcanvas state for per-ownership view
const offcanvasOpen = ref(false);
const oc = ref({ brandId: null, brandName: '', taskId: null, taskName: '', ownership: 'Photographer' });
const activeOcTab = ref('comments'); // files | comments
const subtaskId = ref(null);
const currentSubtask = ref(null);
const commentsLoading = ref(false);
const comments = ref([]);
const newComment = ref('');
const submitting = ref(false);

// Bootstrap Offcanvas integration
const offcanvasEl = ref(null);
let offcanvasInstance = null;
const hasOffcanvas = ref(false);
onMounted(() => {
  const Ctor = window.bootstrap?.Offcanvas;
  if (Ctor && offcanvasEl.value) {
    offcanvasInstance = new Ctor(offcanvasEl.value, { backdrop: true, keyboard: true, scroll: true });
    hasOffcanvas.value = true;
    offcanvasEl.value.addEventListener('show.bs.offcanvas', () => { offcanvasOpen.value = true; });
    offcanvasEl.value.addEventListener('hidden.bs.offcanvas', () => { offcanvasOpen.value = false; });
  }
});

function closeOffcanvas() { if (offcanvasInstance) offcanvasInstance.hide(); else offcanvasOpen.value = false; }

// Assignee selection state for offcanvas
const assigneeOptions = computed(() => ({
  Photographer: props.assignees?.Photographer || [],
  PhotoEditor: props.assignees?.PhotoEditor || [],
}));
function assigneeLabel(u) {
  return u?.name + (u?.is_blocked ? ' — ЗАБЛОКИРОВАН' : '');
}
const selectedAssigneeId = ref(null);
let lastAssigneeId = null;
const canSaveAssignee = computed(() => (selectedAssigneeId.value ?? null) !== (lastAssigneeId ?? null));
const assigneeButtonLabel = computed(() => lastAssigneeId ? 'Изменить' : 'Сохранить');

// Reassign confirm modal state
const showReassign = ref(false);
const pendingAssigneeId = ref(null);
function openReassignConfirm(newId) { pendingAssigneeId.value = newId; showReassign.value = true; }
function cancelReassign() { showReassign.value = false; pendingAssigneeId.value = null; }
function confirmReassign() {
  if (pendingAssigneeId.value === undefined || pendingAssigneeId.value === null) return;
  doSaveAssignee(pendingAssigneeId.value);
  cancelReassign();
}
function saveAssignee() {
  const newId = selectedAssigneeId.value ? Number(selectedAssigneeId.value) : null;
  if (lastAssigneeId && lastAssigneeId !== newId) {
    openReassignConfirm(newId);
    return;
  }
  doSaveAssignee(newId);
}
function doSaveAssignee(newId) {
  if (!oc.value.taskId || !subtaskId.value) return;
  router.put(
    route('brands.tasks.subtasks.update', { brand: oc.value.brandId, task: oc.value.taskId, subtask: subtaskId.value }),
    { assignee_id: newId },
    {
      preserveScroll: true,
      onSuccess: () => {
        lastAssigneeId = newId;
        if (currentSubtask.value) {
          currentSubtask.value.assignee_id = newId;
          // reflect status change locally per backend auto-logic
          currentSubtask.value.status = newId ? 'assigned' : 'unassigned';
          updateListSubtask(oc.value.taskId, oc.value.ownership, { assignee_id: newId, status: currentSubtask.value.status });
        }
      },
      onError: () => { selectedAssigneeId.value = lastAssigneeId; },
    }
  );
}

function acceptSubtask() {
  if (!subtaskId.value) return;
  router.put(
    route('brands.tasks.subtasks.update', { brand: oc.value.brandId, task: oc.value.taskId, subtask: subtaskId.value }),
    { status: 'accepted' },
    {
      preserveScroll: true,
      onSuccess: () => {
        if (currentSubtask.value) {
          currentSubtask.value.status = 'accepted';
          updateListSubtask(oc.value.taskId, oc.value.ownership, { status: 'accepted' });
        }
      },
    }
  );
}

function rejectSubtask() {
  if (!subtaskId.value) return;
  router.put(
    route('brands.tasks.subtasks.update', { brand: oc.value.brandId, task: oc.value.taskId, subtask: subtaskId.value }),
    { status: 'rejected' },
    {
      preserveScroll: true,
      onSuccess: () => {
        if (currentSubtask.value) {
          currentSubtask.value.status = 'rejected';
          updateListSubtask(oc.value.taskId, oc.value.ownership, { status: 'rejected' });
        }
      },
    }
  );
}

// Yandex.Disk files state
const filesLoading = ref(false);
const filesError = ref('');
const yandexItems = ref([]);
const publicFolderUrl = ref('');

// Upload state (Photographer)
const fileInputRef = ref(null);
const uploading = ref(false);
const uploadError = ref('');

// Public folder URL (for display/copy)
const folderPath = computed(() => publicFolderUrl.value || '');

async function copyFolderPath() {
  const text = folderPath.value;
  try {
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(text);
    } else {
      const ta = document.createElement('textarea');
      ta.value = text;
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
    }
  } catch (e) {
    console.error('Copy failed', e);
  }
}

function yandexFolderPath() {
  if (!oc.value.brandName || !currentSubtask.value?.name) return null;
  // New format: BRAND/ф_SubtaskName or BRAND/д_SubtaskName (no Task folder)
  const prefix = oc.value.ownership === 'Photographer' ? 'ф_' : 'д_';
  return `disk:/${oc.value.brandName}/${prefix}${currentSubtask.value.name}`;
}

function yandexBrowserUrl() {
  if (!oc.value.brandName || !currentSubtask.value?.name) return '';
  const prefix = oc.value.ownership === 'Photographer' ? 'ф_' : 'д_';
  const parts = [oc.value.brandName, `${prefix}${currentSubtask.value.name}`];
  const encoded = parts.map(p => encodeURIComponent(p));
  return `https://disk.yandex.ru/client/disk/${encoded.join('/')}`;
}

async function loadYandexFiles() {
  const path = yandexFolderPath();
  if (!path) return;
  filesLoading.value = true;
  filesError.value = '';
  try {
    // Ensure folder exists and is published, capture public_url
    try {
      const resCreate = await fetch(route('integrations.yandex.create_folder'), {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ path }),
      });
      if (resCreate.ok) {
        const created = await resCreate.json();
        publicFolderUrl.value = created?.public_url || '';
      }
    } catch (e) { /* ignore publish errors */ }

    const url = route('integrations.yandex.list') + `?path=${encodeURIComponent(path)}&limit=100`;
    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    yandexItems.value = (data && data._embedded && Array.isArray(data._embedded.items)) ? data._embedded.items : [];
  } catch (e) {
    console.error(e);
    filesError.value = 'Не удалось загрузить список файлов.';
    yandexItems.value = [];
  } finally {
    filesLoading.value = false;
  }
}

async function downloadYandexItem(item) {
  if (!item || item.type !== 'file') return;
  const path = item.path || item.resource_id || null;
  // item.path usually like "disk:/..."; if missing, rebuild from name + parent path
  let reqPath = item.path;
  if (!reqPath) {
    const folder = yandexFolderPath();
    if (!folder) return;
    reqPath = `${folder}/${item.name}`;
  }
  const url = route('integrations.yandex.download_url') + `?path=${encodeURIComponent(reqPath)}`;
  try {
    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    if (data && data.href) {
      window.open(data.href, '_blank');
    }
  } catch (e) { console.error(e); }
}

function openOffcanvasTab(task, ownership, tab) {
  const brandName = task.brand?.name || (props.brands.find(b => b.id === task.brand_id)?.name) || '';
  oc.value = {
    brandId: task.brand_id,
    brandName,
    taskId: task.id,
    taskName: task.name,
    ownership,
  };
  offcanvasOpen.value = true;
  // Load comments for the selected subtask
  activeOcTab.value = tab || 'comments';
  subtaskId.value = null;
  currentSubtask.value = null;
  comments.value = [];
  newComment.value = '';
  // Reset assignee until subtask loads
  selectedAssigneeId.value = null;
  lastAssigneeId = null;
  loadSubtaskAndComments(task.id, ownership);
  if (offcanvasInstance) offcanvasInstance.show();
}

function openOffcanvas(task, ownership) { openOffcanvasTab(task, ownership, 'comments'); }
function openFilesOffcanvas(task, ownership) { openOffcanvasTab(task, ownership, 'files'); }

async function loadSubtaskAndComments(taskId, ownership) {
  try {
    commentsLoading.value = true;
    // fetch subtasks and pick by ownership
    const url = route('brands.tasks.subtasks.index', { brand: oc.value.brandId, task: taskId });
    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
    const data = await res.json();
    const st = (data.subtasks || []).find(s => s.ownership === ownership);
    currentSubtask.value = st || null;
    subtaskId.value = st ? st.id : null;
    // Initialize assignee state from subtask
    const aid = st?.assignee_id ?? null;
    selectedAssigneeId.value = aid;
    lastAssigneeId = aid;
    await loadComments();
    await loadYandexFiles();
  } catch (e) {
    console.error(e);
  } finally {
    commentsLoading.value = false;
  }
}

async function loadComments() {
  if (!subtaskId.value) { comments.value = []; return; }
  const url = route('brands.tasks.subtasks.comments.index', { brand: oc.value.brandId, task: oc.value.taskId, subtask: subtaskId.value });
  const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
  comments.value = await res.json();
}

async function addComment() {
  if (!subtaskId.value || !newComment.value.trim()) return;
  submitting.value = true;
  try {
    const url = route('brands.tasks.subtasks.comments.store', { brand: oc.value.brandId, task: oc.value.taskId, subtask: subtaskId.value });
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') },
      body: JSON.stringify({ content: newComment.value.trim() }),
    });
    const data = await res.json();
    if (data && data.comment) comments.value.push(data.comment);
    newComment.value = '';
  } catch (e) { console.error(e); }
  finally { submitting.value = false; }
}

async function deleteComment(c) {
  if (!subtaskId.value) return;
  const url = route('brands.tasks.subtasks.comments.destroy', { brand: oc.value.brandId, task: oc.value.taskId, subtask: subtaskId.value, comment: c.id });
  try {
    await fetch(url, { method: 'DELETE', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') } });
    comments.value = comments.value.filter(x => x.id !== c.id);
  } catch (e) { console.error(e); }
}

function downloadTaskFiles() {
  if (!oc.value.taskId) return;
  window.location.href = route('brands.tasks.download', { brand: oc.value.brandId, task: oc.value.taskId });
}

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

    <div class="row g-3 align-items-end mb-3">
      <div class="col-12 col-md-4">
        <label class="form-label">Бренд</label>
        <select class="form-select" v-model="brandFilter">
          <option value="">Все бренды</option>
          <option v-for="b in brands" :key="b.id" :value="String(b.id)">{{ b.name }}</option>
        </select>
      </div>
      <div class="col-12 col-md-5">
        <label class="form-label">Поиск по заданиям</label>
        <input type="text" class="form-control" v-model="search" placeholder="Введите наименование задания" />
      </div>
      <div class="col-12 col-md-3 text-md-end">
        <button class="btn btn-primary" @click="openCreate">Добавить задание</button>
      </div>
    </div>

    <div class="card">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-vcenter card-table">
            <thead>
              <tr>
                <th>Задание</th>
                <th>Бренд</th>
                <th class="w-1">Фотограф</th>
                <th class="w-1">Дизайнер</th>
                <th>Создан</th>
                <th class="w-1">ДЕЙСТВИЯ</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="displayedTasks.length === 0">
                <td colspan="6" class="text-center text-secondary py-4">Нет заданий</td>
              </tr>
              <tr v-for="t in displayedTasks" :key="t.id">
                <td>{{ t.name }}</td>
                <td>{{t.brand?.name || (brands.find(b => b.id === t.brand_id)?.name)}}</td>
                <td class="text-nowrap">
                  <div class="btn-list d-flex flex-nowrap align-items-center gap-2" style="white-space: nowrap;">
                    <template v-if="subtaskByOwnership(t, 'Photographer')">
                      <span v-if="subtaskByOwnership(t, 'Photographer').assignee_id" class="me-1 text-secondary">
                        {{ assigneeNameById('Photographer', subtaskByOwnership(t, 'Photographer').assignee_id) }}
                      </span>
                      <button class="btn btn-sm text-light"
                        :class="statusClass(subtaskByOwnership(t, 'Photographer').status, !!subtaskByOwnership(t, 'Photographer').assignee_id)"
                        @click="openOffcanvas(t, 'Photographer')"
                        title="Открыть подзадачу: ФОТОГРАФ (Информация)">
                        {{ statusLabel(subtaskByOwnership(t, 'Photographer').status, !!subtaskByOwnership(t, 'Photographer').assignee_id) }}
                      </button>
                    </template>
                    <button class="btn btn-opacity-primary btn-sm" @click="openFilesOffcanvas(t, 'Photographer')"
                      title="Открыть подзадачу: ФОТОГРАФ (Файлы)">
                      Файлы
                    </button>
                  </div>
                </td>
                <td class="text-nowrap">
                  <div class="btn-list d-flex flex-nowrap align-items-center gap-2" style="white-space: nowrap;">
                    <template v-if="subtaskByOwnership(t, 'PhotoEditor')">
                      <span v-if="subtaskByOwnership(t, 'PhotoEditor').assignee_id" class="me-1 text-secondary">
                        {{ assigneeNameById('PhotoEditor', subtaskByOwnership(t, 'PhotoEditor').assignee_id) }}
                      </span>
                      <button class="btn btn-sm text-light"
                        :class="statusClass(subtaskByOwnership(t, 'PhotoEditor').status, !!subtaskByOwnership(t, 'PhotoEditor').assignee_id)"
                        @click="openOffcanvas(t, 'PhotoEditor')"
                        title="Открыть подзадачу: ФОТОРЕДАКТОР (Информация)">
                        {{ statusLabel(subtaskByOwnership(t, 'PhotoEditor').status, !!subtaskByOwnership(t, 'PhotoEditor').assignee_id) }}
                      </button>
                    </template>
                    <button class="btn btn-opacity-purple btn-sm" @click="openFilesOffcanvas(t, 'PhotoEditor')"
                      title="Открыть подзадачу: ФОТОРЕДАКТОР (Файлы)">
                      Файлы
                    </button>
                  </div>
                </td>
                <td>{{ new Date(t.created_at).toLocaleString('ru-RU') }}</td>
                <td class="text-nowrap">
                  <div class="btn-list d-flex flex-nowrap align-items-center gap-2">
                    <button class="btn btn-icon btn-ghost-primary" @click="onEditTask(t)" title="Редактировать">
                      <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24"
                        stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round"
                        stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M4 20h4l10.5 -10.5a2.828 2.828 0 1 0 -4 -4l-10.5 10.5v4" />
                        <path d="M13.5 6.5l4 4" />
                      </svg>
                    </button>
                    <button class="btn btn-icon btn-ghost-danger" @click="onDeleteTask(t)" title="Удалить">
                      <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="16" height="16" viewBox="0 0 24 24"
                        stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round"
                        stroke-linejoin="round">
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
    </div>

    <!-- Right offcanvas -->
    <teleport to="body">
      <div class="offcanvas offcanvas-end w-50" ref="offcanvasEl" tabindex="-1" role="dialog"
        :class="{ show: offcanvasOpen && !hasOffcanvas }"
        :style="offcanvasOpen && !hasOffcanvas ? 'visibility: visible; z-index: 1045;' : ''">
        <div class="offcanvas-header">
          <h5 class="offcanvas-title">
            {{ oc.brandName }} / {{ oc.taskName }}<br />
            <span class="badge text-light" :class="oc.ownership === 'Photographer' ? 'bg-blue' : 'bg-purple'">
              {{ oc.ownership === 'Photographer' ? 'Фотограф' : 'Фоторедактор' }}
            </span>
          </h5>
          <button type="button" class="btn-close text-reset" aria-label="Close" @click="closeOffcanvas"></button>
        </div>
        <div class="offcanvas-body">
          <div class="mb-3">
            <ul class="nav nav-pills">
              <li class="nav-item">
                <button type="button" class="nav-link" :class="{ active: activeOcTab === 'comments' }"
                  @click="activeOcTab = 'comments'">
                  Информация
                </button>
              </li>
              <li class="nav-item">
                <button type="button" class="nav-link" :class="{ active: activeOcTab === 'files' }"
                  @click="activeOcTab = 'files'">
                  Файлы
                </button>
              </li>
            </ul>
          </div>

          <div v-if="activeOcTab === 'files'">

            <!-- Folder path with copy (browser URL) -->
            <div class="mb-3">
              <label class="form-label">URL папки (для просмотра в браузере)</label>
              <div class="input-group">
                <input type="text" class="form-control" :value="folderPath" readonly />
                <button class="btn btn-outline-secondary" type="button" @click="copyFolderPath">Копировать</button>
                <button class="btn btn-secondary" type="button" @click="openFolderUrl">Перейти</button>
              </div>
            </div>

            <div v-if="filesLoading" class="text-secondary">Загрузка списка файлов…</div>
            <div v-else>
              <div v-if="filesError" class="text-danger">{{ filesError }}</div>
              <div v-else>
                <div v-if="!yandexItems.length" class="text-secondary">Файлы не найдены.</div>
                <ul v-else class="list-group">
                  <li v-for="it in yandexItems" :key="it.resource_id || it.path || it.name"
                    class="list-group-item d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                      <span class="badge" :class="it.type === 'dir' ? 'bg-secondary' : 'bg-primary'">{{ it.type ===
                        'dir' ?
                        'Папка' : 'Файл' }}</span>
                      <span>{{ it.name }}</span>
                      <span v-if="it.size && it.type === 'file'" class="text-secondary small">{{
                        (it.size / 1024 / 1024).toFixed(2) }} MB</span>
                    </div>
                    <div>
                      <button v-if="it.type === 'file'" class="btn btn-sm btn-outline-primary"
                        @click="() => downloadYandexItem(it)">Скачать</button>
                    </div>
                  </li>
                </ul>
              </div>
            </div>

            <!-- Photographer upload UI moved below the list -->
            <div v-if="oc.ownership === 'Photographer'" class="mt-3 d-flex align-items-center gap-2">
              <input type="file" accept="image/*" multiple ref="fileInputRef" class="d-none" @change="onFilesChosen" />
              <button class="btn btn-primary" :disabled="uploading" @click="openUploader">
                <span v-if="!uploading">Загрузить фото</span>
                <span v-else>Загрузка…</span>
              </button>
              <span v-if="uploadError" class="text-danger small">{{ uploadError }}</span>
            </div>
          </div>

          <div v-else>
            <!-- Current subtask status and actions -->
            <div class="mb-3" v-if="currentSubtask">
              <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="text-secondary">Статус:</span>
                <span class="badge text-light"
                  :class="statusClass(currentSubtask.status, !!currentSubtask.assignee_id)">
                  {{ statusLabel(currentSubtask.status, !!currentSubtask.assignee_id) }}
                </span>
                <template v-if="currentSubtask.status === 'on_review'">
                  <button class="btn btn-success btn-sm" @click="acceptSubtask">Принять</button>
                  <button class="btn btn-danger btn-sm" @click="rejectSubtask">Отклонить</button>
                </template>
              </div>
            </div>
            <!-- Executor dropdown + action button -->
            <div class="mb-3 d-flex gap-2 align-items-end">
              <div class="flex-grow-1">
                <label class="form-label">Исполнитель ({{ oc.ownership === 'Photographer' ? 'Фотограф' : 'Фоторедактор'
                  }})</label>
                <select class="form-select" v-model="selectedAssigneeId">
                  <option :value="null">— Не назначено —</option>
                  <option v-for="u in assigneeOptions[oc.ownership]" :key="u.id" :value="u.id">{{ assigneeLabel(u) }}
                  </option>
                </select>
              </div>
              <div>
                <button class="btn btn-primary" :disabled="!canSaveAssignee" @click="saveAssignee">{{
                  assigneeButtonLabel
                  }}</button>
              </div>
            </div>
            <div v-if="commentsLoading" class="text-secondary">Загрузка комментариев…</div>
            <div v-else>
              <div v-if="comments.length === 0" class="text-secondary mb-2">Комментариев пока нет.</div>
              <ul class="list-unstyled">
                <li v-for="c in comments" :key="c.id" class="mb-2 d-flex justify-content-between align-items-start">
                  <div>
                    <div class="fw-bold">{{ c.user?.name || '—' }} <span class="text-secondary small">{{ new
                      Date(c.created_at).toLocaleString('ru-RU') }}</span></div>
                    <div style="white-space: pre-wrap;">{{ c.content }}</div>
                  </div>
                  <button class="btn btn-ghost-danger btn-sm" title="Удалить" @click="deleteComment(c)">Удалить</button>
                </li>
              </ul>
              <div class="mt-2">
                <textarea v-model="newComment" rows="2" class="form-control"
                  placeholder="Новый комментарий…"></textarea>
                <div class="mt-2 d-flex justify-content-end">
                  <button class="btn btn-primary" :disabled="!newComment.trim() || submitting"
                    @click="addComment">Добавить</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <!-- Fallback backdrop when Bootstrap Offcanvas is not available -->
      <div v-if="offcanvasOpen && !hasOffcanvas" class="modal-backdrop fade show" style="z-index: 1040;"
        @click="closeOffcanvas"></div>
    </teleport>

    <!-- Reassign confirm Modal -->
    <teleport to="body">
      <div v-if="showReassign">
        <div class="modal modal-blur fade show d-block" tabindex="-1" role="dialog" style="z-index: 1050;">
          <div class="modal-dialog" role="document">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Переназначить исполнителя</h5>
                <button type="button" class="btn-close" aria-label="Close" @click="cancelReassign"></button>
              </div>
              <div class="modal-body">
                Переназначить исполнителя? Текущее назначение будет изменено.
              </div>
              <div class="modal-footer">
                <button type="button" class="btn me-auto" @click="cancelReassign">Отмена</button>
                <button type="button" class="btn btn-primary" @click="confirmReassign">Переназначить</button>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-backdrop fade show" style="z-index: 1040;" @click="cancelReassign"></div>
      </div>
    </teleport>

    <!-- Rename Task Modal -->
    <teleport to="body">
      <div v-if="showRename && renaming">
        <div class="modal modal-blur fade show d-block" tabindex="-1" role="dialog" style="z-index: 1050;">
          <div class="modal-dialog" role="document">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Переименовать задание</h5>
                <button type="button" class="btn-close" aria-label="Close" @click="cancelRename"></button>
              </div>
              <div class="modal-body">
                <div class="mb-2">
                  <label class="form-label">Новое наименование</label>
                  <input type="text" class="form-control" v-model="renameName" />
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn me-auto" @click="cancelRename">Отмена</button>
                <button type="button" class="btn btn-primary" :disabled="!renameName.trim()"
                  @click="submitRename">Сохранить</button>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-backdrop fade show" style="z-index: 1040;" @click="cancelRename"></div>
      </div>
    </teleport>

    <!-- Create Task Modal -->
    <teleport to="body">
      <div class="modal modal-blur fade" :class="{ show: modalOpen }" :style="modalOpen ? 'display:block;' : ''"
        tabindex="-1" role="dialog" aria-modal="true">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Новое задание</h5>
              <button type="button" class="btn-close" aria-label="Close" @click="modalOpen = false"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label">Наименование</label>
                <input type="text" class="form-control" v-model="createForm.name" placeholder="Введите наименование" />
              </div>
              <div class="mb-3">
                <label class="form-label">Бренд</label>
                <select class="form-select" v-model="createForm.brand_id">
                  <option value="" disabled>Выберите бренд</option>
                  <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
                </select>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-link" @click="modalOpen = false">Отмена</button>
              <button type="button" class="btn btn-primary" :disabled="!createForm.name || !createForm.brand_id"
                @click="submitCreate">Создать</button>
            </div>
          </div>
        </div>
      </div>
    </teleport>

    <!-- Delete Task Modal -->
    <teleport to="body">
      <div v-if="showDelete && deleting">
        <div class="modal modal-blur fade show d-block" tabindex="-1" role="dialog" style="z-index: 1050;">
          <div class="modal-dialog" role="document">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Удалить задание</h5>
                <button type="button" class="btn-close" aria-label="Close" @click="cancelDelete"></button>
              </div>
              <div class="modal-body">
                Вы уверены, что хотите удалить задание <strong>{{ deleting?.name }}</strong>? Это действие необратимо.
              </div>
              <div class="modal-footer">
                <button type="button" class="btn me-auto" @click="cancelDelete">Отмена</button>
                <button type="button" class="btn btn-danger" @click="submitDelete">Удалить</button>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-backdrop fade show" style="z-index: 1040;" @click="cancelDelete"></div>
      </div>
    </teleport>
  </TablerLayout>
</template>
