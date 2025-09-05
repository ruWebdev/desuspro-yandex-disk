<script setup>
import DashByteLayout from '@/layouts/DashByteLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, watch, onBeforeUnmount, nextTick, inject } from 'vue';
import { Offcanvas } from 'bootstrap';

// Additional filters
const brandFilter = ref(''); // brand id as string, empty = all
const articleFilter = ref(''); // free text (merged into search for server-side)
const createdFilter = ref(''); // ''|today|yesterday|date
const createdDate = ref(''); // yyyy-mm-dd
const search = ref('');
const statusFilter = ref('');
const priorityFilter = ref('');

// Server-side list state
const items = ref([]);
const page = ref(1);
const perPage = ref(20);
const hasMore = ref(true);
const loading = ref(false);

function buildQueryParams(resetPage = false) {
    const params = new URLSearchParams();
    params.set('per_page', String(perPage.value));
    params.set('page', resetPage ? '1' : String(page.value));
    // Merge articleFilter into search to reduce API complexity
    const q = [search.value, articleFilter.value].filter(Boolean).join(' ').trim();
    if (q) params.set('search', q);
    if (brandFilter.value) params.set('brand_id', String(brandFilter.value));
    if (statusFilter.value) params.set('status', statusFilter.value);
    if (priorityFilter.value) params.set('priority', priorityFilter.value);
    if (createdFilter.value) {
        params.set('created', createdFilter.value);
        if (createdFilter.value === 'date' && createdDate.value) params.set('date', createdDate.value);
    }
    return params;
}

// Helper to check if a string is a URL
function isUrl(str) {
    try {
        const u = new URL(String(str));
        return !!u.protocol && !!u.host;
    } catch { return false; }
}

// Status and Priority options for filters and dropdown
const statusOptions = [
    { value: 'created', label: 'Создана' },
    { value: 'assigned', label: 'Назначена' },
    { value: 'in_progress', label: 'В работе' },
    { value: 'on_review', label: 'На проверку' },
    { value: 'rework', label: 'Доработать' },
    { value: 'accepted', label: 'Принята' },
    { value: 'question', label: 'Вопрос' },
    { value: 'cancelled', label: 'Отменена' }
];

const priorityOptions = [
    { value: 'low', label: 'Низкий' },
    { value: 'medium', label: 'Средний' },
    { value: 'high', label: 'Высокий' },
    { value: 'urgent', label: 'Срочный' }
];

// Return only allowed status options for performers
function getAvailableStatuses() {
    return statusOptions.filter(opt =>
        ['on_review', 'question'].includes(opt.value)
    );
}

function updateTaskStatus(task, status, event) {
    if (!task || !status) return;

    // Get the toast service
    const toast = inject('toast') || window.toast;

    // Get the status label for the message
    const statusLabel = statusOptions.find(s => s.value === status)?.label || status;

    // Check if status change is allowed
    const allowedStatuses = ['on_review', 'question'];
    if (!allowedStatuses.includes(status)) {
        // Revert the select to previous value
        const select = event?.target;
        if (select) {
            select.value = task.status;
        }
        toast.error('Вы не можете применить к задаче этот статус');
        return;
    }

    // Optimistic update
    const row = items.value.find(x => x.id === task.id);
    const prev = row ? row.status : null;
    if (row) row.status = status;

    // Prepare the request data
    const requestData = { status };
    if (status === 'question') {
        // If changing to 'question' status, include a comment
        const comment = prompt('Пожалуйста, укажите вопрос или комментарий:');
        if (comment === null) {
            // User cancelled the prompt, revert the status
            if (row && prev) row.status = prev;
            return;
        }
        requestData.comment = comment;
    }

    router.put(
        route('performer.tasks.update_status', { task: task.id }),
        requestData,
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.dismiss(toastId);
                toast.success(`Статус изменен на: ${statusLabel}`);
                // Refresh the task list to ensure we have the latest data
                fetchPage(true);
            },
            onError: (errors) => {
                // Revert the optimistic update
                if (row && prev) row.status = prev;
                toast.dismiss(toastId);

                // Show appropriate error message
                let errorMessage = 'Ошибка при обновлении статуса';
                if (errors?.message) {
                    errorMessage = errors.message;
                } else if (errors?.status) {
                    errorMessage = `Ошибка: ${errors.status}`;
                }
                toast.error(errorMessage);
            },
        }
    );
}

async function fetchPage(reset = false) {
    if (loading.value) return;
    loading.value = true;
    try {
        if (reset) { page.value = 1; hasMore.value = true; items.value = []; }
        const params = buildQueryParams(reset);
        const url = route('performer.tasks.search') + `?${params.toString()}`;
        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        const list = Array.isArray(data?.data) ? data.data : [];
        if (reset) items.value = list; else items.value = [...items.value, ...list];
        hasMore.value = Boolean(data?.next_page_url);
        if (!reset) page.value = page.value + 1;
    } catch (e) { console.error(e); }
    finally { loading.value = false; }
}

function onScroll() {
    if (!hasMore.value || loading.value) return;
    const nearBottom = (window.innerHeight + window.scrollY) >= (document.body.offsetHeight - 200);
    if (nearBottom) fetchPage(false);
}

onMounted(() => {
    fetchPage(true);
    window.addEventListener('scroll', onScroll, { passive: true });
});

let debounceTimer = null;
watch([search, statusFilter, priorityFilter, brandFilter, articleFilter, createdFilter, createdDate], () => {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        fetchPage(true);
    }, 300);
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
    if (debounceTimer) clearTimeout(debounceTimer);
});

function resetFilters() {
    search.value = '';
    statusFilter.value = 'all';
    brandFilter.value = '';
    articleFilter.value = '';
    createdFilter.value = '';
    createdDate.value = '';
    fetchPage(true);
}

const availableBrands = computed(() => {
    const map = new Map();
    for (const t of items.value) {
        const id = t.brand_id || t.brand?.id;
        const name = t.brand?.name;
        if (id && name && !map.has(id)) map.set(id, name);
    }
    return Array.from(map.entries()).map(([id, name]) => ({ id, name })).sort((a, b) => a.name.localeCompare(b.name));
});

// Server-provided items are already sorted by backend
const displayed = computed(() => items.value);

// Status helpers with standardized statuses
function statusLabel(status, hasAssignee) {
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
        default: return hasAssignee ? 'Назначена' : '—';
    }
}

function statusClass(status, hasAssignee) {
    switch (status) {
        case 'created': return 'bg-secondary';
        case 'assigned': return hasAssignee ? 'bg-primary' : 'bg-secondary';
        case 'in_progress': return 'bg-info';
        case 'on_review': return 'bg-warning';
        case 'rework': return 'bg-danger';
        case 'accepted': return 'bg-success';
        case 'question': return 'bg-purple';
        case 'cancelled': return 'bg-dark';
        case 'done': return 'bg-success'; // backward compatibility
        default: return hasAssignee ? 'bg-primary' : 'bg-secondary';
    }
}

// Priority helpers
function priorityLabel(priority) {
    switch (priority) {
        case 'low': return 'Низкий';
        case 'medium': return 'Средний';
        case 'high': return 'Высокий';
        case 'urgent': return 'Срочный';
        default: return 'Средний';
    }
}

function priorityClass(priority) {
    switch (priority) {
        case 'low': return 'bg-secondary';
        case 'medium': return 'bg-info';
        case 'high': return 'bg-warning';
        case 'urgent': return 'bg-danger';
        default: return 'bg-info';
    }
}

// Public link helpers (copy/open) similar to All.vue
async function ensurePublicLink(task) {
    if (task.public_link) return true;
    try {
        await useForm({}).post(route('brands.tasks.public_link', { brand: task.brand_id, task: task.id }), { preserveScroll: true });
        return true;
    } catch (e) { console.error(e); return false; }
}
async function copyTaskPublicLink(task) {
    if (!task) return;
    // Ensure link exists
    if (!task.public_link) {
        const ok = await ensurePublicLink(task);
        if (!ok) return;
        // After server creates link, try to use the updated value if available
        if (!task.public_link) {
            alert('Публичная ссылка создана. Нажмите «Скопировать» ещё раз.');
            return;
        }
    }
    const text = task.public_link;
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
function openTaskPublicLink(task) {
    if (!task) return;
    if (task.public_link) {
        window.open(task.public_link, '_blank');
    } else {
        useForm({}).post(route('brands.tasks.public_link', { brand: task.brand_id, task: task.id }), { preserveScroll: true });
    }
}

// Offcanvas state and logic
const offcanvasOpen = ref(false);
const hasOffcanvas = ref(false);
const offcanvasEl = ref(null);
let offcanvasInstance = null;

const oc = ref({ brandId: null, brandName: '', taskId: null, taskName: '', typeName: '', typePrefix: '', articleName: '' });
const activeOcTab = ref('comments'); // comments | files
const currentTask = ref(null);

// Comments state
const commentsLoading = ref(false);
const comments = ref([]);
const newComment = ref('');
const submitting = ref(false);

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

function openOffcanvas(t) {
    const brandName = t.brand?.name || '';
    oc.value = {
        brandId: t.brand_id || t.brand?.id || null,
        brandName,
        taskId: t.id || null,
        taskName: t.name || t.article?.name || '',
        typeName: t.type?.name || t.task_type?.name || t.type_name || '',
        typePrefix: t.type?.prefix || t.task_type?.prefix || t.type_prefix || '',
        articleName: t.article?.name || t.article_name || '',
    };
    currentTask.value = { ...t };
    // Use stored public link for display/open/copy
    publicFolderUrl.value = t.public_link || '';
    activeOcTab.value = 'comments';
    comments.value = [];
    newComment.value = '';
    loadComments();
    loadYandexFiles();
    // If this is a PhotoEditor task, also show photographer's public URL
    loadPhotographerPublicUrl();
    offcanvasOpen.value = true;
    if (!offcanvasInstance && offcanvasEl.value) {
        offcanvasInstance = new Offcanvas(offcanvasEl.value, { backdrop: true, keyboard: true, scroll: true });
        hasOffcanvas.value = true;
    }
    if (offcanvasInstance) offcanvasInstance.show();
}

function closeOffcanvas() { if (offcanvasInstance) offcanvasInstance.hide(); else offcanvasOpen.value = false; }

// Comments API
async function loadComments() {
    if (!oc.value.brandId || !oc.value.taskId) { comments.value = []; return; }
    try {
        commentsLoading.value = true;
        const url = route('brands.tasks.comments.index', { brand: oc.value.brandId, task: oc.value.taskId });
        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        comments.value = await res.json();
    } catch (e) { console.error(e); }
    finally { commentsLoading.value = false; }
}

async function addComment() {
    if (!oc.value.taskId || !newComment.value.trim()) return;
    submitting.value = true;
    try {
        const url = route('brands.tasks.comments.store', { brand: oc.value.brandId, task: oc.value.taskId });
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
    if (!oc.value.taskId) return;
    const url = route('brands.tasks.comments.destroy', { brand: oc.value.brandId, task: oc.value.taskId, comment: c.id });
    try {
        await fetch(url, { method: 'DELETE', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') } });
        comments.value = comments.value.filter(x => x.id !== c.id);
    } catch (e) { console.error(e); }
}

// Source Offcanvas (brand/source comments)
const sourceOffcanvasOpen = ref(false);
const sourceOc = ref({ brandId: null, brandName: '', taskId: null, taskName: '' });
const sourceComments = ref([]);
const sourceFiles = ref([]);
const newSourceComment = ref('');
const sourceSubmitting = ref(false);
const sourceCommentsLoading = ref(false);
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

function closeSourceOffcanvas() { if (sourceOffcanvasInstance) sourceOffcanvasInstance.hide(); else sourceOffcanvasOpen.value = false; }

function openSourceCommentsOffcanvas(task) {
    const brandName = task.brand?.name || '';
    sourceOc.value = {
        brandId: task.brand_id,
        brandName,
        taskId: task.id,
        taskName: task.name || task.article?.name || '',
    };
    sourceFiles.value = Array.isArray(task.source_files) ? task.source_files : [];
    sourceComments.value = [];
    newSourceComment.value = '';
    sourceOffcanvasOpen.value = true;
    loadSourceComments();
    if (sourceOffcanvasInstance) sourceOffcanvasInstance.show();
}

// Load source comments for brand/source offcanvas
async function loadSourceComments() {
    if (!sourceOc.value.brandId || !sourceOc.value.taskId) { sourceComments.value = []; return; }
    try {
        sourceCommentsLoading.value = true;
        const url = route('brands.tasks.source_comments.index', { brand: sourceOc.value.brandId, task: sourceOc.value.taskId });
        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        sourceComments.value = Array.isArray(data) ? data : (Array.isArray(data?.data) ? data.data : []);
    } catch (e) { console.error(e); }
    finally { sourceCommentsLoading.value = false; }
}

async function addSourceComment() {
    if (!sourceOc.value.taskId || !newSourceComment.value.trim()) return;
    sourceSubmitting.value = true;
    try {
        const url = route('brands.tasks.source_comments.store', { brand: sourceOc.value.brandId, task: sourceOc.value.taskId });
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') },
            body: JSON.stringify({ content: newSourceComment.value.trim() }),
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        if (data && data.comment) sourceComments.value.push(data.comment);
        newSourceComment.value = '';
    } catch (e) { console.error(e); }
    finally { sourceSubmitting.value = false; }
}

async function deleteSourceComment(c) {
    if (!sourceOc.value.taskId) return;
    const url = route('brands.tasks.source_comments.destroy', { brand: sourceOc.value.brandId, task: sourceOc.value.taskId, comment: c.id });
    try {
        await fetch(url, { method: 'DELETE', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') } });
        sourceComments.value = sourceComments.value.filter(x => x.id !== c.id);
    } catch (e) { console.error(e); }
}

// Yandex Files state
const filesLoading = ref(false);
const filesError = ref('');
const yandexItems = ref([]);
const publicFolderUrl = ref('');
// Photographer public URL shown in Information tab for PhotoEditor subtasks
const photographerPublicUrl = ref('');

// Lightbox state and helpers (with temp cleanup like in All.vue)
const lightboxOpen = ref(false);
const lightboxSrc = ref('');
const lightboxTemp = ref(null); // { id, path } for cleanup
function openLightbox(src, tempMeta = null) {
    if (!src) return;
    lightboxSrc.value = src;
    lightboxTemp.value = tempMeta;
    lightboxOpen.value = true;
}
function closeLightbox() {
    lightboxOpen.value = false;
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
function isImageName(name) { return /(\.(jpe?g|png|gif|webp|bmp|svg|heic|heif))$/i.test(name || ''); }
async function viewYandexItemInLightbox(item) {
    if (!item || item.type !== 'file') return;
    if (!isImageName(item.name)) {
        // Fallback for non-images: open direct URL in new tab
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

const fileInputRef = ref(null);
const uploading = ref(false);
const uploadError = ref('');

const folderPath = computed(() => publicFolderUrl.value || '');

function sanitizeName(name) {
    if (!name) return '';
    let s = String(name);
    s = s.replace(/[\\\n\r\t]/g, ' ');
    s = s.replace(/\//g, '-');
    return s.trim();
}

function yandexFolderPath() {
    const brandName = sanitizeName(oc.value.brandName);
    const typeName = sanitizeName(oc.value.typeName);
    const leafBase = sanitizeName(oc.value.articleName || oc.value.taskName);
    if (!brandName || !leafBase) return null;
    const prefix = (oc.value.typePrefix || '').toLowerCase();
    const leaf = `${prefix ? prefix + '_' : ''}${leafBase}`;
    return typeName ? `disk:/${brandName}/${typeName}/${leaf}` : `disk:/${brandName}/${leaf}`;
}

function photographerFolderPath() {
    // Photographer folder assumed to share the same brand/type, but with 'ф_' prefix
    const brandName = sanitizeName(oc.value.brandName);
    const typeName = sanitizeName(oc.value.typeName);
    const leafBase = sanitizeName(oc.value.articleName || oc.value.taskName);
    if (!brandName || !leafBase) return null;
    const prefix = 'ф';
    const leaf = `${prefix}_${leafBase}`;
    return typeName ? `disk:/${brandName}/${typeName}/${leaf}` : `disk:/${brandName}/${leaf}`;
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
    } finally { filesLoading.value = false; }
}

async function loadPhotographerPublicUrl() {
    // Only relevant when current task is owned by PhotoEditor
    if (!currentTask.value || currentTask.value.ownership !== 'PhotoEditor') { photographerPublicUrl.value = ''; return; }

    // For PhotoEditor tasks, the photographer's public URL should be available from the task data
    // or retrieved from the backend. For now, we'll leave it empty to avoid duplicate folder creation.
    photographerPublicUrl.value = '';

    // TODO: Implement proper retrieval of photographer's public URL from backend
    // This should be stored in the task or subtask data during creation
}

async function copyText(val) {
    try { await navigator.clipboard.writeText(val || ''); } catch (e) { /* ignore */ }
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
            if (!res.ok) throw new Error('Upload failed');
        }
        await loadYandexFiles();
    } catch (e) { console.error(e); uploadError.value = 'Ошибка загрузки файлов.'; }
    finally { uploading.value = false; }
}

async function deleteYandexItem(item) {
    if (!item || item.type !== 'file') return;
    // Do not allow deleting files if task is already accepted
    if (currentTask.value?.status === 'accepted') return;
    // Build path if not provided
    let reqPath = item.path;
    if (!reqPath) {
        const folder = yandexFolderPath();
        if (!folder) return;
        reqPath = `${folder}/${item.name}`;
    }
    try {
        const res = await fetch(route('integrations.yandex.delete'), {
            method: 'DELETE',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') },
            body: JSON.stringify({ path: reqPath, permanently: true }),
        });
        if (!res.ok) throw new Error('Delete failed');
        await loadYandexFiles();
    } catch (e) { console.error(e); }
}

// Format manager name to ФАМИЛИЯ И.О.
function formatManagerName(manager) {
    if (!manager?.name) return '—';

    const parts = manager.name.trim().split(' ').filter(p => p.trim() !== '');
    if (parts.length === 0) return '—';

    // Last name is the first part
    const lastName = parts[0];

    // Get first letters of other parts (middle names) with dots
    const initials = parts.slice(1)
        .map(part => part.charAt(0) + '.')
        .join('');

    return `${lastName} ${initials}`.toUpperCase();
}

// Removed: send-for-review flow and related helpers
</script>

<template>
    <DashByteLayout>

        <div id="fileSidebar" class="file-sidebar">
            <label class="sidebar-label mb-2">Статусы</label>
            <nav class="nav nav-sidebar mb-4">
                <a href="#" class="nav-link" :class="{ 'active': statusFilter === '' }"
                    @click.prevent="statusFilter = ''">
                    Все статусы
                </a>
                <a v-for="status in statusOptions" :key="status.value" href="#" class="nav-link"
                    :class="{ 'active': statusFilter === status.value }" @click.prevent="statusFilter = status.value">
                    {{ status.label }}
                </a>
            </nav>

            <label class="sidebar-label mb-2">Приоритеты</label>
            <nav class="nav nav-sidebar mb-4">
                <a href="#" class="nav-link" :class="{ 'active': priorityFilter === '' }"
                    @click.prevent="priorityFilter = ''">
                    Все приоритеты
                </a>
                <a v-for="priority in priorityOptions" :key="priority.value" href="#" class="nav-link"
                    :class="{ 'active': priorityFilter === priority.value }"
                    @click.prevent="priorityFilter = priority.value">
                    {{ priority.label }}
                </a>
            </nav>


        </div><!-- file-sidebar -->
        <div id="fileContent" class="file-content p-3 p-lg-4">

            <div class="d-md-flex align-items-center justify-content-between mb-4">
                <div>
                    <ol class="breadcrumb fs-sm mb-1">
                        <li class="breadcrumb-item">Список задач, назначенных вам</li>
                    </ol>
                    <h4 class="main-title mb-0">Назначенные мне задачи</h4>
                </div>
                <div class="d-flex gap-2 mt-3 mt-md-0 flex-wrap">
                    <div class="input-group input-group-flat w-auto me-2">
                        <span class="input-group-text">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="icon icon-1">
                                <path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0" />
                                <path d="M21 21l-6 -6" />
                            </svg>
                        </span>
                        <input v-model="search" type="text" class="form-control form-control-sm"
                            placeholder="Поиск..." />
                    </div>
                    <select v-model="statusFilter" class="form-select form-select-sm w-auto me-2" style="display:none;">
                        <option value="all">Все статусы</option>
                        <option value="assigned">Назначено</option>
                        <option value="on_review">На проверке</option>
                        <option value="rework">На доработку</option>
                        <option value="accepted">Принято</option>
                    </select>

                    <select v-model="brandFilter" class="form-select form-select-sm w-auto me-2">
                        <option value="">Все бренды</option>
                        <option v-for="b in availableBrands" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                    <div class="input-group input-group-flat w-auto me-2">
                        <span class="input-group-text">Артикул</span>
                        <input v-model="articleFilter" type="text" class="form-control form-control-sm"
                            placeholder="Поиск по артикулу" />
                    </div>

                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <select v-model="createdFilter" class="form-select form-select-sm w-auto">
                            <option value="">Любая дата</option>
                            <option value="today">Сегодня</option>
                            <option value="yesterday">Вчера</option>
                            <option value="date">Дата…</option>
                        </select>
                        <input v-if="createdFilter === 'date'" v-model="createdDate" type="date"
                            class="form-control form-control-sm w-auto" />
                        <button class="btn btn-sm btn-outline-secondary" @click="resetFilters"
                            :disabled="loading">Сбросить
                            фильтры</button>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>Создан</th>
                            <th>Наименование задачи</th>
                            <th>Бренд, Артикул</th>
                            <th>Проверяющий</th>
                            <th>Тип</th>
                            <th>Исходник</th>
                            <th>Результат</th>
                            <th>Статус</th>
                            <th>Приоритет</th>
                            <th class="w-1">ДЕЙСТВИЯ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="displayed.length === 0">
                            <td colspan="10" class="text-center text-secondary py-4">Назначенных задач нет</td>
                        </tr>
                        <tr v-for="t in displayed" :key="t.id">
                            <td>{{ new Date(t.created_at).toLocaleString('ru-RU') }}</td>
                            <td>{{ t.name || t.article?.name || t.article_name || '' }}</td>
                            <td>{{ t.brand?.name || '—' }}<br />{{ t.article?.name || t.article_name || t.article || '—'
                                }}
                            </td>
                            <td>{{ formatManagerName(t.creator) || '—' }}</td>
                            <td>{{ t.type?.name || t.task_type?.name || t.type_name || '—' }}</td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-sm btn-outline-secondary"
                                        @click="openSourceCommentsOffcanvas(t)">КОММЕНТАРИЙ</button>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-sm btn-outline-secondary"
                                        @click="openOffcanvas(t)">КОММЕНТАРИЙ</button>
                                    <button class="btn btn-sm btn-outline-primary"
                                        @click="() => { openOffcanvas(t); activeOcTab = 'files'; }">ФАЙЛЫ</button>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <select class="form-select form-select-sm w-auto" :value="t.status"
                                        @change="(e) => updateTaskStatus(t, e.target.value, e)"
                                        :disabled="['accepted', 'cancelled'].includes(t.status)">
                                        <option v-for="status in statusOptions" :key="status.value"
                                            :value="status.value" :selected="status.value === t.status">
                                            {{ status.label }}
                                        </option>
                                    </select>
                                </div>
                            </td>
                            <td>
                                <span class="badge text-light" :class="priorityClass(t.priority)">{{
                                    priorityLabel(t.priority)
                                    }}</span>
                            </td>
                            <td class="text-nowrap">
                                <div class="btn-list d-flex flex-nowrap align-items-center gap-2">
                                    <button class="btn btn-icon btn-ghost-secondary" @click="copyTaskPublicLink(t)"
                                        title="Копировать ссылку">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round"
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
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round"
                                            class="icon icon-tabler icons-tabler-outline icon-tabler-link">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M9 15l6 -6" />
                                            <path d="M11 6l.463 -.536a5 5 0 0 1 7.071 7.072l-.534 .464" />
                                            <path
                                                d="M13 18l-.397 .534a5.068 5.068 0 0 1 -7.127 0a4.972 4.972 0 0 1 0 -7.071l.524 -.463" />
                                        </svg>
                                    </button>

                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div><!-- file-content -->

        <!-- Right offcanvas -->
        <teleport to="body">
            <div class="offcanvas offcanvas-end w-50" ref="offcanvasEl" tabindex="-1" role="dialog"
                :class="{ show: offcanvasOpen && !hasOffcanvas }"
                :style="offcanvasOpen && !hasOffcanvas ? 'visibility: visible; z-index: 1045;' : ''">
                <div class="offcanvas-header">
                    <h5 class="offcanvas-title">
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
                                    @click="activeOcTab = 'comments'">Информация</button>
                            </li>
                            <li class="nav-item">
                                <button type="button" class="nav-link" :class="{ active: activeOcTab === 'files' }"
                                    @click="activeOcTab = 'files'">Файлы</button>
                            </li>
                        </ul>
                    </div>

                    <div v-if="activeOcTab === 'files'">
                        <div class="mb-3">
                            <label class="form-label">URL папки (для просмотра в браузере)</label>
                            <div class="input-group">
                                <input type="text" class="form-control" :value="folderPath" readonly />
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
                                                :class="it.type === 'dir' ? 'bg-secondary' : 'bg-primary'">{{
                                                    it.type === 'dir' ? 'Папка' : 'Файл' }}</span>
                                            <span>{{ it.name }}</span>
                                            <span v-if="it.size && it.type === 'file'" class="text-secondary small">{{
                                                (it.size
                                                    / 1024 / 1024).toFixed(2) }} MB</span>
                                        </div>
                                        <div>
                                            <button v-if="it.type === 'file'"
                                                class="btn btn-sm btn-outline-primary me-2"
                                                @click="() => viewYandexItemInLightbox(it)">ПОСМОТРЕТЬ</button>
                                            <button v-if="it.type === 'file'" class="btn btn-sm btn-outline-danger"
                                                :disabled="currentTask?.status === 'accepted'"
                                                @click="() => deleteYandexItem(it)">Удалить</button>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div class="mt-3 d-flex align-items-center gap-2">
                            <input type="file" accept="image/*" multiple ref="fileInputRef" class="d-none"
                                @change="onFilesChosen" />
                            <button class="btn btn-primary" :disabled="uploading || currentTask?.status === 'accepted'"
                                @click="openUploader">
                                <span v-if="!uploading">Загрузить фото</span>
                                <span v-else>Загрузка…</span>
                            </button>
                            <span v-if="uploadError" class="text-danger small">{{ uploadError }}</span>
                        </div>
                    </div>

                    <div v-else>
                        <div class="mb-3" v-if="currentTask">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="text-secondary">Статус:</span>
                                <span class="badge text-light"
                                    :class="statusClass(currentTask.status, !!currentTask.assignee_id)">{{
                                        statusLabel(currentTask.status, !!currentTask.assignee_id) }}</span>
                            </div>
                        </div>

                        <!-- Photographer public folder URL (only for PhotoEditor tasks) -->
                        <div class="mb-3" v-if="currentTask?.ownership === 'PhotoEditor'">
                            <label class="form-label">Папка фотографа (публичная ссылка)</label>
                            <div class="input-group">
                                <input type="text" class="form-control" :value="photographerPublicUrl" readonly />
                                <button class="btn btn-outline-primary" type="button" :disabled="!photographerPublicUrl"
                                    @click="() => window.open(photographerPublicUrl, '_blank')">Перейти</button>
                                <button class="btn btn-outline-secondary" type="button"
                                    :disabled="!photographerPublicUrl"
                                    @click="() => copyText(photographerPublicUrl)">Скопировать</button>
                            </div>
                        </div>

                        <div v-if="commentsLoading" class="text-secondary">Загрузка комментариев…</div>
                        <div v-else>
                            <div v-if="comments.length === 0" class="text-secondary mb-2">Комментариев пока нет.</div>
                            <ul class="list-unstyled">
                                <li v-for="c in comments" :key="c.id" class="mb-2">
                                    <div>
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
            <div v-if="offcanvasOpen && !hasOffcanvas" class="modal-backdrop fade show" style="z-index: 1040;"
                @click="closeOffcanvas"></div>
        </teleport>

        <!-- Source Offcanvas (БРЕНД / Исходник) -->
        <teleport to="body">
            <div class="offcanvas offcanvas-end w-50" ref="sourceOffcanvasEl" tabindex="-1" role="dialog"
                :class="{ show: sourceOffcanvasOpen && !hasSourceOffcanvas }"
                :style="sourceOffcanvasOpen && !hasSourceOffcanvas ? 'visibility: visible; z-index: 1045;' : ''">
                <div class="offcanvas-header">
                    <h5 class="offcanvas-title">
                        {{ sourceOc.brandName }} / {{ sourceOc.taskName }} — Исходник
                    </h5>
                    <button type="button" class="btn-close text-reset" aria-label="Close"
                        @click="closeSourceOffcanvas"></button>
                </div>
                <div class="offcanvas-body">
                    <div v-if="sourceCommentsLoading" class="text-secondary">Загрузка комментариев…</div>
                    <div v-else>
                        <div v-if="sourceFiles && sourceFiles.length" class="mb-3">
                            <div class="fw-bold">Файлы задачи:</div>
                            <ul class="list-unstyled small mb-0">
                                <li v-for="(f, idx) in sourceFiles" :key="idx">
                                    <a v-if="isUrl(f)" :href="f" target="_blank" rel="noopener">{{ f }}</a>
                                    <span v-else>{{ f }}</span>
                                </li>
                            </ul>
                            <hr class="my-3" />
                        </div>
                        <div v-if="sourceComments.length === 0" class="text-secondary mb-2">Комментариев пока нет.</div>
                        <ul class="list-unstyled">
                            <li v-for="c in sourceComments" :key="c.id" class="mb-2">
                                <div>
                                    <div class="fw-bold">{{ c.user?.name || '—' }} <span class="text-secondary small">{{
                                        new Date(c.created_at).toLocaleString('ru-RU') }}</span></div>
                                    <div v-if="c.content" style="white-space: pre-wrap;">{{ c.content }}</div>
                                    <div v-if="c.image_path" class="mt-2">
                                        <img :src="'/storage/' + c.image_path" class="img-fluid rounded cursor-pointer"
                                            style="max-width: 300px; max-height: 200px;"
                                            @click="() => openLightbox('/storage/' + c.image_path)" />
                                    </div>
                                </div>
                            </li>
                        </ul>
                        <div class="mt-2">
                            <textarea v-model="newSourceComment" rows="2" class="form-control"
                                placeholder="Новый комментарий…"></textarea>
                            <div class="mt-2 d-flex justify-content-end">
                                <button class="btn btn-primary" :disabled="!newSourceComment.trim() || sourceSubmitting"
                                    @click="addSourceComment">Добавить</button>
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
            <div class="modal modal-blur fade" :class="{ show: lightboxOpen }"
                :style="lightboxOpen ? 'display: block;' : ''" tabindex="-1" role="dialog" @click.self="closeLightbox">
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

/* Status select wrapper */
.status-select-wrapper {
    position: relative;
    display: inline-block;
    min-width: 120px;
}

/* Style the select */
.status-select-wrapper select {
    width: 100%;
    padding: 0.25rem 1.75rem 0.25rem 0.5rem;
    font-size: 0.75rem;
    font-weight: 500;
    line-height: 1.5;
    color: #212529;
    background-color: #fff;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 0.5rem center;
    background-size: 16px 12px;
    border: 1px solid #ced4da;
    border-radius: 0.25rem;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

/* Style the select when closed (not focused) */
.status-select-wrapper:not(.focused) select {
    color: white;
    border-color: transparent;
}

/* Status-specific colors when closed */
.status-select-wrapper.assigned:not(.focused) select {
    background-color: #6c757d;
}

.status-select-wrapper.in_progress:not(.focused) select {
    background-color: #0d6efd;
}

.status-select-wrapper.on_review:not(.focused) select {
    background-color: #fd7e14;
}

.status-select-wrapper.rework:not(.focused) select {
    background-color: #dc3545;
}

.status-select-wrapper.accepted:not(.focused) select {
    background-color: #198754;
}

.status-select-wrapper.question:not(.focused) select {
    background-color: #6f42c1;
}

.status-select-wrapper.cancelled:not(.focused) select {
    background-color: #6c757d;
}

/* Focus state */
.status-select-wrapper select:focus {
    border-color: #86b7fe;
    outline: 0;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

/* Remove default select styling in IE10/11 */
.status-select-wrapper select::-ms-expand {
    display: none;
}

/* Ensure options have default styling */
.status-select-wrapper select option {
    color: #212529;
    background-color: white;
}

/* Fix for Firefox focus outline */
.status-select-wrapper select:-moz-focusring {
    color: transparent;
    text-shadow: 0 0 0 #000;
}
</style>
