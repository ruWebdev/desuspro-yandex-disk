<script>
// Импорт разметки для проекта
import MainLayout from '@/Layouts/MainLayout.vue';

export default {
    layout: MainLayout
};
</script>

<script setup>
import { ref, computed, onMounted, watch, nextTick, onUnmounted } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { useToast } from 'vue-toastification';
import ContentLayout from '@/Layouts/ContentLayout.vue';

// Partials
import Filters from '@/Pages/Dashboard/Partials/Filters.vue';
import TasksTable from '@/Pages/Dashboard/Partials/TasksTable.vue';

// Modals / Offcanvas
import DeleteTaskModal from '@/Pages/Dashboard/Modals/DeleteTaskModal.vue';
import CreateTaskModal from '@/Pages/Dashboard/Modals/CreateTaskModal.vue';
import AssignPerformerModal from '@/Pages/Dashboard/Modals/AssignPerformerModal.vue';
import BulkAssignModal from '@/Pages/Dashboard/Modals/BulkAssignModal.vue';
import EditTaskModal from '@/Pages/Dashboard/Modals/EditTaskModal.vue';
import RenameTaskModal from '@/Pages/Dashboard/Modals/RenameTaskModal.vue';
import LightboxModal from '@/Pages/Dashboard/Modals/LightboxModal.vue';
import TaskOffcanvas from '@/Pages/Dashboard/Modals/TaskOffcanvas.vue';
import SourceOffcanvas from '@/Pages/Dashboard/Modals/SourceOffcanvas.vue';
import QuestionModal from '@/Pages/Dashboard/Modals/QuestionModal.vue';

const props = defineProps({
    tasks: { type: Array, required: true },
    brands: { type: Array, required: true },
    performers: { type: Array, default: () => [] },
    taskTypes: { type: Array, default: () => [] },
    initialBrandId: { type: [Number, String], default: null },
    currentUser: { type: Object, default: null },
});

// Role helpers
const isAdmin = computed(() => {
    const u = props.currentUser; if (!u) return false;
    return (u.roles?.some(r => r.name === 'Administrator' || r.name === 'admin') || u.is_admin) === true;
});
const isManager = computed(() => {
    const u = props.currentUser; if (!u) return false;
    return (u.roles?.some(r => r.name === 'Manager' || r.name === 'manager') || u.is_manager) === true;
});
const isPerformer = computed(() => {
    const u = props.currentUser; if (!u) return false;
    return (u.roles?.some(r => r.name === 'Performer' || r.name === 'performer') || u.is_performer) === true;
});

const toast = useToast();

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

// Filters
const search = ref('');
const globalSearch = ref('');
const brandFilter = ref('');
const statusFilter = ref('');
const priorityFilter = ref('');
const articleFilter = ref('');
const filterArticles = ref([]);
const performerFilter = ref('');
const createdFilter = ref('');
const createdDate = ref('');
if (props.initialBrandId) brandFilter.value = String(props.initialBrandId);

// Server-side list state
const items = ref([]);
const page = ref(1);
const perPage = ref(20);
const hasMore = ref(true);
const loading = ref(false);

// Reset filters
function resetFilters() {
    search.value = '';
    globalSearch.value = '';
    brandFilter.value = '';
    articleFilter.value = '';
    performerFilter.value = '';
    createdFilter.value = '';
    createdDate.value = '';
    fetchPage(true);
}

function buildQueryParams(resetPage = false) {
    const params = {};
    if (search.value) params.search = search.value;
    if (globalSearch.value) params.global_search = globalSearch.value;
    if (statusFilter.value) params.status = statusFilter.value;
    if (priorityFilter.value) params.priority = priorityFilter.value;
    if (brandFilter.value) params.brand_id = brandFilter.value;
    if (articleFilter.value) params.article_id = articleFilter.value;
    if (performerFilter.value) params.assignee_id = performerFilter.value;
    if (createdFilter.value) params.created = createdFilter.value;
    if (createdDate.value) params.date = createdDate.value;

    // Role-based filtering
    if (props.currentUser) {
        const user = props.currentUser;
        const isAdmin = user.roles?.some(r => r.name === 'admin') || user.is_admin;
        const isManager = user.roles?.some(r => r.name === 'manager') || user.is_manager;
        const isPerformer = user.roles?.some(r => r.name === 'performer') || user.is_performer;
        if (isManager && !isAdmin) params.created_by = user.id;
        else if (isPerformer && !isAdmin && !isManager) params.assignee_id = user.id;
    }

    if (resetPage) page.value = 1;
    params.page = page.value;
    params.per_page = perPage.value;
    return params;
}

async function fetchPage(reset = false) {
    if (loading.value) return;
    loading.value = true;
    try {
        if (reset) {
            page.value = 1;
            hasMore.value = true;
            items.value = [];
        } else {
            page.value++;
        }
        const params = buildQueryParams();
        const url = route('tasks.search') + '?' + Object.keys(params).map(k => `${k}=${params[k]}`).join('&');
        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        const list = Array.isArray(data?.data) ? data.data : [];
        if (reset) items.value = list;
        else {
            const existingIds = new Set(items.value.map(i => i.id));
            const newItems = list.filter(i => !existingIds.has(i.id));
            items.value = [...items.value, ...newItems];
        }
        hasMore.value = Boolean(data?.next_page_url);
    } catch (e) {
        console.error('Error fetching tasks:', e);
        if (!reset) page.value--;
    } finally {
        loading.value = false;
    }
}

// Scroll-based infinite loader (uses table body wrapper)
const scrollContainer = ref(null);
const checkScroll = () => {
    if (!hasMore.value || loading.value) return;
    const container = scrollContainer.value || document.getElementById('tableBodyWrapper') || document.documentElement;
    const isWindow = container === window || container === document.documentElement;
    const scrollTop = isWindow ? window.scrollY : container.scrollTop;
    const clientHeight = isWindow ? window.innerHeight : container.clientHeight;
    const scrollHeight = isWindow ? document.documentElement.scrollHeight : container.scrollHeight;
    if (scrollTop + clientHeight >= scrollHeight - 300) fetchPage(false);
};
let scrollTimeout = null;
const handleScroll = () => {
    if (scrollTimeout) clearTimeout(scrollTimeout);
    scrollTimeout = setTimeout(checkScroll, 100);
};

onMounted(() => {
    fetchPage(true);
    // Resolve scroll container to internal table body wrapper
    scrollContainer.value = document.getElementById('tableBodyWrapper') || document.querySelector('.app-scroll') || null;
    const container = scrollContainer.value || window;
    container.addEventListener('scroll', handleScroll, { passive: true });
    checkScroll();
});
onUnmounted(() => {
    try { const container = scrollContainer.value || window; container.removeEventListener('scroll', handleScroll); } catch (_) { }
    if (scrollTimeout) clearTimeout(scrollTimeout);
});

// Filters reactive watchers
watch([search, globalSearch, brandFilter, statusFilter, priorityFilter, articleFilter, performerFilter, createdFilter, createdDate], () => {
    fetchPage(true);
});

// Load articles for brand for filter dropdown
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

const displayedTasks = computed(() => items.value);

// Selection
const selectedIds = ref([]);
const isSelected = (id) => selectedIds.value.includes(id);
function toggleRow(id) {
    if (isSelected(id)) selectedIds.value = selectedIds.value.filter(x => x !== id);
    else selectedIds.value = [...selectedIds.value, id];
}
function onSelectAll(val, ids) {
    if (val) {
        const set = new Set(selectedIds.value);
        ids.forEach(id => set.add(id));
        selectedIds.value = Array.from(set);
    } else {
        selectedIds.value = selectedIds.value.filter(id => !ids.includes(id));
    }
}
const anySelected = computed(() => selectedIds.value.length > 0);
const selectedTasks = computed(() => items.value.filter(t => selectedIds.value.includes(t.id)));
function clearSelection() { selectedIds.value = []; }

// Options helpers
const statusOptions = [
    { value: 'created', label: 'Создана' },
    { value: 'assigned', label: 'Назначена' },
    { value: 'in_progress', label: 'В работе' },
    { value: 'on_review', label: 'На проверку' },
    { value: 'rework', label: 'Доработать' },
    { value: 'accepted', label: 'Принята' },
    { value: 'question', label: 'Вопрос' },
    { value: 'cancelled', label: 'Отменена' },
];
const priorityOptions = [
    { value: 'low', label: 'Низкий' },
    { value: 'medium', label: 'Средний' },
    { value: 'high', label: 'Срочный' },
];

// Allowed status options per role
const userStatusOptions = computed(() => {
    if (isAdmin.value) return statusOptions;
    if (isManager.value) {
        // Manager cannot set 'in_progress' and 'on_review'
        return statusOptions.filter(o => !['in_progress', 'on_review'].includes(o.value));
    }
    if (isPerformer.value) {
        // Performer can only set to these
        return statusOptions.filter(o => ['in_progress', 'on_review', 'question'].includes(o.value));
    }
    return statusOptions;
});

function updateTaskStatus(task, status) {
    if (!task || !status) return;
    router.put(route('brands.tasks.update', { brand: task.brand_id, task: task.id }), { status }, {
        preserveScroll: true,
        onSuccess: () => {
            const i = items.value.findIndex(t => t.id === task.id);
            if (i !== -1) items.value[i].status = status;
            toast.success('Статус задачи обновлен', { position: 'top-right', timeout: 2500 });
        },
        onError: () => {
            toast.error('Не удалось обновить статус задачи', { position: 'top-right', timeout: 2500 });
        }
    });
}

// Question flow (single/bulk)
const showQuestion = ref(false);
const questionContext = ref({ mode: 'single', task: null });

function onUpdateStatus(task, status) {
    // Enforce allowed transitions by role in UI, but double-check here
    const allowed = new Set(userStatusOptions.value.map(o => o.value));
    if (!allowed.has(status)) { toast.error('Недоступный статус для вашей роли'); return; }
    if (status === 'question') {
        questionContext.value = { mode: 'single', task };
        showQuestion.value = true;
        return;
    }
    updateTaskStatus(task, status);
}

async function addSourceQuestionComment(brandId, taskId, text) {
    const url = route('brands.tasks.source_comments.store', { brand: brandId, task: taskId });
    const headers = { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() };
    const res = await fetch(url, { method: 'POST', headers, body: JSON.stringify({ content: text }) });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
}

async function submitQuestion(text) {
    const mode = questionContext.value.mode;
    try {
        if (mode === 'single' && questionContext.value.task) {
            const t = questionContext.value.task;
            await addSourceQuestionComment(t.brand_id, t.id, text);
            updateTaskStatus(t, 'question');
        } else if (mode === 'bulk') {
            // Post comment for each selected task then bulk set status
            const ids = [...selectedIds.value];
            for (const id of ids) {
                const t = items.value.find(x => x.id === id);
                if (t) {
                    try { await addSourceQuestionComment(t.brand_id, t.id, text); } catch (e) { console.warn('comment failed', e); }
                }
            }
            await bulkUpdateStatusProceed('question');
        }
        showQuestion.value = false;
    } catch (e) {
        console.error(e);
        toast.error('Не удалось сохранить вопрос');
    }
}

function updateTaskPriority(task, priority) {
    if (!task || !priority) return;
    router.put(route('brands.tasks.update', { brand: task.brand_id, task: task.id }), { priority }, {
        preserveScroll: true,
        onSuccess: () => {
            const i = items.value.findIndex(t => t.id === task.id);
            if (i !== -1) items.value[i].priority = priority;
            toast.success('Приоритет задачи обновлен', { position: 'top-right', timeout: 2500 });
        },
        onError: () => {
            toast.error('Не удалось обновить приоритет задачи', { position: 'top-right', timeout: 2500 });
        }
    });
}

// Public links
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
        if (!task.public_link) { toast.info('Публичная ссылка создана. Нажмите «Скопировать» ещё раз.'); return; }
    }
    try {
        const text = task.public_link;
        if (navigator.clipboard?.writeText) await navigator.clipboard.writeText(text);
        else {
            const ta = document.createElement('textarea'); ta.value = text; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
        }
        toast.success('Ссылка скопирована в буфер обмена');
    } catch (e) { console.error('Copy failed', e); toast.error('Не удалось скопировать ссылку'); }
}
function openTaskPublicLink(task) {
    if (!task) return;
    if (task.public_link) window.open(task.public_link, '_blank');
    else useForm({}).post(route('brands.tasks.public_link', { brand: task.brand_id, task: task.id }), { preserveScroll: true });
}

// Per-task assign modal
const showAssign = ref(false);
const assigningTask = ref(null);
const assignUserId = ref(null);
function openAssign(task) { assigningTask.value = task; assignUserId.value = task.assignee_id || null; showAssign.value = true; }
function closeAssign() { showAssign.value = false; assigningTask.value = null; assignUserId.value = null; }
async function submitAssign(uid) {
    if (!assigningTask.value) return;
    const id = assigningTask.value.id; const brandId = assigningTask.value.brand_id;
    const payload = { assignee_id: uid ? Number(uid) : null, status: uid ? 'assigned' : 'created' };
    closeAssign();
    await router.put(route('brands.tasks.update', { brand: brandId, task: id }), payload, {
        preserveScroll: true,
        onSuccess: () => {
            const i = items.value.findIndex(t => t.id === id);
            if (i !== -1) {
                const performer = props.performers.find(p => p.id === (uid ? Number(uid) : -1)) || null;
                items.value.splice(i, 1, { ...items.value[i], assignee_id: uid ? Number(uid) : null, assignee: uid ? performer : null, status: payload.status });
            }
            fetchPage(true);
        },
        onError: () => {
            const t = items.value.find(t => t.id === id); if (t) openAssign(t);
            toast.error('Не удалось обновить исполнителя', { position: 'top-right', timeout: 3000 });
        }
    });
}

// Bulk actions
const showBulkAssign = ref(false);
const bulkAssignUserId = ref(null);
function openBulkAssign() { bulkAssignUserId.value = null; showBulkAssign.value = true; }
function closeBulkAssign() { showBulkAssign.value = false; bulkAssignUserId.value = null; }
async function submitBulkAssign(uid) {
    uid = uid ? Number(uid) : null; if (uid == null) return;
    const ids = [...selectedIds.value];
    await router.put(route('tasks.bulk_update'), { ids, assignee_id: uid }, {
        preserveScroll: true,
        onSuccess: () => {
            ids.forEach(id => { const i = items.value.findIndex(t => t.id === id); if (i !== -1) { const perf = props.performers.find(p => p.id === uid); items.value.splice(i, 1, { ...items.value[i], assignee_id: uid, assignee: perf || null }); } });
            toast.success(`Назначено ${ids.length} задач`, { position: 'top-right', timeout: 3000 });
            clearSelection(); closeBulkAssign();
        },
        onError: () => { toast.error('Не удалось назначить задачи', { position: 'top-right', timeout: 3000 }); }
    });
}

async function bulkUpdateStatus(value) {
    if (!value) return;
    const allowed = new Set(userStatusOptions.value.map(o => o.value));
    if (!allowed.has(value)) { toast.error('Недоступный статус для вашей роли'); return; }
    if (value === 'question') {
        // Open modal to capture question
        questionContext.value = { mode: 'bulk', task: null };
        showQuestion.value = true;
        return;
    }
    await bulkUpdateStatusProceed(value);
}

async function bulkUpdateStatusProceed(value) {
    const ids = [...selectedIds.value];
    await router.put(route('tasks.bulk_update'), { ids, status: value }, {
        preserveScroll: true,
        onSuccess: () => { ids.forEach(id => { const i = items.value.findIndex(t => t.id === id); if (i !== -1) items.value[i].status = value; }); clearSelection(); toast.success('Статусы обновлены', { position: 'top-right', timeout: 2500 }); },
        onError: () => { toast.error('Не удалось обновить статусы', { position: 'top-right', timeout: 2500 }); }
    });
}
async function bulkUpdatePriority(value) {
    if (!value) return; const ids = [...selectedIds.value];
    await router.put(route('tasks.bulk_update'), { ids, priority: value }, {
        preserveScroll: true,
        onSuccess: () => { ids.forEach(id => { const i = items.value.findIndex(t => t.id === id); if (i !== -1) items.value[i].priority = value; }); clearSelection(); toast.success('Приоритеты обновлены', { position: 'top-right', timeout: 2500 }); },
        onError: () => { toast.error('Не удалось обновить приоритеты', { position: 'top-right', timeout: 2500 }); }
    });
}

// Delete
const showDelete = ref(false);
const deleting = ref(null);
function onDeleteTask(t) { deleting.value = t; showDelete.value = true; }
function cancelDelete() { showDelete.value = false; deleting.value = null; }
function submitDelete() {
    if (!deleting.value) return; const id = deleting.value.id;
    router.delete(route('brands.tasks.destroy', { brand: deleting.value.brand_id, task: id }), {
        onSuccess: () => { const i = items.value.findIndex(x => x.id === id); if (i !== -1) items.value.splice(i, 1); cancelDelete(); },
        preserveScroll: true,
    });
}

// Create
const showCreate = ref(false);
function openCreate() { showCreate.value = true; }
function closeCreate() { showCreate.value = false; }
function onCreated() { fetchPage(true); }

// Edit / Rename
const showEdit = ref(false);
const editingTask = ref(null);
function onEditTask(t) { editingTask.value = t; showEdit.value = true; }
function onUpdated() { showEdit.value = false; editingTask.value = null; fetchPage(true); }

const showRename = ref(false);
const renaming = ref(null);
const renameName = ref('');
function cancelRename() { showRename.value = false; renaming.value = null; renameName.value = ''; }
function submitRename() {
    if (!renaming.value) return; const name = (renameName.value || '').trim(); if (!name) return;
    router.put(route('brands.tasks.update', { brand: renaming.value.brand_id, task: renaming.value.id }), { name }, {
        preserveScroll: true,
        onSuccess: () => { cancelRename(); fetchPage(true); },
    });
}

// Offcanvas (task comments/files)
const showTaskOffcanvas = ref(false);
const offcanvasTask = ref(null);
const activeOcTab = ref('comments');
function openCommentsOffcanvas(task) { offcanvasTask.value = task; activeOcTab.value = 'comments'; showTaskOffcanvas.value = true; }
function openFilesOffcanvas(task) { offcanvasTask.value = task; activeOcTab.value = 'files'; showTaskOffcanvas.value = true; }
function closeTaskOffcanvas() { showTaskOffcanvas.value = false; offcanvasTask.value = null; }

// Source offcanvas (brand source comments/files)
const showSourceOffcanvas = ref(false);
const sourceTask = ref(null);
const sourceInitialTab = ref('comments'); // 'comments' | 'files'
function openSourceCommentsOffcanvas(task) { sourceTask.value = task; sourceInitialTab.value = 'comments'; showSourceOffcanvas.value = true; }
function openSourceFilesOffcanvas(task) { sourceTask.value = task; sourceInitialTab.value = 'files'; showSourceOffcanvas.value = true; }
function closeSourceOffcanvas() { showSourceOffcanvas.value = false; sourceTask.value = null; sourceInitialTab.value = 'comments'; }
function handleSourceFilesUpdated(data) {
    const { taskId, sourceFiles } = data;
    const taskIndex = items.value.findIndex(t => t.id === taskId);
    if (taskIndex !== -1) {
        items.value[taskIndex].source_files = sourceFiles;
    }
}

// Lightbox
const showLightbox = ref(false);
const lightboxSrc = ref('');
const lightboxType = ref('image');
const lightboxMeta = ref(null); // { id, path } for temp cleanup
function openLightbox(url, meta = null) {
    lightboxType.value = 'image';
    lightboxSrc.value = url;
    lightboxMeta.value = meta || null;
    showLightbox.value = true;
}
function closeLightbox() {
    showLightbox.value = false;
    const meta = lightboxMeta.value;
    lightboxMeta.value = null;
    setTimeout(() => {
        lightboxSrc.value = '';
        lightboxType.value = 'image';
    }, 150);
    // Cleanup temp file if meta present
    try {
        if (meta?.path || meta?.id) {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            fetch(route('integrations.yandex.delete_temp'), {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
                },
                body: JSON.stringify({ path: meta.path, id: meta.id })
            });
        }
    } catch (e) { console.warn('Temp cleanup failed', e); }
}

</script>

<template>

    <Head title="Задачи" />
    <ContentLayout>
        <template #TopButtons>
            <Filters :search="search" :globalSearch="globalSearch" :brandFilter="brandFilter"
                :statusFilter="statusFilter" :priorityFilter="priorityFilter" :articleFilter="articleFilter"
                :performerFilter="performerFilter" :createdFilter="createdFilter" :createdDate="createdDate"
                :brands="brands" :performers="performers" :statusOptions="statusOptions"
                :priorityOptions="priorityOptions" :filterArticles="filterArticles" :currentUser="currentUser"
                @update:search="(v) => search = v" @update:globalSearch="(v) => globalSearch = v"
                @update:brandFilter="(v) => brandFilter = v" @update:statusFilter="(v) => statusFilter = v"
                @update:priorityFilter="(v) => priorityFilter = v" @update:articleFilter="(v) => articleFilter = v"
                @update:performerFilter="(v) => performerFilter = v" @update:createdFilter="(v) => createdFilter = v"
                @update:createdDate="(v) => createdDate = v" @reset="resetFilters" @create="openCreate" />
        </template>

        <div class="row">
            <div class="col-12">
                <div class="card p-0 m-0" v-if="anySelected" style="border-radius: 0; border: 0px;">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-12 d-flex align-items-center flex-wrap gap-2">
                                <div class="me-3">
                                    <i class="ti ti-selector me-1"></i> Выбрано: {{ selectedIds.length }}
                                </div>
                                <button class="btn btn-sm btn-outline-primary" @click="openBulkAssign">
                                    <i class="ti ti-user-plus me-1"></i> Добавить исполнителя
                                </button>
                                <div class="d-flex align-items-center gap-1">
                                    <span class="text-secondary small">Статус:</span>
                                    <select class="form-select form-select-sm w-auto"
                                        @change="(e) => bulkUpdateStatus(e.target.value)">
                                        <option value="" selected disabled>Выбрать…</option>
                                        <option v-for="s in userStatusOptions" :key="s.value" :value="s.value">{{
                                            s.label }}
                                        </option>
                                    </select>
                                </div>
                                <div class="d-flex align-items-center gap-1">
                                    <span class="text-secondary small">Приоритет:</span>
                                    <select class="form-select form-select-sm w-auto"
                                        @change="(e) => bulkUpdatePriority(e.target.value)">
                                        <option value="" selected disabled>Выбрать…</option>
                                        <option v-for="p in priorityOptions" :key="p.value" :value="p.value">{{ p.label
                                        }}
                                        </option>
                                    </select>
                                </div>
                                <div class="ms-auto">
                                    <button class="btn btn-sm btn-outline-secondary me-2" @click="clearSelection">
                                        <i class="ti ti-x me-1"></i> Снять выделение
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <TasksTable :tasks="displayedTasks" :brands="brands" :selectedIds="selectedIds"
                    :statusOptions="statusOptions" :priorityOptions="priorityOptions" :loading="loading"
                    :currentUser="currentUser" :hideScroll="true" @toggle-row="toggleRow" @select-all="onSelectAll"
                    @update-status="onUpdateStatus" @update-priority="updateTaskPriority" @open-assign="openAssign"
                    @open-source-comments="openSourceCommentsOffcanvas" @open-source-files="openSourceFilesOffcanvas"
                    @open-comments="openCommentsOffcanvas" @open-files="openFilesOffcanvas"
                    @copy-link="copyTaskPublicLink" @open-link="openTaskPublicLink" @edit-task="onEditTask"
                    @delete-task="onDeleteTask" />
            </div>
        </div>

        <!-- Modals -->
        <AssignPerformerModal :show="showAssign" :assigningTask="assigningTask" :assignUserId="assignUserId"
            :performers="performers" @update:assignUserId="(v) => assignUserId = v" @close="closeAssign"
            @submit="submitAssign" />
        <BulkAssignModal :show="showBulkAssign" :selectedCount="selectedIds.length" :bulkAssignUserId="bulkAssignUserId"
            :performers="performers" @update:bulkAssignUserId="(v) => bulkAssignUserId = v" @close="closeBulkAssign"
            @submit="submitBulkAssign" />
        <DeleteTaskModal :show="showDelete" :deleting="deleting" @cancel="cancelDelete" @confirm="submitDelete" />
        <CreateTaskModal :show="showCreate" :brands="brands" :taskTypes="taskTypes" :performers="performers"
            @close="closeCreate" @created="onCreated" />
        <EditTaskModal :show="showEdit" :editingTask="editingTask" :brands="brands" :taskTypes="taskTypes"
            :performers="performers" @close="() => { showEdit = false; editingTask = null; }" @updated="onUpdated" />
        <RenameTaskModal :show="showRename" :renaming="renaming" :renameName="renameName" @cancel="cancelRename"
            @submit="(v) => { renameName = v; submitRename(); }" />
        <LightboxModal :show="showLightbox" :lightboxSrc="lightboxSrc" :lightboxType="lightboxType"
            @close="closeLightbox" />

        <!-- Offcanvas -->
        <TaskOffcanvas :show="showTaskOffcanvas" :task="offcanvasTask" :brands="brands" :activeTab="activeOcTab"
            :currentUser="currentUser" @close="closeTaskOffcanvas" @update-tab="(t) => activeOcTab = t"
            @open-lightbox="openLightbox" />
        <SourceOffcanvas :show="showSourceOffcanvas" :task="sourceTask" :brands="brands" :currentUser="currentUser"
            :initialTab="sourceInitialTab" @close="closeSourceOffcanvas" @open-lightbox="openLightbox"
            @source-files-updated="handleSourceFilesUpdated" />
        <QuestionModal :show="showQuestion" @close="() => showQuestion = false" @submit="submitQuestion" />
    </ContentLayout>
</template>