<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import TablerLayout from '@/Layouts/TablerLayout.vue';

const props = defineProps({
  tasks: { type: Array, required: true },
  brands: { type: Array, required: true },
  performers: { type: Array, default: () => [] },
  taskTypes: { type: Array, default: () => [] },
  initialBrandId: { type: [Number, String], default: null },
});

// Filters
// Name search (existing)
const search = ref('');
// Global search across fields
const globalSearch = ref('');
// Brand and dependent Article
const brandFilter = ref(''); // brand id as string for select
const articleFilter = ref(''); // article id as string for select
const filterArticles = ref([]); // options based on brandFilter
// Executor
const performerFilter = ref(''); // user id as string
// Created date filter
// createdFilter: '' | 'today' | 'yesterday' | 'date'
const createdFilter = ref('');
const createdDate = ref(''); // yyyy-mm-dd
if (props.initialBrandId) brandFilter.value = String(props.initialBrandId);

// Load articles for filter when brand changes
watch(brandFilter, async (val) => {
  articleFilter.value = '';
  filterArticles.value = [];
  if (!val) return;
  try {
    const url = route('brands.articles.index', { brand: Number(val) });
    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
    const data = await res.json();
    filterArticles.value = Array.isArray(data?.data) ? data.data : [];
  } catch (e) { console.error(e); }
});

const displayedTasks = computed(() => {
  const nameQ = search.value.trim().toLowerCase();
  const gq = globalSearch.value.trim().toLowerCase();
  const brandId = brandFilter.value ? Number(brandFilter.value) : null;
  const artId = articleFilter.value ? Number(articleFilter.value) : null;
  const perfId = performerFilter.value ? Number(performerFilter.value) : null;

  // Build date boundaries
  let byDateFn = () => true;
  if (createdFilter.value === 'today' || createdFilter.value === 'yesterday' || (createdFilter.value === 'date' && createdDate.value)) {
    const now = new Date();
    let start, end;
    if (createdFilter.value === 'today') {
      start = new Date(now.getFullYear(), now.getMonth(), now.getDate());
      end = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1);
    } else if (createdFilter.value === 'yesterday') {
      start = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 1);
      end = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    } else {
      // specific date
      const [y, m, d] = createdDate.value.split('-').map(Number);
      if (!isNaN(y) && !isNaN(m) && !isNaN(d)) {
        start = new Date(y, m - 1, d);
        end = new Date(y, m - 1, d + 1);
      }
    }
    if (start && end) {
      byDateFn = (t) => {
        const dt = new Date(t.created_at);
        return dt >= start && dt < end;
      };
    }
  }

  const filtered = props.tasks.filter(t => {
    // Brand
    if (brandId && t.brand_id !== brandId) return false;
    // Article (only applies if brand selected)
    if (brandId && artId && t.article_id !== artId) return false;
    // Executor
    if (perfId && t.assignee_id !== perfId) return false;
    // Date
    if (!byDateFn(t)) return false;
    // Name search
    if (nameQ && !String(t.name || '').toLowerCase().includes(nameQ)) return false;
    // Global search across fields
    if (gq) {
      const blob = [
        t.name,
        t.article?.name,
        t.brand?.name,
        t.type?.name,
        t.assignee?.name,
        t.public_link,
      ].map(x => String(x || '').toLowerCase());
      const gmatch = blob.some(val => val.includes(gq));
      if (!gmatch) return false;
    }
    return true;
  });
  // Sort newest first
  return filtered.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
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

// Public link helpers
async function ensurePublicLink(task) {
  if (task.public_link) return true;
  try {
    await useForm({}).post(route('brands.tasks.public_link', { brand: task.brand_id, task: task.id }), { preserveScroll: true });
    return true;
  } catch (e) { console.error(e); return false; }
}
async function copyTaskPublicLink(task) {
  if (!task) return;
  if (!task.public_link) {
    const ok = await ensurePublicLink(task);
    if (!ok) return;
    // Backend created link but local object may not be updated immediately
    if (!task.public_link) {
      alert('Публичная ссылка создана. Нажмите «Скопировать» ещё раз.');
      return;
    }
  }
  try {
    const text = task.public_link;
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
function openTaskPublicLink(task) {
  if (!task) return;
  if (task.public_link) {
    window.open(task.public_link, '_blank');
  } else {
    // create and ask user to click again
    useForm({}).post(route('brands.tasks.public_link', { brand: task.brand_id, task: task.id }), { preserveScroll: true });
  }
}

// Status helpers
function statusLabel(status) {
  switch (status) {
    case 'created': return 'Создано';
    case 'assigned': return 'Назначено';
    case 'on_review': return 'На проверке';
    case 'rework': return 'На доработку';
    case 'accepted': return 'Принято';
    case 'done': return 'Принято'; // backward compatibility
    default: return 'Создано';
  }
}
function statusClass(status) {
  switch (status) {
    case 'created': return 'bg-secondary';
    case 'assigned': return 'bg-primary';
    case 'on_review': return 'bg-warning';
    case 'rework': return 'bg-danger';
    case 'accepted': return 'bg-success';
    case 'done': return 'bg-success'; // backward compatibility
    default: return 'bg-secondary';
  }
}

const statusOptions = [
  { value: 'created', label: 'Создано' },
  { value: 'assigned', label: 'Назначено' },
  { value: 'on_review', label: 'На проверке' },
  { value: 'rework', label: 'На доработку' },
  { value: 'accepted', label: 'Принято' },
];

function updateTaskStatus(task, status) {
  if (!task || !status) return;
  router.put(route('brands.tasks.update', { brand: task.brand_id, task: task.id }), { status }, { preserveScroll: true });
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
const createForm = ref({ name: '', brand_id: '', task_type_id: '', article_id: '', assignee_id: '' });
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
  createForm.value = { name: '', brand_id: '', task_type_id: '', article_id: '', assignee_id: '' };
  brandArticles.value = [];
  modalOpen.value = true;
}









async function submitCreate() {
  const payload = {
    brand_id: createForm.value.brand_id ? Number(createForm.value.brand_id) : null,
    task_type_id: createForm.value.task_type_id ? Number(createForm.value.task_type_id) : null,
    article_id: createForm.value.article_id ? Number(createForm.value.article_id) : null,
    name: createForm.value.name?.trim() || undefined,
    assignee_id: createForm.value.assignee_id ? Number(createForm.value.assignee_id) : null,
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

// Offcanvas state and opener (RESULT: comments/files)
const offcanvasOpen = ref(false);
const oc = ref({ brandId: null, brandName: '', taskId: null, taskName: '' });
const activeOcTab = ref('comments'); // comments|files
const commentsLoading = ref(false);
const comments = ref([]);
const newComment = ref('');
const submitting = ref(false);

// Comment image upload
const commentImageInput = ref(null);
const selectedCommentImage = ref(null);

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

function openCommentsOffcanvas(task) {
  const brandName = task.brand?.name || (props.brands.find(b => b.id === task.brand_id)?.name) || '';
  oc.value = {
    brandId: task.brand_id,
    brandName,
    taskId: task.id,
    taskName: task.name || task.article?.name || '',
  };
  offcanvasOpen.value = true;
  activeOcTab.value = 'comments';
  comments.value = [];
  newComment.value = '';
  loadComments();
  if (offcanvasInstance) offcanvasInstance.show();
}

async function openFilesOffcanvas(task) {
  const brandName = task.brand?.name || (props.brands.find(b => b.id === task.brand_id)?.name) || '';
  oc.value = {
    brandId: task.brand_id,
    brandName,
    taskId: task.id,
    taskName: task.name || task.article?.name || '',
  };

  // Check if public_link exists, if not, publish the folder and update the task
  if (!task.public_link) {
    try {
      const brandNameSanitized = sanitizeName(brandName);
      const typeName = sanitizeName(task.type?.name || 'Type');
      const articleName = sanitizeName(task.article?.name || task.name || 'Article');
      const prefix = typeName.charAt(0);
      const path = `/${brandNameSanitized}/${typeName}/${prefix}_${articleName}`;

      const response = await fetch(route('integrations.yandex.publish_folder'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        },
        body: JSON.stringify({ path }),
      });

      if (response.ok) {
        const data = await response.json();
        if (data.href) {
          // Update the task's public_link in the database
          const updateResponse = await fetch(route('tasks.update_public_link', { task: task.id }), {
            method: 'PUT',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
            body: JSON.stringify({ public_link: data.href }),
          });

          if (updateResponse.ok) {
            // Update the task data on the client side
            task.public_link = data.href;
          }
        }
      }
    } catch (error) {
      console.error('Error publishing folder:', error);
    }
  }

  offcanvasOpen.value = true;
  activeOcTab.value = 'files';
  loadYandexFiles();
  if (offcanvasInstance) offcanvasInstance.show();
}

async function loadComments() {
  if (!oc.value.taskId) { comments.value = []; return; }
  const url = route('brands.tasks.comments.index', { brand: oc.value.brandId, task: oc.value.taskId });
  const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
  comments.value = await res.json();
}

async function addComment() {
  if (!oc.value.taskId || (!newComment.value.trim() && !selectedCommentImage.value)) return;
  submitting.value = true;
  try {
    const url = route('brands.tasks.comments.store', { brand: oc.value.brandId, task: oc.value.taskId });

    let body;
    let headers = {
      'Accept': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
    };

    if (selectedCommentImage.value) {
      // Use FormData for file upload
      const formData = new FormData();
      formData.append('content', newComment.value.trim() || ''); // Allow empty content
      formData.append('image', selectedCommentImage.value);
      body = formData;
    } else {
      // Use JSON for text-only comments
      headers['Content-Type'] = 'application/json';
      body = JSON.stringify({ content: newComment.value.trim() });
    }

    const res = await fetch(url, {
      method: 'POST',
      headers,
      body,
    });

    if (!res.ok) {
      const errorData = await res.json();
      if (errorData.errors && errorData.errors.content) {
        alert(errorData.errors.content[0]);
        return;
      }
      throw new Error(`HTTP ${res.status}`);
    }

    const data = await res.json();
    if (data && data.comment) comments.value.push(data.comment);
    clearCommentForm();
  } catch (e) {
    console.error(e);
    alert('Ошибка при добавлении комментария. Попробуйте ещё раз.');
  }
  finally { submitting.value = false; }
}

function onCommentImageSelected(event) {
  const file = event.target.files[0];
  if (file) {
    selectedCommentImage.value = file;
  }
}

function clearCommentForm() {
  newComment.value = '';
  selectedCommentImage.value = null;
  if (commentImageInput.value) {
    commentImageInput.value.value = null;
  }
}

async function deleteComment(c) {
  if (!oc.value.taskId) return;
  const url = route('brands.tasks.comments.destroy', { brand: oc.value.brandId, task: oc.value.taskId, comment: c.id });
  try {
    await fetch(url, { method: 'DELETE', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') } });
    comments.value = comments.value.filter(x => x.id !== c.id);
  } catch (e) { console.error(e); }
}

// Yandex Disk functions
function openFolder(task) {
  if (task.public_link) {
    window.open(task.public_link, '_blank');
  } else {
    alert('Ссылка на папку не найдена.');
  }
}

// Yandex files state
const filesLoading = ref(false);
const filesError = ref('');
const yandexItems = ref([]);
const publicFolderUrl = ref('');

// Upload state
const fileInputRef = ref(null);
const uploading = ref(false);
const uploadError = ref('');

async function copyFolderPath() {
  const text = publicFolderUrl.value;
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
  const url = publicFolderUrl.value;
  if (!url) return;
  window.open(url, '_blank');
}

function yandexFolderPath() {
  if (!oc.value.brandName || !oc.value.taskName) return null;
  // Use task name as folder identifier
  return `disk:/${oc.value.brandName}/${oc.value.taskName}`;
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

// Source Offcanvas (BRAND / Исходник) with only Comments tab
const sourceOffcanvasOpen = ref(false);
const sourceOc = ref({ brandId: null, brandName: '', taskId: null, taskName: '' });
const sourceComments = ref([]);
const newSourceComment = ref('');
const sourceSubmitting = ref(false);
const sourceOffcanvasEl = ref(null);
let sourceOffcanvasInstance = null;
const hasSourceOffcanvas = ref(false);
onMounted(() => {
  const Ctor = window.bootstrap?.Offcanvas;
  if (Ctor && sourceOffcanvasEl.value) {
    sourceOffcanvasInstance = new Ctor(sourceOffcanvasEl.value, { backdrop: true, keyboard: true, scroll: true });
    hasSourceOffcanvas.value = true;
    sourceOffcanvasEl.value.addEventListener('show.bs.offcanvas', () => { sourceOffcanvasOpen.value = true; });
    sourceOffcanvasEl.value.addEventListener('hidden.bs.offcanvas', () => { sourceOffcanvasOpen.value = false; });
  }
});

function closeSourceOffcanvas() {
  if (sourceOffcanvasInstance) sourceOffcanvasInstance.hide();
  else sourceOffcanvasOpen.value = false;
}

function openSourceCommentsOffcanvas(task) {
  const brandName = task.brand?.name || (props.brands.find(b => b.id === task.brand_id)?.name) || '';
  sourceOc.value = {
    brandId: task.brand_id,
    brandName,
    taskId: task.id,
    taskName: task.name || task.article?.name || '',
  };
  sourceComments.value = [];
  newSourceComment.value = '';
  sourceOffcanvasOpen.value = true;
  loadSourceComments();
  if (sourceOffcanvasInstance) sourceOffcanvasInstance.show();
}

async function loadSourceComments() {
  if (!sourceOc.value.taskId) { sourceComments.value = []; return; }
  const url = route('brands.tasks.source_comments.index', { brand: sourceOc.value.brandId, task: sourceOc.value.taskId });
  const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
  sourceComments.value = await res.json();
}

async function addSourceComment() {
  if (!sourceOc.value.taskId || (!newSourceComment.value.trim() && !selectedCommentImage.value)) return;
  sourceSubmitting.value = true;
  try {
    const url = route('brands.tasks.source_comments.store', { brand: sourceOc.value.brandId, task: sourceOc.value.taskId });

    let body;
    let headers = {
      'Accept': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
    };

    if (selectedCommentImage.value) {
      const formData = new FormData();
      formData.append('content', newSourceComment.value.trim() || '');
      formData.append('image', selectedCommentImage.value);
      body = formData;
    } else {
      headers['Content-Type'] = 'application/json';
      body = JSON.stringify({ content: newSourceComment.value.trim() });
    }

    const res = await fetch(url, { method: 'POST', headers, body });
    if (!res.ok) {
      const errorData = await res.json();
      if (errorData.errors && errorData.errors.content) {
        alert(errorData.errors.content[0]);
        return;
      }
      throw new Error(`HTTP ${res.status}`);
    }

    const data = await res.json();
    if (data && data.comment) sourceComments.value.push(data.comment);
    clearSourceCommentForm();
  } catch (e) {
    console.error(e);
    alert('Ошибка при добавлении комментария. Попробуйте ещё раз.');
  } finally { sourceSubmitting.value = false; }
}

function clearSourceCommentForm() {
  newSourceComment.value = '';
  selectedCommentImage.value = null;
  if (commentImageInput.value) {
    commentImageInput.value.value = null;
  }
}

async function deleteSourceComment(c) {
  if (!sourceOc.value.taskId) return;
  const url = route('brands.tasks.source_comments.destroy', { brand: sourceOc.value.brandId, task: sourceOc.value.taskId, comment: c.id });
  try {
    await fetch(url, { method: 'DELETE', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') } });
    sourceComments.value = sourceComments.value.filter(x => x.id !== c.id);
  } catch (e) { console.error(e); }
}
</script>

<template>
  <TablerLayout>

    <div class="row row-deck">
      <div class="col-12">
        <div class="card mb-3">
          <!-- Card Header with Filters -->
          <div class="card-header">
            <div>
              <div class="card-title">Список заданий</div>
              <div class="card-subtitle">Просмотр и управление всеми заданиями.</div>
            </div>
            <div class="card-actions d-flex flex-wrap align-items-center">
              <!-- Global search -->
              <div class="input-group input-group-flat w-auto me-2">
                <span class="input-group-text"><i class="ti ti-search"></i></span>
                <input type="text" class="form-control" v-model="globalSearch" placeholder="Общий поиск..."
                  autocomplete="off" />
              </div>
              <!-- Name search -->
              <div class="input-group input-group-flat w-auto me-2">
                <span class="input-group-text"><i class="ti ti-letter-case"></i></span>
                <input type="text" class="form-control" v-model="search" placeholder="Название..." autocomplete="off" />
              </div>
              <!-- Brand filter -->
              <select class="form-select w-auto me-2" v-model="brandFilter">
                <option value="">Все бренды</option>
                <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
              </select>
              <!-- Article filter (dependent on brand) -->
              <select class="form-select w-auto me-2" v-model="articleFilter" :disabled="!brandFilter">
                <option value="">Все артикулы</option>
                <option v-for="a in filterArticles" :key="a.id" :value="a.id">{{ a.name }}</option>
              </select>
              <!-- Executor filter -->
              <select class="form-select w-auto me-2" v-model="performerFilter">
                <option value="">Все исполнители</option>
                <option v-for="u in performers" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
              <!-- Created date filter -->
              <select class="form-select w-auto me-2" v-model="createdFilter">
                <option value="">Все даты</option>
                <option value="today">Сегодня</option>
                <option value="yesterday">Вчера</option>
                <option value="date">Дата…</option>
              </select>
              <input v-if="createdFilter === 'date'" type="date" class="form-control w-auto me-2"
                v-model="createdDate" />

              <!-- Action buttons -->
              <button class="btn btn-primary" @click="openCreate">
                <i class="ti ti-plus"></i> Новое задание
              </button>
            </div>
          </div>
          <div class="card-header" v-if="anySelected">
            <!-- Bulk actions (only delete now) -->
            <div class="row">
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

          <div class="card-table">

            <!-- Table -->
            <div class="table-responsive">
              <table class="table table-vcenter card-table">
                <thead>
                  <tr>
                    <th class="w-1"><input type="checkbox" class="form-check-input" v-model="selectAllVisible" /></th>
                    <th>Создан</th>
                    <th>Название</th>
                    <th>Артикул</th>
                    <th>Бренд</th>
                    <th>Тип</th>
                    <th>Исполнитель</th>
                    <th>Исходник</th>
                    <th>Результат</th>
                    <th>Статус</th>
                    <th class="w-1">Действия</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="displayedTasks.length === 0">
                    <td colspan="11" class="text-center text-secondary py-4">Нет заданий</td>
                  </tr>
                  <tr v-for="t in displayedTasks" :key="t.id">
                    <td>
                      <input type="checkbox" class="form-check-input" :checked="isSelected(t.id)"
                        @change="toggleRow(t.id)" />
                    </td>
                    <td>{{ new Date(t.created_at).toLocaleString('ru-RU') }}</td>
                    <td>{{ t.name || t.article?.name || '' }}</td>
                    <td>{{ t.article?.name || '' }}</td>
                    <td>{{t.brand?.name || (brands.find(b => b.id === t.brand_id)?.name)}}</td>
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
                      <div class="d-flex gap-1">
                        <button class="btn btn-sm btn-outline-secondary"
                          @click="openSourceCommentsOffcanvas(t)">КОММЕНТАРИЙ</button>
                        <!-- <button class="btn btn-sm btn-outline-primary" @click="openFolder(t)">ФАЙЛЫ (скрыто)</button> -->
                      </div>
                    </td>
                    <td>
                      <div class="d-flex gap-1">
                        <button class="btn btn-sm btn-outline-secondary"
                          @click="openCommentsOffcanvas(t)">КОММЕНТАРИЙ</button>
                        <button class="btn btn-sm btn-outline-primary" @click="openFilesOffcanvas(t)">ФАЙЛЫ</button>
                      </div>
                    </td>
                    <td>
                      <div class="d-flex align-items-center gap-2">
                        <select class="form-select form-select-sm w-auto" :value="t.status"
                          @change="(e) => updateTaskStatus(t, e.target.value)">
                          <option v-for="s in statusOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                      </div>
                    </td>
                    <td class="text-nowrap">
                      <div class="btn-list d-flex flex-nowrap align-items-center gap-2">
                        <button class="btn btn-icon btn-ghost-secondary" @click="copyTaskPublicLink(t)"
                          title="Копировать ссылку">
                          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="icon icon-tabler icons-tabler-outline icon-tabler-copy">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path
                              d="M7 7m0 2.667a2.667 2.667 0 0 1 2.667 -2.667h8.666a2.667 2.667 0 0 1 2.667 2.667v8.666a2.667 2.667 0 0 1 -2.667 2.667h-8.666a2.667 2.667 0 0 1 -2.667 -2.667z" />
                            <path
                              d="M4.012 16.737a2.005 2.005 0 0 1 -1.012 -1.737v-10c0 -1.1 .9 -2 2 -2h10c.75 0 1.158 .385 1.5 1" />
                          </svg>
                        </button>
                        <button class="btn btn-icon btn-ghost-secondary" @click="openTaskPublicLink(t)"
                          title="Открыть ссылку">
                          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="icon icon-tabler icons-tabler-outline icon-tabler-link">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M9 15l6 -6" />
                            <path d="M11 6l.463 -.536a5 5 0 0 1 7.071 7.072l-.534 .464" />
                            <path
                              d="M13 18l-.397 .534a5.068 5.068 0 0 1 -7.127 0a4.972 4.972 0 0 1 0 -7.071l.524 -.463" />
                          </svg>
                        </button>
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
                <div class="col-md-12">
                  <label class="form-label">Исполнитель</label>
                  <select class="form-select" v-model="createForm.assignee_id">
                    <option value="">Не назначен</option>
                    <option v-for="u in performers" :key="u.id" :value="u.id">
                      {{ u.name }}<span v-if="u.is_blocked"> — ЗАБЛОКИРОВАН</span>
                    </option>
                  </select>
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

    <!-- Rename Task Modal -->
    <teleport to="body">
      <div class="modal modal-blur fade" :class="{ show: showRename }" :style="showRename ? 'display: block;' : ''"
        tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Переименовать задание</h5>
              <button type="button" class="btn-close" @click="cancelRename" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <label class="form-label">Новое название</label>
              <input type="text" class="form-control" v-model="renameName" @keyup.enter="submitRename" />
            </div>
            <div class="modal-footer">
              <button type="button" class="btn me-auto" @click="cancelRename">Отмена</button>
              <button type="button" class="btn btn-primary" @click="submitRename">Сохранить</button>
            </div>
          </div>
        </div>
      </div>
    </teleport>

    <!-- Delete Task Modal -->
    <teleport to="body">
      <div class="modal modal-blur fade" :class="{ show: showDelete }" :style="showDelete ? 'display: block;' : ''"
        tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Удалить задание</h5>
              <button type="button" class="btn-close" @click="cancelDelete" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <p>Действительно удалить задание «{{ deleting?.name || deleting?.article?.name }}»? Это действие
                необратимо.
              </p>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn me-auto" @click="cancelDelete">Отмена</button>
              <button type="button" class="btn btn-danger" @click="submitDelete">Удалить</button>
            </div>
          </div>
        </div>
      </div>
    </teleport>

    <!-- Right offcanvas -->
    <teleport to="body">
      <div class="offcanvas offcanvas-end w-50" id="task-offcanvas" ref="offcanvasEl" tabindex="-1" role="dialog"
        aria-hidden="true" :aria-labelledby="'task-offcanvas-title'" :class="{ show: offcanvasOpen && !hasOffcanvas }"
        :style="offcanvasOpen && !hasOffcanvas ? 'visibility: visible; z-index: 1045;' : ''">
        <div class="offcanvas-header">
          <h5 class="offcanvas-title" :id="'task-offcanvas-title'">
            {{ oc.brandName }} / {{ oc.taskName }}
          </h5>
          <button type="button" class="btn-close text-reset" aria-label="Close" @click="closeOffcanvas"></button>
        </div>
        <div class="offcanvas-body">
          <div class="mb-3">
            <ul class="nav nav-pills">
              <li class="nav-item">
                <button type="button" class="nav-link" :class="{ active: activeOcTab === 'comments' }"
                  @click="activeOcTab = 'comments'">
                  Комментарии
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
                <input type="text" class="form-control" :value="publicFolderUrl" readonly />
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
            <div v-if="commentsLoading" class="text-secondary">Загрузка комментариев…</div>
            <div v-else>
              <div v-if="comments.length === 0" class="text-secondary mb-2">Комментариев пока нет.</div>
              <ul class="list-unstyled">
                <li v-for="c in comments" :key="c.id" class="mb-3 d-flex justify-content-between align-items-start">
                  <div class="flex-grow-1">
                    <div class="fw-bold">{{ c.user?.name || '—' }} <span class="text-secondary small">{{ new
                      Date(c.created_at).toLocaleString('ru-RU') }}</span></div>
                    <div v-if="c.content" style="white-space: pre-wrap;">{{ c.content }}</div>
                    <div v-if="c.image_path" class="mt-2">
                      <img :src="'/storage/' + c.image_path" class="img-fluid rounded"
                        style="max-width: 300px; max-height: 200px;" />
                    </div>
                  </div>
                  <button class="btn btn-ghost-danger btn-sm ms-2" title="Удалить"
                    @click="deleteComment(c)">Удалить</button>
                </li>
              </ul>
              <div class="mt-3">
                <form @submit.prevent="addComment">
                  <div class="mb-2">
                    <textarea v-model="newComment" rows="2" class="form-control"
                      placeholder="Новый комментарий…"></textarea>
                  </div>
                  <div class="mb-2">
                    <input type="file" ref="commentImageInput" accept="image/*" class="form-control"
                      @change="onCommentImageSelected" />
                    <small class="text-secondary">Максимальный размер: 5MB</small>
                  </div>
                  <div class="d-flex justify-content-end">
                    <button type="button" class="btn btn-secondary me-2" @click="clearCommentForm">Очистить</button>
                    <button type="submit" class="btn btn-primary"
                      :disabled="!newComment.trim() && !selectedCommentImage || submitting">
                      Добавить
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
      <!-- Fallback backdrop when Bootstrap Offcanvas is not available -->
      <div v-if="offcanvasOpen && !hasOffcanvas" class="modal-backdrop fade show" style="z-index: 1040;"
        @click="closeOffcanvas"></div>
    </teleport>

    <!-- Source Offcanvas: BRAND / Исходник (COMMENTS only) -->
    <teleport to="body">
      <div class="offcanvas offcanvas-end w-50" id="task-source-offcanvas" ref="sourceOffcanvasEl" tabindex="-1"
        role="dialog" aria-hidden="true" :aria-labelledby="'task-source-offcanvas-title'"
        :class="{ show: sourceOffcanvasOpen && !hasSourceOffcanvas }"
        :style="sourceOffcanvasOpen && !hasSourceOffcanvas ? 'visibility: visible; z-index: 1045;' : ''">
        <div class="offcanvas-header">
          <h5 class="offcanvas-title" :id="'task-source-offcanvas-title'">
            {{ sourceOc.brandName }} / Исходник
          </h5>
          <button type="button" class="btn-close text-reset" aria-label="Close" @click="closeSourceOffcanvas"></button>
        </div>
        <div class="offcanvas-body">
          <div class="mb-3">
            <ul class="nav nav-pills">
              <li class="nav-item">
                <button type="button" class="nav-link active" disabled>
                  Комментарий
                </button>
              </li>
            </ul>
          </div>

          <div>
            <div v-if="commentsLoading" class="text-secondary">Загрузка комментариев…</div>
            <div v-else>
              <div v-if="sourceComments.length === 0" class="text-secondary mb-2">Комментариев пока нет.</div>
              <ul class="list-unstyled">
                <li v-for="c in sourceComments" :key="c.id"
                  class="mb-3 d-flex justify-content-between align-items-start">
                  <div class="flex-grow-1">
                    <div class="fw-bold">{{ c.user?.name || '—' }} <span class="text-secondary small">{{ new
                      Date(c.created_at).toLocaleString('ru-RU') }}</span></div>
                    <div v-if="c.content" style="white-space: pre-wrap;">{{ c.content }}</div>
                    <div v-if="c.image_path" class="mt-2">
                      <img :src="'/storage/' + c.image_path" class="img-fluid rounded"
                        style="max-width: 300px; max-height: 200px;" />
                    </div>
                  </div>
                  <button class="btn btn-ghost-danger btn-sm ms-2" title="Удалить"
                    @click="deleteSourceComment(c)">Удалить</button>
                </li>
              </ul>
              <div class="mt-3">
                <form @submit.prevent="addSourceComment">
                  <div class="mb-2">
                    <textarea v-model="newSourceComment" rows="2" class="form-control"
                      placeholder="Новый комментарий…"></textarea>
                  </div>
                  <div class="mb-2">
                    <input type="file" ref="commentImageInput" accept="image/*" class="form-control"
                      @change="onCommentImageSelected" />
                    <small class="text-secondary">Максимальный размер: 5MB</small>
                  </div>
                  <div class="d-flex justify-content-end">
                    <button type="button" class="btn btn-secondary me-2"
                      @click="clearSourceCommentForm">Очистить</button>
                    <button type="submit" class="btn btn-primary"
                      :disabled="!newSourceComment.trim() && !selectedCommentImage || sourceSubmitting">
                      Добавить
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div v-if="sourceOffcanvasOpen && !hasSourceOffcanvas" class="modal-backdrop fade show" style="z-index: 1040;"
        @click="closeSourceOffcanvas"></div>
    </teleport>
  </TablerLayout>
</template>
