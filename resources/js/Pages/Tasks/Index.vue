<script setup>
import TablerLayout from '@/Layouts/TablerLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, onMounted } from 'vue';

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

// (moved below with offcanvas state)

// Offcanvas state and opener (match All.vue)
const offcanvasOpen = ref(false);
const oc = ref({ brandId: null, brandName: '', taskId: null, taskName: '', ownership: 'Photographer' });
const activeOcTab = ref('comments'); // files|comments
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

function closeOffcanvas() {
  if (offcanvasInstance) offcanvasInstance.hide();
  else offcanvasOpen.value = false;
}

// Assignee selection for offcanvas
const assigneeOptions = computed(() => ({
  Photographer: props.assignees?.Photographer || [],
  PhotoEditor: props.assignees?.PhotoEditor || [],
}));
const selectedAssigneeId = ref(null);
let lastAssigneeId = null;
const canSaveAssignee = computed(() => (selectedAssigneeId.value ?? null) !== (lastAssigneeId ?? null));
const assigneeButtonLabel = computed(() => lastAssigneeId ? 'Изменить' : 'Сохранить');
function saveAssignee() {
  const newId = selectedAssigneeId.value ? Number(selectedAssigneeId.value) : null;
  if (lastAssigneeId && lastAssigneeId !== newId) {
    const ok = window.confirm('Переназначить исполнителя? Текущее назначение будет изменено.');
    if (!ok) return;
  }
  if (!oc.value.taskId || !subtaskId.value) return;
  router.put(
    route('brands.tasks.subtasks.update', { brand: oc.value.brandId, task: oc.value.taskId, subtask: subtaskId.value }),
    { assignee_id: newId },
    {
      preserveScroll: true,
      onSuccess: () => { lastAssigneeId = newId; },
      onError: () => { selectedAssigneeId.value = lastAssigneeId; },
    }
  );
}

// Yandex.Disk files state (as in All.vue)
const filesLoading = ref(false);
const filesError = ref('');
const yandexItems = ref([]);
const publicFolderUrl = ref('');

// Upload state (Photographer) for Yandex area
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
  } catch (e) { console.error('Copy failed', e); }
}

function openFolderUrl() {
  const url = folderPath.value;
  if (!url) return;
  window.open(url, '_blank');
}

function yandexFolderPath() {
  if (!oc.value.brandName || !oc.value.taskName) return null;
  const base = `${oc.value.brandName}/${oc.value.taskName}`;
  const suffix = oc.value.ownership === 'Photographer' ? `${oc.value.taskName}_Ф` : `${oc.value.taskName}_Р`;
  return `disk:/${base}/${suffix}`;
}

function yandexBrowserUrl() {
  if (!oc.value.brandName || !oc.value.taskName) return '';
  const parts = [
    oc.value.brandName,
    oc.value.taskName,
    oc.value.ownership === 'Photographer' ? `${oc.value.taskName}_Ф` : `${oc.value.taskName}_Р`,
  ];
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
    } catch (e) { /* ignore publish errors to still show listing */ }

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
    if (data && data.href) window.open(data.href, '_blank');
  } catch (e) { console.error(e); }
}

function openUploader() {
  uploadError.value = '';
  if (fileInputRef.value) fileInputRef.value.value = null;
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
  } finally { uploading.value = false; }
}

function openOffcanvas(task, ownership) {
  const brandName = props.brand?.name || task.brand?.name || '';
  oc.value = {
    brandId: task.brand_id,
    brandName,
    taskId: task.id,
    taskName: task.name,
    ownership,
  };
  offcanvasOpen.value = true;
  // Load comments for the selected subtask
  activeOcTab.value = 'comments';
  subtaskId.value = null;
  currentSubtask.value = null;
  comments.value = [];
  newComment.value = '';
  // Reset assignee until subtask loads
  selectedAssigneeId.value = null;
  lastAssigneeId = null;
  loadSubtaskAndComments(task.id, ownership);
  loadYandexFiles();
  if (offcanvasInstance) offcanvasInstance.show();
}

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
              <th>Статус</th>
              <th class="w-1">ПОДЗАДАНИЯ</th>
              <th>Создан</th>
              <th class="w-1">ДЕЙСТВИЯ</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="displayed.length === 0">
              <td colspan="6" class="text-center text-secondary py-4">Задачи отсутствуют</td>
            </tr>
            <tr v-for="t in displayed" :key="t.id">
              <td>{{ t.name }}</td>
              <td>{{ brand.name }}</td>
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
      <div class="offcanvas offcanvas-end w-50" ref="offcanvasEl" tabindex="-1" role="dialog"
        :class="{ show: offcanvasOpen && !hasOffcanvas }"
        :style="offcanvasOpen && !hasOffcanvas ? 'visibility: visible; z-index: 1045;' : ''">
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
          <button type="button" class="btn-close text-reset" aria-label="Close" @click="closeOffcanvas"></button>
        </div>
        <div class="offcanvas-body">
          <div class="mb-3">
            <ul class="nav nav-pills">
              <li class="nav-item">
                <button type="button" class="nav-link" :class="{ active: activeOcTab === 'comments' }"
                  @click="activeOcTab = 'comments'">Информация</button>
              </li>
              <li class="nav-item">
                <button type="button" class="nav-link" :class="{ active: activeOcTab === 'files' }"
                  @click="activeOcTab = 'files'">Файлы</button>
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
                      <span v-if="it.size && it.type === 'file'" class="text-secondary small">{{ (it.size / 1024 /
                        1024).toFixed(2) }} MB</span>
                    </div>
                    <div>
                      <button v-if="it.type === 'file'" class="btn btn-sm btn-outline-primary"
                        @click="() => downloadYandexItem(it)">Скачать</button>
                    </div>
                  </li>
                </ul>
              </div>
            </div>

            <!-- Photographer upload UI below the list -->
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
            <!-- Executor dropdown -->
            <div class="mb-3">
              <label class="form-label">Исполнитель ({{ oc.ownership === 'Photographer' ? 'Фотограф' : 'Фоторедактор' }})</label>
              <select class="form-select" v-model="selectedAssigneeId" @change="onAssigneeChanged">
                <option :value="null">— Не назначено —</option>
                <option v-for="u in assigneeOptions[oc.ownership]" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
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
      <div v-if="offcanvasOpen && !hasOffcanvas" class="modal-backdrop fade show" style="z-index: 1040;" @click="closeOffcanvas"></div>
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
