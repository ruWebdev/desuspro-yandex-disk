<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import DashByteLayout from '@/layouts/DashByteLayout.vue';
import { Offcanvas } from 'bootstrap';

// Reference to Bootstrap's Modal component
const Modal = window.bootstrap?.Modal;

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

// Server-side list state
const items = ref([]);
const page = ref(1);
const perPage = ref(20);
const hasMore = ref(true);
const loading = ref(false);

// Reset all filters to their default values
function resetFilters() {
    search.value = '';
    globalSearch.value = '';
    brandFilter.value = '';
    articleFilter.value = '';
    performerFilter.value = '';
    createdFilter.value = '';
    createdDate.value = '';

    // Reset to first page and fetch fresh data
    fetchPage(true);
}

function buildQueryParams(resetPage = false) {
    const params = new URLSearchParams();
    if (!resetPage) params.set('page', String(page.value)); else params.set('page', '1');
    params.set('per_page', String(perPage.value));
    if (search.value.trim()) params.set('search', search.value.trim());
    if (globalSearch.value.trim()) params.set('global', globalSearch.value.trim());
    if (brandFilter.value) params.set('brand_id', String(brandFilter.value));
    if (articleFilter.value) params.set('article_id', String(articleFilter.value));
    if (performerFilter.value) params.set('assignee_id', String(performerFilter.value));
    if (createdFilter.value) {
        params.set('created', createdFilter.value);
        if (createdFilter.value === 'date' && createdDate.value) params.set('date', createdDate.value);
    }
    return params;
}

async function fetchPage(reset = false) {
    if (loading.value) return;
    loading.value = true;
    try {
        if (reset) { page.value = 1; hasMore.value = true; items.value = []; }
        const params = buildQueryParams(reset);
        const url = route('tasks.search') + `?${params.toString()}`;
        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        const list = Array.isArray(data?.data) ? data.data : [];
        if (reset) items.value = list; else items.value = [...items.value, ...list];
        hasMore.value = Boolean(data?.next_page_url);
        if (!reset) page.value = page.value + 1; // next page for subsequent loads
    } catch (e) { console.error(e); }
    finally { loading.value = false; }
}

// Infinite scroll on window
function onScroll() {
    if (!hasMore.value || loading.value) return;
    const nearBottom = (window.innerHeight + window.scrollY) >= (document.body.offsetHeight - 200);
    if (nearBottom) fetchPage(false);
}
onMounted(() => {
    fetchPage(true);
    window.addEventListener('scroll', onScroll, { passive: true });
});

watch([search, globalSearch, brandFilter, articleFilter, performerFilter, createdFilter, createdDate], () => {
    // Debounce not strictly necessary here; simple immediate fetch reset
    fetchPage(true);
});

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

const displayedTasks = computed(() => items.value);

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
const selectedTasks = computed(() => items.value.filter(t => selectedIds.value.includes(t.id)));
function clearSelection() { selectedIds.value = []; }

// Bulk actions: assign, status, priority
const bulkAssignUserId = ref(null);
function openBulkAssign() { const m = getBsModal('bulkAssignModal'); m?.show(); }
function closeBulkAssign() { const m = getBsModal('bulkAssignModal'); m?.hide(); bulkAssignUserId.value = null; }
async function submitBulkAssign() {
    const uid = bulkAssignUserId.value ? Number(bulkAssignUserId.value) : null;
    if (uid == null) return;
    const ids = [...selectedIds.value];
    await router.put(route('tasks.bulk_update'), { ids, assignee_id: uid }, {
        preserveScroll: true,
        onSuccess: () => { closeBulkAssign(); },
    });
}

async function bulkUpdateStatus(value) {
    if (!value) return;
    const ids = [...selectedIds.value];
    await router.put(route('tasks.bulk_update'), { ids, status: value }, { preserveScroll: true });
}

async function bulkUpdatePriority(value) {
    if (!value) return;
    const ids = [...selectedIds.value];
    await router.put(route('tasks.bulk_update'), { ids, priority: value }, { preserveScroll: true });
}

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
        case 'created': return 'Создана';
        case 'assigned': return 'Назначена';
        case 'in_progress': return 'В работе';
        case 'on_review': return 'На проверку';
        case 'rework': return 'Доработать';
        case 'accepted': return 'Принята';
        case 'question': return 'Вопрос';
        case 'cancelled': return 'Отменена';
        case 'done': return 'Принята'; // backward compatibility
        default: return 'Создана';
    }
}
function statusClass(status) {
    switch (status) {
        case 'created': return 'bg-secondary';
        case 'assigned': return 'bg-primary';
        case 'in_progress': return 'bg-info';
        case 'on_review': return 'bg-warning';
        case 'rework': return 'bg-danger';
        case 'accepted': return 'bg-success';
        case 'question': return 'bg-purple';
        case 'cancelled': return 'bg-dark';
        case 'done': return 'bg-success'; // backward compatibility
        default: return 'bg-secondary';
    }
}

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
    { value: 'high', label: 'Высокий' },
];

function priorityLabel(priority) {
    switch (priority) {
        case 'low': return 'Низкий';
        case 'medium': return 'Средний';
        case 'high': return 'Высокий';
        default: return 'Средний';
    }
}

function priorityClass(priority) {
    switch (priority) {
        case 'low': return 'bg-secondary';
        case 'medium': return 'bg-info';
        case 'high': return 'bg-danger';
        default: return 'bg-info';
    }
}

function updateTaskStatus(task, status) {
    if (!task || !status) return;
    
    // Update the local state optimistically
    const taskToUpdate = items.value.find(t => t.id === task.id);
    if (taskToUpdate) {
        taskToUpdate.status = status;
    }
    
    // Make the API call
    router.put(
        route('brands.tasks.update', { brand: task.brand_id, task: task.id }), 
        { status },
        {
            preserveScroll: true,
            onError: () => {
                // Revert the optimistic update on error
                if (taskToUpdate) {
                    taskToUpdate.status = task.status;
                }
            }
        }
    );
}

function updateTaskPriority(task, priority) {
    if (!task || !priority) return;
    router.put(
        route('brands.tasks.update', { brand: task.brand_id, task: task.id }), 
        { priority }, 
        { 
            preserveScroll: true,
            onSuccess: () => {
                // Update local state
                const taskToUpdate = items.value.find(t => t.id === task.id);
                if (taskToUpdate) {
                    taskToUpdate.priority = priority;
                }
            }
        }
    );
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
const assigningTask = ref(null);
const assignUserId = ref(null);
function openAssign(task) { assigningTask.value = task; assignUserId.value = task.assignee_id || null; const m = getBsModal('assignModal'); m?.show(); }
function closeAssign() { const m = getBsModal('assignModal'); m?.hide(); assigningTask.value = null; assignUserId.value = null; }
async function submitAssign() {
    if (!assigningTask.value) return;
    const payload = { assignee_id: assignUserId.value ? Number(assignUserId.value) : null, status: assignUserId.value ? 'assigned' : 'created' };
    await router.put(route('brands.tasks.update', { brand: assigningTask.value.brand_id, task: assigningTask.value.id }), payload, { preserveScroll: true });
    closeAssign();
}

// Bulk actions

// Create task modal/form
const createForm = ref({
    name: '',
    brand_id: '',
    task_type_id: '',
    article_id: '',
    assignee_id: '',
    priority: 'medium' // Default priority
});
const brandArticles = ref([]);
const creating = ref(false);

// Helper to get or create a Bootstrap Modal instance
function getBsModal(id) {
    const el = document.getElementById(id);
    if (!el) {
        console.error(`Modal element with id '${id}' not found`);
        return null;
    }

    try {
        // Get existing instance or create a new one
        return Modal.getInstance(el) || new Modal(el);
    } catch (error) {
        console.error('Error initializing modal:', error);
        return null;
    }
}

// Article search for create modal
const articleSearch = ref('');
const showArticleDropdown = ref(false);

const selectedArticle = computed(() => {
    if (!createForm.value.article_id) return null;
    return brandArticles.value.find(a => a.id == createForm.value.article_id);
});

const filteredArticles = computed(() => {
    if (!articleSearch.value.trim()) return brandArticles.value;
    const q = articleSearch.value.toLowerCase();
    return brandArticles.value.filter(a => a.name.toLowerCase().includes(q));
});

function onArticleSearchInput() {
    showArticleDropdown.value = true;
}

function hideDropdown() {
    setTimeout(() => showArticleDropdown.value = false, 200);
}

function selectArticle(article) {
    createForm.value.article_id = article.id;
    articleSearch.value = article.name;
    showArticleDropdown.value = false;
}

// Watch for selected article changes
watch(selectedArticle, (newVal) => {
    if (newVal) {
        articleSearch.value = newVal.name;
    } else {
        articleSearch.value = '';
    }
});

// Watch for brand changes to reset article
watch(() => createForm.value.brand_id, () => {
    createForm.value.article_id = '';
    articleSearch.value = '';
});

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

// Prefer direct Yandex "sizes" URL (ORIGINAL > XXXL > XXL > XL > L > M), then preview, else fallback
function getBestSizeUrl(item) {
    const sizes = Array.isArray(item?.sizes) ? item.sizes : [];
    if (sizes.length) {
        const pref = ['ORIGINAL', 'XXXL', 'XXL', 'XL', 'L', 'M', 'DEFAULT'];
        for (const name of pref) {
            const found = sizes.find(s => s?.name === name && s?.url);
            if (found?.url) return found.url;
        }
        if (sizes[0]?.url) return sizes[0].url;
    }
    if (item?.preview) return item.preview;
    if (item?.file) return item.file;
    return null;
}

async function openYandexItemDirect(item) {
    if (!item || item.type !== 'file') return;
    // Prefer Python-based resolver via backend with --input JSON
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        const res = await fetch(route('integrations.yandex.resolve_from_item'), {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
            },
            body: JSON.stringify({ item })
        });
        if (res.ok) {
            const data = await res.json();
            const href = data?.href;
            if (href) {
                // Pipe via temp public download for consistent inline serving
                const tempRes = await fetch(route('integrations.yandex.download_public_to_temp'), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
                    },
                    body: JSON.stringify({ direct_url: href })
                });
                if (tempRes.ok) {
                    const temp = await tempRes.json();
                    if (temp?.url) { openLightbox(temp.url, { id: temp.id, path: temp.path }); return; }
                }
                openLightbox(href);
                return;
            }
        }
    } catch (e) {
        console.warn('resolve_from_item failed, trying direct URL', e);
    }

    // Fallback: try direct sizes URL if available
    const direct = getBestSizeUrl(item);
    if (direct) {
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            const tempRes = await fetch(route('integrations.yandex.download_public_to_temp'), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
                },
                body: JSON.stringify({ direct_url: direct })
            });
            if (tempRes.ok) {
                const temp = await tempRes.json();
                if (temp?.url) { openLightbox(temp.url, { id: temp.id, path: temp.path }); return; }
            }
        } catch { }
        openLightbox(direct);
        return;
    }

    // Final fallback: backend public download url flow
    await downloadYandexItem(item);
}

function openCreate() {
    createForm.value = { name: '', brand_id: '', task_type_id: '', article_id: '', assignee_id: '', priority: 'medium' };
    brandArticles.value = [];

    // Show the modal
    const modal = getBsModal('createModal');
    if (modal) {
        modal.show();
    }
}

async function submitCreate() {
    const payload = {
        brand_id: createForm.value.brand_id ? Number(createForm.value.brand_id) : null,
        task_type_id: createForm.value.task_type_id ? Number(createForm.value.task_type_id) : null,
        article_id: createForm.value.article_id ? Number(createForm.value.article_id) : null,
        name: createForm.value.name?.trim() || undefined,
        assignee_id: createForm.value.assignee_id ? Number(createForm.value.assignee_id) : null,
        priority: createForm.value.priority || 'medium',
    };
    if (!payload.brand_id || !payload.task_type_id || !payload.article_id) return;
    creating.value = true;
    router.post(route('tasks.store'), payload, {
        onSuccess: () => { const m = getBsModal('createModal'); m?.hide(); fetchPage(true); },
        onFinish: () => { creating.value = false; },
    });
}

// Edit task modal
// Edit Task Modal (Bootstrap)
const editingTask = ref(null);
const editForm = ref({ name: '', brand_id: '', task_type_id: '', article_id: '', assignee_id: '' });
const editBrandArticles = ref([]);
const editArticleSearch = ref('');
const showEditArticleDropdown = ref(false);

const editSelectedArticle = computed(() => {
    if (!editForm.value.article_id) return null;
    return editBrandArticles.value.find(a => a.id == editForm.value.article_id);
});

const editFilteredArticles = computed(() => {
    if (!editArticleSearch.value.trim()) return editBrandArticles.value;
    const q = editArticleSearch.value.toLowerCase();
    return editBrandArticles.value.filter(a => a.name.toLowerCase().includes(q));
});

async function loadEditArticlesForBrand(brandId) {
    editBrandArticles.value = [];
    if (!brandId) return;
    try {
        const url = route('brands.articles.index', { brand: Number(brandId) });
        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        const data = await res.json();
        editBrandArticles.value = Array.isArray(data?.data) ? data.data : [];
    } catch (e) { console.error(e); }
}

function openEdit(task) {
    editingTask.value = task;
    editForm.value = {
        name: task.name || '',
        brand_id: String(task.brand_id || ''),
        task_type_id: String(task.task_type_id || ''),
        article_id: String(task.article_id || ''),
        assignee_id: String(task.assignee_id || ''),
    };
    editArticleSearch.value = '';
    editBrandArticles.value = [];
    loadEditArticlesForBrand(task.brand_id);
    const m = getBsModal('editTaskModal');
    m?.show();
}

function closeEdit() {
    const m = getBsModal('editTaskModal');
    m?.hide();
    editingTask.value = null;
    editForm.value = { name: '', brand_id: '', task_type_id: '', article_id: '', assignee_id: '' };
    editArticleSearch.value = '';
}

async function submitEdit() {
    if (!editingTask.value) return;
    const payload = {
        brand_id: editForm.value.brand_id ? Number(editForm.value.brand_id) : null,
        task_type_id: editForm.value.task_type_id ? Number(editForm.value.task_type_id) : null,
        article_id: editForm.value.article_id ? Number(editForm.value.article_id) : null,
        name: editForm.value.name?.trim() || undefined,
        assignee_id: editForm.value.assignee_id ? Number(editForm.value.assignee_id) : null,
    };
    if (!payload.brand_id || !payload.task_type_id || !payload.article_id) return;
    router.put(route('brands.tasks.update', { brand: editingTask.value.brand_id, task: editingTask.value.id }), payload, { preserveScroll: true });
    closeEdit();
}

function onEditArticleSearchInput() {
    showEditArticleDropdown.value = true;
}

function hideEditDropdown() {
    setTimeout(() => showEditArticleDropdown.value = false, 200);
}

function selectEditArticle(article) {
    editForm.value.article_id = article.id;
    editArticleSearch.value = article.name;
    showEditArticleDropdown.value = false;
}

// Watch for edit selected article changes
watch(editSelectedArticle, (newVal) => {
    if (newVal) {
        editArticleSearch.value = newVal.name;
    } else {
        editArticleSearch.value = '';
    }
});

// Watch for edit brand changes to reset article
watch(() => editForm.value.brand_id, () => {
    editForm.value.article_id = '';
    editArticleSearch.value = '';
});

// Removed offcanvas, comments, and Yandex files for simplified single-assignment flow

// Row actions: edit / delete
// Rename modal state (Bootstrap)
const renaming = ref(null);
const renameName = ref('');
function onEditTask(t) {
    openEdit(t);
}
function cancelRename() { const m = getBsModal('renameModal'); m?.hide(); renaming.value = null; renameName.value = ''; }
function submitRename() {
    if (!renaming.value || !renameName.value.trim()) return;
    renaming.value.name = renameName.value.trim();
    const m = getBsModal('renameModal'); m?.hide();
    renaming.value = null;
    renameName.value = '';
}

// Task actions

// Offcanvas state and opener (RESULT: comments/files)
const offcanvasOpen = ref(false);
const oc = ref({ brandId: null, brandName: '', taskId: null, taskName: '', typeName: '', typePrefix: '', articleName: '' });
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
onMounted(async () => {
    await nextTick();
    if (offcanvasEl.value && !offcanvasInstance) {
        offcanvasInstance = new Offcanvas(offcanvasEl.value, { backdrop: true, keyboard: true, scroll: true });
        hasOffcanvas.value = true;
        offcanvasEl.value.addEventListener('show.bs.offcanvas', () => { offcanvasOpen.value = true; });
        offcanvasEl.value.addEventListener('hidden.bs.offcanvas', () => { offcanvasOpen.value = false; });
    }
});
watch(offcanvasEl, (el) => {
    if (el && !offcanvasInstance) {
        offcanvasInstance = new Offcanvas(el, { backdrop: true, keyboard: true, scroll: true });
        hasOffcanvas.value = true;
        el.addEventListener('show.bs.offcanvas', () => { offcanvasOpen.value = true; });
        el.addEventListener('hidden.bs.offcanvas', () => { offcanvasOpen.value = false; });
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
        typeName: task.type?.name || '',
        typePrefix: task.type?.prefix || '',
        articleName: task.article?.name || '',
    };
    offcanvasOpen.value = true;
    if (!offcanvasInstance && offcanvasEl.value) {
        offcanvasInstance = new Offcanvas(offcanvasEl.value, { backdrop: true, keyboard: true, scroll: true });
        hasOffcanvas.value = true;
    }
    if (offcanvasInstance) offcanvasInstance.show();
    activeOcTab.value = 'comments';
    comments.value = [];
    newComment.value = '';
    loadComments();
}

async function openFilesOffcanvas(task) {
    const brandName = task.brand?.name || (props.brands.find(b => b.id === task.brand_id)?.name) || '';
    oc.value = {
        brandId: task.brand_id,
        brandName,
        taskId: task.id,
        taskName: task.name || task.article?.name || '',
        typeName: task.type?.name || '',
        typePrefix: task.type?.prefix || '',
        articleName: task.article?.name || '',
    };

    // Set the public folder URL from the task's public_link
    publicFolderUrl.value = task.public_link || '';

    offcanvasOpen.value = true;
    if (!offcanvasInstance && offcanvasEl.value) {
        offcanvasInstance = new Offcanvas(offcanvasEl.value, { backdrop: true, keyboard: true, scroll: true });
        hasOffcanvas.value = true;
    }
    if (offcanvasInstance) offcanvasInstance.show();
    activeOcTab.value = 'files';
    loadYandexFiles();
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

// Lightbox state and helpers
const lightboxSrc = ref('');
const lightboxTemp = ref(null); // { id, path } for cleanup
function openLightbox(src, tempMeta = null) { lightboxSrc.value = src; lightboxTemp.value = tempMeta; const m = getBsModal('lightboxModal'); m?.show(); }
function closeLightbox() {
    const m = getBsModal('lightboxModal'); m?.hide();
    lightboxSrc.value = '';
    // Cleanup temp file if any
    const meta = lightboxTemp.value;
    lightboxTemp.value = null;
    if (meta?.path || meta?.id) {
        try {
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
        } catch (e) { console.warn('Temp cleanup failed', e); }
    }
}
function isImageName(name) {
    return /\.(jpe?g|png|gif|webp|bmp|svg|heic|heif)$/i.test(name || '');
}
async function viewYandexItemInLightbox(item) {
    if (!item || item.type !== 'file') return;
    if (!isImageName(item.name)) {
        // Fallback: open in new tab
        return downloadYandexItem(item);
    }
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
            // Ask backend to download to temp public file for safer inline viewing
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            const tempRes = await fetch(route('integrations.yandex.download_public_to_temp'), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
                },
                body: JSON.stringify({ direct_url: data.href })
            });
            if (tempRes.ok) {
                const temp = await tempRes.json();
                if (temp?.url) openLightbox(temp.url, { id: temp.id, path: temp.path });
                else openLightbox(data.href);
            } else {
                openLightbox(data.href);
            }
        }
    } catch (e) { console.error(e); }
}

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

function sanitizeName(name) {
    if (!name) return '';
    // Replace backslashes and control characters with space
    name = name.replace(/[\\\n\r\t]/g, ' ');
    // Replace forward slashes with dashes
    name = name.replace(/\//g, '-');
    return name.trim();
}

function yandexFolderPath() {
    const brandName = sanitizeName(oc.value.brandName);
    const typeName = sanitizeName(oc.value.typeName);
    // Prefer article name as leaf name; fallback to taskName
    const leafBase = sanitizeName(oc.value.articleName || oc.value.taskName);
    if (!brandName || !leafBase) return null;
    const prefix = (oc.value.typePrefix || '');
    const leaf = `${prefix ? prefix + '_' : ''}${leafBase}`;
    return typeName
        ? `disk:/${brandName}/${typeName}/${leaf}`
        : `disk:/${brandName}/${leaf}`;
}

async function loadYandexFiles() {
    const path = yandexFolderPath();
    if (!path) return;
    filesLoading.value = true;
    filesError.value = '';
    try {
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
onMounted(async () => {
    await nextTick();
    if (sourceOffcanvasEl.value && !sourceOffcanvasInstance) {
        sourceOffcanvasInstance = new Offcanvas(sourceOffcanvasEl.value, { backdrop: true, keyboard: true, scroll: true });
        hasSourceOffcanvas.value = true;
        sourceOffcanvasEl.value.addEventListener('show.bs.offcanvas', () => { sourceOffcanvasOpen.value = true; });
        sourceOffcanvasEl.value.addEventListener('hidden.bs.offcanvas', () => { sourceOffcanvasOpen.value = false; });
    }
});
watch(sourceOffcanvasEl, (el) => {
    if (el && !sourceOffcanvasInstance) {
        sourceOffcanvasInstance = new Offcanvas(el, { backdrop: true, keyboard: true, scroll: true });
        hasSourceOffcanvas.value = true;
        el.addEventListener('show.bs.offcanvas', () => { sourceOffcanvasOpen.value = true; });
        el.addEventListener('hidden.bs.offcanvas', () => { sourceOffcanvasOpen.value = false; });
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
    
    // Initialize the offcanvas if it doesn't exist
    if (!sourceOffcanvasInstance && sourceOffcanvasEl.value) {
        sourceOffcanvasInstance = new Offcanvas(sourceOffcanvasEl.value, { 
            backdrop: true, 
            keyboard: true, 
            scroll: true 
        });
        hasSourceOffcanvas.value = true;
    }
    
    // Show the offcanvas
    if (sourceOffcanvasInstance) {
        sourceOffcanvasInstance.show();
    } else {
        sourceOffcanvasOpen.value = true;
    }
    
    loadSourceComments();
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
    <DashByteLayout>

        <div class="card mb-2">
            <div class="card-header">
                <div>
                    <div class="card-title">Список задач</div>
                    <div class="card-subtitle">Просмотр и управление всеми задачами.</div>
                </div>
                <div class="card-actions d-flex flex-wrap align-items-center">
                    <!-- Global search -->
                    <div class="input-group input-group-flat w-auto me-2">
                        <span class="input-group-text"><i class="ti ti-search"></i></span>
                        <input type="text" class="form-control form-control-sm" v-model="globalSearch"
                            placeholder="Общий поиск..." autocomplete="off" />
                    </div>
                    <!-- Name search -->
                    <div class="input-group input-group-flat w-auto me-2">
                        <span class="input-group-text"><i class="ti ti-letter-case"></i></span>
                        <input type="text" class="form-control form-control-sm" v-model="search"
                            placeholder="Название..." autocomplete="off" />
                    </div>
                    <!-- Brand filter -->
                    <select class="form-select form-select-sm w-auto me-2" v-model="brandFilter">
                        <option value="">Все бренды</option>
                        <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                    <!-- Article filter (dependent on brand) -->
                    <select class="form-select form-select-sm w-auto me-2" v-model="articleFilter"
                        :disabled="!brandFilter" style="display:none;">
                        <option value="">Все артикулы</option>
                        <option v-for="a in filterArticles" :key="a.id" :value="a.id">{{ a.name }}</option>
                    </select>
                    <!-- Executor filter -->
                    <select class="form-select form-select-sm w-auto me-2" v-model="performerFilter">
                        <option value="">Все исполнители</option>
                        <option v-for="u in performers" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
                    <!-- Created date filter -->
                    <select class="form-select form-select-sm w-auto me-2" v-model="createdFilter">
                        <option value="">Все даты</option>
                        <option value="today">Сегодня</option>
                        <option value="yesterday">Вчера</option>
                        <option value="date">Дата…</option>
                    </select>
                    <input v-if="createdFilter === 'date'" type="date" class="form-control w-auto me-2"
                        v-model="createdDate" />

                    <!-- Action buttons -->
                    <button class="btn btn-sm btn-secondary me-2" @click="resetFilters">
                        <i class="ti ti-filter-off me-1"></i> СБРОСИТЬ ФИЛЬТРЫ
                    </button>
                    <button class="btn btn-sm btn-primary" @click="openCreate">
                        <i class="ti ti-plus me-1"></i> НОВАЯ ЗАДАЧА
                    </button>
                </div>
            </div>
        </div>

        <div class="card mb-2" v-if="anySelected">
            <div class="card-header">
                <!-- Bulk actions toolbar -->
                <div class="row">
                    <div class="col-12 d-flex align-items-center flex-wrap gap-2">
                        <div class="me-3">
                            <i class="ti ti-selector me-1"></i> Выбрано: {{ selectedIds.length }}
                        </div>

                        <!-- Bulk assign performer -->
                        <button class="btn btn-sm btn-outline-primary" @click="openBulkAssign">
                            <i class="ti ti-user-plus me-1"></i> Добавить исполнителя
                        </button>

                        <!-- Bulk status change -->
                        <div class="d-flex align-items-center gap-1">
                            <span class="text-secondary small">Статус:</span>
                            <select class="form-select form-select-sm w-auto"
                                @change="(e) => bulkUpdateStatus(e.target.value)">
                                <option value="" selected disabled>Выбрать…</option>
                                <option v-for="s in statusOptions" :key="s.value" :value="s.value">{{ s.label }}
                                </option>
                            </select>
                        </div>

                        <!-- Bulk priority change -->
                        <div class="d-flex align-items-center gap-1">
                            <span class="text-secondary small">Приоритет:</span>
                            <select class="form-select form-select-sm w-auto"
                                @change="(e) => bulkUpdatePriority(e.target.value)">
                                <option value="" selected disabled>Выбрать…</option>
                                <option v-for="p in priorityOptions" :key="p.value" :value="p.value">{{ p.label
                                }}</option>
                            </select>
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
        </div>

        <div class="table-responsive">
            <table class="table card-table table-hover">
                <thead class="table-light">
                    <tr>
                        <th class="w-1"><input type="checkbox" class="form-check-input" v-model="selectAllVisible" />
                        </th>
                        <th class="text-start">Создан</th>
                        <th class="text-start">Наименование задачи</th>
                        <th class="text-start">Бренд, Артикул</th>
                        <th class="text-start">Тип</th>
                        <th class="text-center">Исполнитель</th>
                        <th class="text-center">Исходник</th>
                        <th class="text-center">Результат</th>
                        <th class="text-start">Статус</th>
                        <th class="text-start">Приоритет</th>
                        <th class="text-end">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="displayedTasks.length === 0">
                        <td colspan="12" class="text-center text-secondary py-4">Нет задач</td>
                    </tr>
                    <tr v-for="t in displayedTasks" :key="t.id" style="font-size: 13px !important">
                        <td style="vertical-align: middle;">
                            <input type="checkbox" class="form-check-input" :checked="isSelected(t.id)"
                                @change="toggleRow(t.id)" />
                        </td>
                        <td style="vertical-align: middle;">{{ new Date(t.created_at).toLocaleString('ru-RU') }}</td>
                        <td class="text-start" style="vertical-align: middle;">{{ t.name || t.article?.name || '' }}
                        </td>
                        <td class="text-truncate" style="vertical-align: middle;">{{t.brand?.name || (brands.find(b =>
                            b.id === t.brand_id)?.name)}}<br />{{ t.article?.name || '' }}</td>
                        <td style="vertical-align: middle;">{{ t.type?.name || '' }}</td>
                        <td class="text-center" style="vertical-align: middle;">
                            <div class="d-flex justify-content-center gap-2">
                                <span v-if="t.assignee?.name" class="text-secondary">{{ t.assignee.name }}</span>
                                <button class="btn btn-sm btn-outline-primary" @click="openAssign(t)">
                                    {{ t.assignee?.name ? 'Изменить' : 'Назначить' }}
                                </button>
                            </div>
                        </td>
                        <td class="text-center" style="vertical-align: middle;">
                            <div class="d-flex gap-1">
                                <button class="btn btn-icon btn-ghost-secondary"
                                    @click="openSourceCommentsOffcanvas(t)"><svg xmlns="http://www.w3.org/2000/svg"
                                        width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                        class="icon icon-tabler icons-tabler-outline icon-tabler-message">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M8 9h8" />
                                        <path d="M8 13h6" />
                                        <path
                                            d="M18 4a3 3 0 0 1 3 3v8a3 3 0 0 1 -3 3h-5l-5 3v-3h-2a3 3 0 0 1 -3 -3v-8a3 3 0 0 1 3 -3h12z" />
                                    </svg></button>
                                <!-- <button class="btn btn-sm btn-outline-primary" @click="openFolder(t)">ФАЙЛЫ (скрыто)</button> -->
                            </div>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <button class="btn btn-icon btn-ghost-secondary" @click="openCommentsOffcanvas(t)"><svg
                                        xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round"
                                        class="icon icon-tabler icons-tabler-outline icon-tabler-message">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M8 9h8" />
                                        <path d="M8 13h6" />
                                        <path
                                            d="M18 4a3 3 0 0 1 3 3v8a3 3 0 0 1 -3 3h-5l-5 3v-3h-2a3 3 0 0 1 -3 -3v-8a3 3 0 0 1 3 -3h12z" />
                                    </svg></button>
                                <button class="btn btn-icon btn-ghost-primary" @click="openFilesOffcanvas(t)"><svg
                                        xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round"
                                        class="icon icon-tabler icons-tabler-outline icon-tabler-files">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M15 3v4a1 1 0 0 0 1 1h4" />
                                        <path
                                            d="M18 17h-7a2 2 0 0 1 -2 -2v-10a2 2 0 0 1 2 -2h4l5 5v7a2 2 0 0 1 -2 2z" />
                                        <path d="M16 17v2a2 2 0 0 1 -2 2h-7a2 2 0 0 1 -2 -2v-10a2 2 0 0 1 2 -2h2" />
                                    </svg></button>
                            </div>
                        </td>
                        <td style="vertical-align: middle;">
                            <div class="d-flex align-items-center gap-2">
                                <select class="form-select form-select-sm w-auto" :value="t.status"
                                    @change="(e) => updateTaskStatus(t, e.target.value)">
                                    <option v-for="s in statusOptions" :key="s.value" :value="s.value">
                                        {{ s.label }}</option>
                                </select>
                            </div>
                        </td>
                        <td style="vertical-align: middle;">
                            <div class="d-flex align-items-center gap-2">
                                <select class="form-select form-select-sm w-auto" :value="t.priority || 'medium'"
                                    @change="(e) => updateTaskPriority(t, e.target.value)">
                                    <option v-for="p in priorityOptions" :key="p.value" :value="p.value">{{
                                        p.label }}</option>
                                </select>
                            </div>
                        </td>
                        <td class="text-nowrap">
                            <div class="btn-list d-flex flex-nowrap align-items-center justify-content-end gap-2">
                                <button class="btn btn-icon btn-ghost-secondary" @click="copyTaskPublicLink(t)"
                                    title="Копировать ссылку">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round"
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
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round"
                                        class="icon icon-tabler icons-tabler-outline icon-tabler-link">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M9 15l6 -6" />
                                        <path d="M11 6l.463 -.536a5 5 0 0 1 7.071 7.072l-.534 .464" />
                                        <path
                                            d="M13 18l-.397 .534a5.068 5.068 0 0 1 -7.127 0a4.972 4.972 0 0 1 0 -7.071l.524 -.463" />
                                    </svg>
                                </button>
                                <button class="btn btn-icon btn-ghost-primary" @click="onEditTask(t)"
                                    title="Редактировать">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24"
                                        viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" />
                                        <path
                                            d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z" />
                                        <path d="M16 5l3 3" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Create Modal -->
        <teleport to="body">
            <div class="modal fade" id="createModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
                aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Создать задачу</h5>
                            <button type="button" class="btn-close" @click="() => getBsModal('createModal')?.hide()"
                                aria-label="Close"></button>
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
                                        <option v-for="tt in taskTypes" :key="tt.id" :value="tt.id">{{ tt.name }}
                                        </option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Артикул</label>
                                    <div class="position-relative">
                                        <input type="text" class="form-control" v-model="articleSearch"
                                            @input="onArticleSearchInput" @focus="showArticleDropdown = true"
                                            @blur="hideDropdown"
                                            :placeholder="selectedArticle ? selectedArticle.name : 'Выберите артикул'"
                                            :disabled="!createForm.brand_id" />
                                        <div v-show="showArticleDropdown && filteredArticles.length"
                                            class="position-absolute top-100 start-0 w-100 bg-white border rounded shadow"
                                            style="z-index: 1000; max-height: 200px; overflow-y: auto;">
                                            <div v-for="a in filteredArticles" :key="a.id"
                                                class="p-2 cursor-pointer hover-bg-light" @click="selectArticle(a)">
                                                {{ a.name }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Название (необязательно)</label>
                                    <input type="text" class="form-control" v-model="createForm.name"
                                        placeholder="По умолчанию — название статьи" />
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Исполнитель</label>
                                    <select class="form-select" v-model="createForm.assignee_id">
                                        <option value="">Не назначен</option>
                                        <option v-for="u in performers" :key="u.id" :value="u.id">
                                            {{ u.name }}<span v-if="u.is_blocked"> — ЗАБЛОКИРОВАН</span>
                                        </option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Приоритет</label>
                                    <select class="form-select" v-model="createForm.priority">
                                        <option v-for="p in priorityOptions" :key="p.value" :value="p.value">
                                            {{ p.label }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn me-auto"
                                @click="() => getBsModal('createModal')?.hide()">Отмена</button>
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

        <!-- Bulk Assign Performer Modal -->
        <teleport to="body">
            <div class="modal fade" id="bulkAssignModal" data-bs-backdrop="static" data-bs-keyboard="false"
                tabindex="-1" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Добавить исполнителя ({{ selectedIds.length }})</h5>
                            <button type="button" class="btn-close" @click="closeBulkAssign"
                                aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Исполнитель</label>
                                <select class="form-select" v-model="bulkAssignUserId">
                                    <option :value="null">— Не выбрано —</option>
                                    <option v-for="u in performers" :key="u.id" :value="u.id">{{ u.name }}</option>
                                </select>
                            </div>
                            <div class="text-secondary small">Будут обновлены выбранные задания: назначен исполнитель и
                                статус «Назначено».</div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-link" @click="closeBulkAssign">Отмена</button>
                            <button type="button" class="btn btn-primary" :disabled="!bulkAssignUserId"
                                @click="submitBulkAssign">Сохранить</button>
                        </div>
                    </div>
                </div>
            </div>
        </teleport>

        <!-- Edit Task Modal -->
        <teleport to="body">
            <div class="modal fade" id="editTaskModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
                aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Редактировать задание</h5>
                            <button type="button" class="btn-close" @click="closeEdit" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Бренд</label>
                                    <select class="form-select" v-model="editForm.brand_id"
                                        @change="loadEditArticlesForBrand(editForm.brand_id)">
                                        <option value="">Выберите бренд</option>
                                        <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Тип задачи</label>
                                    <select class="form-select" v-model="editForm.task_type_id">
                                        <option value="">Выберите тип</option>
                                        <option v-for="tt in taskTypes" :key="tt.id" :value="tt.id">{{ tt.name }}
                                        </option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Артикул</label>
                                    <div class="position-relative">
                                        <input type="text" class="form-control" v-model="editArticleSearch"
                                            @input="onEditArticleSearchInput" @focus="showEditArticleDropdown = true"
                                            @blur="hideEditDropdown"
                                            :placeholder="editSelectedArticle ? editSelectedArticle.name : 'Выберите артикул'"
                                            :disabled="!editForm.brand_id" />
                                        <div v-show="showEditArticleDropdown && editFilteredArticles.length"
                                            class="position-absolute top-100 start-0 w-100 bg-white border rounded shadow"
                                            style="z-index: 1000; max-height: 200px; overflow-y: auto;">
                                            <div v-for="a in editFilteredArticles" :key="a.id"
                                                class="p-2 cursor-pointer hover-bg-light" @click="selectEditArticle(a)">
                                                {{ a.name }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Наименование задачи</label>
                                    <input type="text" class="form-control" v-model="editForm.name"
                                        placeholder="Наименование задачи" />
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Исполнитель</label>
                                    <select class="form-select" v-model="editForm.assignee_id">
                                        <option value="">Не назначен</option>
                                        <option v-for="u in performers" :key="u.id" :value="u.id">
                                            {{ u.name }}<span v-if="u.is_blocked"> — ЗАБЛОКИРОВАН</span>
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn me-auto" @click="closeEdit">Отмена</button>
                            <button type="button" class="btn btn-primary"
                                :disabled="!editForm.brand_id || !editForm.task_type_id || !editForm.article_id"
                                @click="submitEdit">
                                Сохранить
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </teleport>

        <!-- Assign Executor Modal -->
        <teleport to="body">
            <div class="modal fade" id="assignModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
                aria-hidden="true">
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
                                <option v-for="u in performers" :key="u.id" :value="u.id">{{ u.name }}<span
                                        v-if="u.is_blocked"> —
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
            <div class="modal fade" id="renameModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
                aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Переименовать задачу</h5>
                            <button type="button" class="btn-close" @click="cancelRename" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
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

        <!-- Right offcanvas -->
        <teleport to="body">
            <div class="offcanvas offcanvas-end w-50" id="task-offcanvas" ref="offcanvasEl" tabindex="-1" role="dialog"
                aria-hidden="true" :aria-labelledby="'task-offcanvas-title'"
                :class="{ show: offcanvasOpen && !hasOffcanvas }"
                :style="offcanvasOpen && !hasOffcanvas ? 'visibility: visible; z-index: 1045;' : ''">
                <div class="offcanvas-header">
                    <h5 class="offcanvas-title" :id="'task-offcanvas-title'">
                        {{ oc.brandName }} / {{ oc.taskName }}
                    </h5>
                    <button type="button" class="btn-close text-reset" aria-label="Close"
                        @click="closeOffcanvas"></button>
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
                                <button class="btn btn-outline-secondary" type="button"
                                    @click="copyFolderPath">Копировать</button>
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
                                            <span class="badge"
                                                :class="it.type === 'dir' ? 'bg-secondary' : 'bg-primary'">{{ it.type
                                                    ===
                                                    'dir' ?
                                                    'Папка' : 'Файл' }}</span>
                                            <span>{{ it.name }}</span>
                                            <span v-if="it.size && it.type === 'file'" class="text-secondary small">{{
                                                (it.size / 1024 / 1024).toFixed(2) }} MB</span>
                                        </div>
                                        <div>
                                            <button v-if="it.type === 'file'" class="btn btn-sm btn-outline-primary"
                                                @click="() => openYandexItemDirect(it)">ПОСМОТРЕТЬ</button>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Photographer upload UI moved below the list -->
                        <div v-if="oc.ownership === 'Photographer'" class="mt-3 d-flex align-items-center gap-2">
                            <input type="file" accept="image/*" multiple ref="fileInputRef" class="d-none"
                                @change="onFilesChosen" />
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
                                <li v-for="c in comments" :key="c.id"
                                    class="mb-3 d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="fw-bold">{{ c.user?.name || '—' }} <span
                                                class="text-secondary small">{{ new
                                                    Date(c.created_at).toLocaleString('ru-RU') }}</span></div>
                                        <div v-if="c.content" style="white-space: pre-wrap;">{{ c.content }}</div>
                                        <div v-if="c.image_path" class="mt-2">
                                            <img :src="'/storage/' + c.image_path"
                                                class="img-fluid rounded cursor-pointer"
                                                style="max-width: 300px; max-height: 200px;"
                                                @click="() => openLightbox('/storage/' + c.image_path)" />
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
                                        <button type="button" class="btn btn-secondary me-2"
                                            @click="clearCommentForm">Очистить</button>
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
                    <button type="button" class="btn-close text-reset" aria-label="Close"
                        @click="closeSourceOffcanvas"></button>
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
                            <div v-if="sourceComments.length === 0" class="text-secondary mb-2">Комментариев пока нет.
                            </div>
                            <ul class="list-unstyled">
                                <li v-for="c in sourceComments" :key="c.id"
                                    class="mb-3 d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="fw-bold">{{ c.user?.name || '—' }} <span
                                                class="text-secondary small">{{ new
                                                    Date(c.created_at).toLocaleString('ru-RU') }}</span></div>
                                        <div v-if="c.content" style="white-space: pre-wrap;">{{ c.content }}</div>
                                        <div v-if="c.image_path" class="mt-2">
                                            <img :src="'/storage/' + c.image_path"
                                                class="img-fluid rounded cursor-pointer"
                                                style="max-width: 300px; max-height: 200px;"
                                                @click="() => openLightbox('/storage/' + c.image_path)" />
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
            <div v-if="sourceOffcanvasOpen && !hasSourceOffcanvas" class="modal-backdrop fade show"
                style="z-index: 1040;" @click="closeSourceOffcanvas"></div>
        </teleport>

        <!-- Lightbox Modal -->
        <teleport to="body">
            <div class="modal fade" id="lightboxModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
                aria-hidden="true" @click.self="closeLightbox">
                <div class="modal-dialog modal-xl" role="document">
                    <div class="modal-content bg-dark">
                        <div class="modal-body p-0 d-flex justify-content-center align-items-center"
                            style="min-height: 60vh;">
                            <img v-if="lightboxSrc" :src="lightboxSrc" alt="preview"
                                style="max-width: 100%; max-height: 80vh;" />
                        </div>
                        <div class="modal-footer border-0">
                            <button type="button" class="btn btn-light" @click="closeLightbox">Закрыть</button>
                        </div>
                    </div>
                </div>
            </div>
        </teleport>
    </DashByteLayout>
</template>

<style scoped>
.cursor-pointer {
    cursor: pointer;
}

.hover-bg-light:hover {
    background-color: #f8f9fa;
}
</style>