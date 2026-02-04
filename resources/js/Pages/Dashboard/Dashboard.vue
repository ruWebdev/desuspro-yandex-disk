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

// Части
import Filters from '@/Pages/Dashboard/Partials/Filters.vue';
import TasksTable from '@/Pages/Dashboard/Partials/TasksTable.vue';

// Модалы / Offcanvas
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

// Помощники ролей
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

function getStatusColor(value) {
    switch (value) {
        case 'created': return '#6c757d';
        case 'assigned': return '#0d6efd';
        case 'in_progress': return '#0dcaf0';
        case 'on_review': return '#ffc107';
        case 'rework': return '#dc3545';
        case 'accepted': return '#198754';
        case 'question': return '#6f42c1';
        case 'cancelled': return '#212529';
        default: return '#495057';
    }
}
function getFileNameFromUrl(url) {
    try {
        const u = new URL(url, window.location.origin);
        const path = u.pathname || '';
        const segs = path.split('/').filter(Boolean);
        return segs[segs.length - 1] || '';
    } catch (_) {
        const clean = (url || '').split('?')[0].split('#')[0];
        const parts = clean.split('/');
        return parts[parts.length - 1] || '';
    }
}
async function onLightboxComment(fname) {
    const derived = fname && String(fname).trim()
        ? fname.trim()
        : ((lightboxMeta.value && lightboxMeta.value.name) ? lightboxMeta.value.name : getFileNameFromUrl(lightboxSrc.value));
    closeLightbox();
    // Switch offcanvas to comments and prefill (force watcher by toggling value)
    activeOcTab.value = 'comments';
    commentPrefill.value = '';
    await nextTick();
    commentPrefill.value = derived || '';
}

// Фильтры
const search = ref('');
const globalSearch = ref('');
const brandFilter = ref('');
const statusFilter = ref('');
const priorityFilter = ref('');
const articleFilter = ref('');
const filterArticles = ref([]);
const performerFilter = ref('');
const createdFilter = ref('');
const createdDateFrom = ref('');
const createdDateTo = ref('');
if (props.initialBrandId) brandFilter.value = String(props.initialBrandId);

// Состояние списка на стороне сервера
const items = ref([]);
const page = ref(1);
const perPage = ref(20);
const hasMore = ref(true);
const loading = ref(false);
let folderPollTimer = null;

// Сбросить фильтры
function resetFilters() {
    search.value = '';
    globalSearch.value = '';
    brandFilter.value = '';
    statusFilter.value = '';
    priorityFilter.value = '';
    articleFilter.value = '';
    performerFilter.value = '';
    createdFilter.value = '';
    createdDateFrom.value = '';
    createdDateTo.value = '';
    fetchPage(true);
}

// Применить фильтр по артикулу из строки таблицы
function filterByArticleFromTask(task) {
    if (!task || !task.article) return;

    const brandId = task.brand_id || task.brand?.id;
    const articleId = task.article.id;

    if (!articleId) return;

    if (brandId) {
        brandFilter.value = String(brandId);
    }

    articleFilter.value = String(articleId);
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
    if (createdFilter.value === 'date') {
        if (createdDateFrom.value) params.date_from = createdDateFrom.value;
        if (createdDateTo.value) params.date_to = createdDateTo.value;
    }

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
    // Start polling folder status
    try { if (folderPollTimer) clearInterval(folderPollTimer); } catch (_) { }
    folderPollTimer = setInterval(pollPendingFolderStatus, 30000);
});
onUnmounted(() => {
    try { const container = scrollContainer.value || window; container.removeEventListener('scroll', handleScroll); } catch (_) { }
    if (scrollTimeout) clearTimeout(scrollTimeout);
    if (folderPollTimer) { try { clearInterval(folderPollTimer); } catch (_) { } folderPollTimer = null; }
});

// Filters reactive watchers
watch([
    search,
    globalSearch,
    brandFilter,
    statusFilter,
    priorityFilter,
    articleFilter,
    performerFilter,
    createdFilter,
    createdDateFrom,
    createdDateTo,
], () => {
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

async function pollPendingFolderStatus() {
    try {
        const pending = items.value.filter(t => t && t.folder_created === false).map(t => t.id);
        if (!pending.length) return;
        const base = route('tasks.folder_status');
        const qs = pending.map(id => 'ids[]=' + encodeURIComponent(id)).join('&');
        const url = base + '?' + qs;
        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        if (!res.ok) return;
        const data = await res.json();
        const list = Array.isArray(data?.data) ? data.data : [];
        if (!list.length) return;
        const map = new Map(list.map(x => [x.id, x]));
        items.value = items.value.map(t => {
            const upd = map.get(t.id);
            if (!upd) return t;
            // If folder_created flipped to true, also refresh public_link and source_files if provided
            const next = { ...t };
            if (typeof upd.folder_created !== 'undefined') next.folder_created = upd.folder_created;
            if (upd.public_link) next.public_link = upd.public_link;
            if (Array.isArray(upd.source_files)) next.source_files = upd.source_files;
            return next;
        });
    } catch (e) { /* silent */ }
}

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

function getTaskAcceptedAt(task) {
    if (!task || task.status !== 'accepted') return null;
    return task.accepted_at || task.updated_at || null;
}

function formatDateRu(value) {
    if (!value) return '';
    try {
        const d = new Date(value);
        return Number.isNaN(d.getTime()) ? '' : d.toLocaleDateString('ru-RU');
    } catch (e) {
        return '';
    }
}

async function exportSelectedToXls() {
    const tasks = selectedTasks.value;
    if (!tasks.length) return;

    try {
        const XLSX = await import(/* @vite-ignore */ 'https://cdn.jsdelivr.net/npm/xlsx@0.18.5/+esm');

        const header = [
            'Дата создания',
            'Дата принятия',
            'Наименование',
            'Бренд',
            'Артикул',
            'Тип',
            'Исполнитель',
        ];

        const rows = tasks.map((t) => {
            const createdDate = formatDateRu(t.created_at);
            const acceptedAt = getTaskAcceptedAt(t);
            const acceptedDate = acceptedAt ? formatDateRu(acceptedAt) : '';
            const name = t.name || (t.article?.name || '');
            const brandName = t.brand?.name || (brands.find(b => b.id === t.brand_id)?.name) || '';
            const articleName = t.article?.name || '';
            const typeName = t.type?.name || '';
            const assigneeName = t.assignee?.name || '';

            return [
                createdDate,
                acceptedDate,
                name,
                brandName,
                articleName,
                typeName,
                assigneeName,
            ];
        });

        const sheetData = [header, ...rows];
        const ws = XLSX.utils.aoa_to_sheet(sheetData);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Задачи');

        const now = new Date();
        const yyyyMmDd = now.toISOString().slice(0, 10);
        const fileName = `tasks_export_${yyyyMmDd}.xlsx`;

        XLSX.writeFile(wb, fileName);
    } catch (e) {
        console.error('Export XLS failed', e);
        toast.error('Не удалось сформировать XLS');
    }
}

// Bulk delete (admin only)
async function onBulkDelete() {
    if (!isAdmin.value) return;
    const ids = [...selectedIds.value];
    if (ids.length === 0) return;
    if (!confirm(`Удалить ${ids.length} задач(и)? Будут удалены комментарии, изображения комментариев и папка на Яндекс.Диске.`)) return;
    try {
        const res = await fetch(route('tasks.bulk_delete'), {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ ids })
        });
        if (!res.ok) {
            const text = await res.text().catch(() => '');
            throw new Error(`HTTP ${res.status}: ${text}`);
        }
        // Remove deleted tasks from UI
        const idSet = new Set(ids);
        items.value = items.value.filter(t => !idSet.has(t.id));
        clearSelection();
        toast.success('Задачи удалены');
    } catch (e) {
        console.error('bulk delete failed', e);
        toast.error('Не удалось удалить задачи');
    }
}

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
            toast.success('Статус задачи обновлен', { timeout: 2500 });
        },
        onError: () => {
            toast.error('Не удалось обновить статус задачи', { timeout: 2500 });
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
    // Restrict: Performer cannot change status if task is already accepted
    if (isPerformer.value && task?.status === 'accepted') {
        toast.error('Исполнитель не может менять статус принятой задачи');
        return;
    }
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
            toast.success('Приоритет задачи обновлен', { position: 'bottom-right', timeout: 2500 });
        },
        onError: () => {
            toast.error('Не удалось обновить приоритет задачи', { position: 'bottom-right', timeout: 2500 });
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
            toast.error('Не удалось обновить исполнителя', { position: 'bottom-right', timeout: 3000 });
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
            ids.forEach(id => { const i = items.value.findIndex(t => t.id === id); if (i !== -1) { const perf = props.performers.find(p => p.id === uid); items.value.splice(i, 1, { ...items.value[i], assignee_id: uid, assignee: perf || null, status: 'assigned' }); } });
            toast.success(`Назначено ${ids.length} задач`, { position: 'bottom-right', timeout: 3000 });
            clearSelection(); closeBulkAssign();
        },
        onError: () => { toast.error('Не удалось назначить задачи', { position: 'bottom-right', timeout: 3000 }); }
    });
}

async function bulkUpdateStatus(value) {
    if (!value) return;
    const allowed = new Set(userStatusOptions.value.map(o => o.value));
    if (!allowed.has(value)) { toast.error('Недоступный статус для вашей роли'); return; }
    // Restrict: Performer cannot bulk-change status for any accepted tasks
    if (isPerformer.value) {
        const hasAccepted = selectedTasks.value.some(t => t.status === 'accepted');
        if (hasAccepted) { toast.error('Исполнитель не может менять статус принятых задач'); return; }
    }
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
        onSuccess: () => { ids.forEach(id => { const i = items.value.findIndex(t => t.id === id); if (i !== -1) items.value[i].status = value; }); clearSelection(); toast.success('Статусы обновлены', { position: 'bottom-right', timeout: 2500 }); },
        onError: () => { toast.error('Не удалось обновить статусы', { position: 'bottom-right', timeout: 2500 }); }
    });
}
async function bulkUpdatePriority(value) {
    if (!value) return; const ids = [...selectedIds.value];
    await router.put(route('tasks.bulk_update'), { ids, priority: value }, {
        preserveScroll: true,
        onSuccess: () => { ids.forEach(id => { const i = items.value.findIndex(t => t.id === id); if (i !== -1) items.value[i].priority = value; }); clearSelection(); toast.success('Приоритеты обновлены', { position: 'bottom-right', timeout: 2500 }); },
        onError: () => { toast.error('Не удалось обновить приоритеты', { position: 'bottom-right', timeout: 2500 }); }
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
const createBasedOnTask = ref(null); // Task to create based on
function openCreate() { createBasedOnTask.value = null; showCreate.value = true; }
function closeCreate() { showCreate.value = false; createBasedOnTask.value = null; }
function onCreated() { fetchPage(true); }

// Create based on existing task
function openCreateBasedOn(sourceTask) {
    createBasedOnTask.value = sourceTask;
    showCreate.value = true;
    toast.info('Заполните тип задачи и при необходимости измените другие поля');
}

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
const lightboxItems = ref([]); // optional array of normalized items { src, meta }
const lightboxIndex = ref(0);
const commentPrefill = ref('');

function normalizeLightboxItems(items) {
    if (!Array.isArray(items)) return [];
    return items
        .map((entry) => {
            if (!entry) return null;
            if (typeof entry === 'string') {
                return { src: entry, meta: null };
            }
            if (typeof entry === 'object') {
                if (entry.src) return { src: entry.src, meta: entry.meta ?? null };
                if (entry.url) return { src: entry.url, meta: entry.meta ?? null };
            }
            return null;
        })
        .filter((item) => item && item.src);
}

function ensureLightboxMeta(metaCandidate, url) {
    const base = (metaCandidate && typeof metaCandidate === 'object') ? { ...metaCandidate } : {};
    if (!base.name) base.name = getFileNameFromUrl(url);
    return base;
}

function applyLightboxIndex(idx) {
    if (!Array.isArray(lightboxItems.value) || !lightboxItems.value.length) {
        lightboxSrc.value = '';
        lightboxMeta.value = null;
        return;
    }
    const safeIndex = ((idx % lightboxItems.value.length) + lightboxItems.value.length) % lightboxItems.value.length;
    const current = lightboxItems.value[safeIndex];
    lightboxIndex.value = safeIndex;
    lightboxSrc.value = current?.src || '';
    lightboxMeta.value = ensureLightboxMeta(current?.meta, lightboxSrc.value);
}

function openLightbox(url, meta = null, items = null) {
    lightboxType.value = 'image';
    const normalized = normalizeLightboxItems(items);
    let workingItems = normalized;
    let idx = -1;

    if (workingItems.length) {
        idx = workingItems.findIndex((item) => item.src === url);
        if (idx === -1 && url) {
            const injectedMeta = ensureLightboxMeta(meta, url);
            workingItems = [{ src: url, meta: injectedMeta }, ...workingItems];
            idx = 0;
        }
    }

    if (!workingItems.length && url) {
        const injectedMeta = ensureLightboxMeta(meta, url);
        workingItems = [{ src: url, meta: injectedMeta }];
        idx = 0;
    }

    lightboxItems.value = workingItems;
    if (idx < 0) idx = 0;

    const currentMeta = (workingItems[idx] && workingItems[idx].meta) ? workingItems[idx].meta : meta;
    const resolvedMeta = ensureLightboxMeta(currentMeta, workingItems[idx]?.src || url);

    lightboxMeta.value = resolvedMeta;
    lightboxSrc.value = workingItems[idx]?.src || url;
    lightboxIndex.value = idx;
    showLightbox.value = true;
}
function closeLightbox() {
    showLightbox.value = false;
    const meta = lightboxMeta.value;
    lightboxMeta.value = null;
    setTimeout(() => {
        lightboxSrc.value = '';
        lightboxItems.value = [];
        lightboxIndex.value = 0;
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
function lightboxPrev() {
    if (!lightboxItems.value.length) return;
    applyLightboxIndex(lightboxIndex.value - 1);
}
function lightboxNext() {
    if (!lightboxItems.value.length) return;
    applyLightboxIndex(lightboxIndex.value + 1);
}

</script>

<template>

    <Head title="Задачи" />
    <ContentLayout>
        <template #TopButtons>
            <Filters :search="search" :globalSearch="globalSearch" :brandFilter="brandFilter"
                :statusFilter="statusFilter" :priorityFilter="priorityFilter" :articleFilter="articleFilter"
                :performerFilter="performerFilter" :createdFilter="createdFilter"
                :createdDateFrom="createdDateFrom" :createdDateTo="createdDateTo" :brands="brands"
                :performers="performers" :statusOptions="statusOptions" :priorityOptions="priorityOptions"
                :filterArticles="filterArticles" :currentUser="currentUser"
                @update:search="(v) => search = v" @update:globalSearch="(v) => globalSearch = v"
                @update:brandFilter="(v) => brandFilter = v" @update:statusFilter="(v) => statusFilter = v"
                @update:priorityFilter="(v) => priorityFilter = v" @update:articleFilter="(v) => articleFilter = v"
                @update:performerFilter="(v) => performerFilter = v" @update:createdFilter="(v) => createdFilter = v"
                @update:createdDateFrom="(v) => createdDateFrom = v" @update:createdDateTo="(v) => createdDateTo = v"
                @reset="resetFilters" @create="openCreate" />
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
                                <button class="btn btn-sm btn-outline-primary" @click="openBulkAssign"
                                    v-if="!isPerformer">
                                    <i class="ti ti-user-plus me-1"></i> Добавить исполнителя
                                </button>
                                <div class="d-flex align-items-center gap-1">
                                    <span class="text-secondary small">Статус:</span>
                                    <select class="form-select form-select-sm w-auto"
                                        @change="(e) => bulkUpdateStatus(e.target.value)">
                                        <option value="" selected disabled>Выбрать…</option>
                                        <option v-for="s in userStatusOptions" :key="s.value" :value="s.value"
                                            :style="{ color: getStatusColor(s.value) }">{{ s.label }}</option>
                                    </select>
                                </div>
                                <div class="d-flex align-items-center gap-1" v-if="!isPerformer">
                                    <span class="text-secondary small">Приоритет:</span>
                                    <select class="form-select form-select-sm w-auto"
                                        @change="(e) => bulkUpdatePriority(e.target.value)">
                                        <option value="" selected disabled>Выбрать…</option>
                                        <option v-for="p in priorityOptions" :key="p.value" :value="p.value">{{ p.label
                                        }}
                                        </option>
                                    </select>
                                </div>
                                <div class="ms-auto d-flex align-items-center gap-2">
                                    <button v-if="isAdmin" class="btn btn-sm btn-outline-danger" @click="onBulkDelete">
                                        <i class="ti ti-trash me-1"></i> Удалить
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary" @click="clearSelection">
                                        <i class="ti ti-x me-1"></i> Снять выделение
                                    </button>
                                    <button class="btn btn-sm btn-outline-success" @click="exportSelectedToXls">
                                        <i class="ti ti-file-spreadsheet me-1"></i> ЭКСПОРТ XLS
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
                    @delete-task="onDeleteTask" @create-based-on="openCreateBasedOn"
                    @filter-by-article="filterByArticleFromTask" />
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
            :basedOnTask="createBasedOnTask" @close="closeCreate" @created="onCreated" />
        <EditTaskModal :show="showEdit" :editingTask="editingTask" :brands="brands" :taskTypes="taskTypes"
            :performers="performers" @close="() => { showEdit = false; editingTask = null; }" @updated="onUpdated" />
        <RenameTaskModal :show="showRename" :renaming="renaming" :renameName="renameName" @cancel="cancelRename"
            @submit="(v) => { renameName = v; submitRename(); }" />
        <LightboxModal :show="showLightbox" :lightboxSrc="lightboxSrc" :lightboxType="lightboxType"
            :items="lightboxItems" :index="lightboxIndex" :meta="lightboxMeta" @close="closeLightbox"
            @prev="lightboxPrev" @next="lightboxNext" @comment="onLightboxComment" />

        <!-- Offcanvas -->
        <TaskOffcanvas :show="showTaskOffcanvas" :task="offcanvasTask" :brands="brands" :activeTab="activeOcTab"
            :currentUser="currentUser" :commentPrefill="commentPrefill" @close="closeTaskOffcanvas"
            @update-tab="(t) => activeOcTab = t" @open-lightbox="openLightbox" />
        <SourceOffcanvas :show="showSourceOffcanvas" :task="sourceTask" :brands="brands" :currentUser="currentUser"
            :initialTab="sourceInitialTab" @close="closeSourceOffcanvas" @open-lightbox="openLightbox"
            @source-files-updated="handleSourceFilesUpdated" />
        <QuestionModal :show="showQuestion" @close="() => showQuestion = false" @submit="submitQuestion" />
    </ContentLayout>
</template>

<style>
.Vue-Toastification__container {
    z-index: 999999999 !important;
}
</style>