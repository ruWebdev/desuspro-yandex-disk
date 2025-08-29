<script setup>
import TablerLayout from '@/Layouts/TablerLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';

const props = defineProps({
    tasks: { type: Array, default: () => [] },
});

// Search / filter
const search = ref('');
// all|assigned|on_review|rework|accepted (synced with All.vue)
const statusFilter = ref('all');

// Additional filters (synced with All.vue)
const brandFilter = ref('all'); // brand id or 'all'
const articleFilter = ref(''); // free text
const createdFilter = ref('all'); // all|today|yesterday|date
const createdDate = ref(''); // yyyy-mm-dd

const availableBrands = computed(() => {
    const map = new Map();
    for (const t of props.tasks) {
        const id = t.brand_id || t.brand?.id;
        const name = t.brand?.name;
        if (id && name && !map.has(id)) map.set(id, name);
    }
    return Array.from(map.entries()).map(([id, name]) => ({ id, name })).sort((a, b) => a.name.localeCompare(b.name));
});

function matchesCreated(t) {
    if (createdFilter.value === 'all') return true;
    const d = new Date(t.created_at);
    const pad = (n) => String(n).padStart(2, '0');
    const ymd = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    const today = new Date();
    const ymdToday = `${today.getFullYear()}-${pad(today.getMonth() + 1)}-${pad(today.getDate())}`;
    const yest = new Date(today); yest.setDate(today.getDate() - 1);
    const ymdYest = `${yest.getFullYear()}-${pad(yest.getMonth() + 1)}-${pad(yest.getDate())}`;
    if (createdFilter.value === 'today') return ymd === ymdToday;
    if (createdFilter.value === 'yesterday') return ymd === ymdYest;
    if (createdFilter.value === 'date') return !!createdDate.value && ymd === createdDate.value;
    return true;
}

function isAssigned(t) {
    return !!t.assignee_id;
}

function matchesFilter(t) {
    if (statusFilter.value === 'all') return true;
    if (statusFilter.value === 'assigned') return isAssigned(t) && !['on_review', 'accepted', 'rework'].includes(t.status);
    return t.status === statusFilter.value;
}

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    return props.tasks.filter((t) => {
        if (!matchesFilter(t)) return false;
        // brand select
        if (brandFilter.value !== 'all') {
            const bid = t.brand_id || t.brand?.id || null;
            if (String(bid) !== String(brandFilter.value)) return false;
        }
        // article free-text
        if (articleFilter.value.trim()) {
            const a = (t.article?.name || t.article_name || t.article || '').toString().toLowerCase();
            if (!a.includes(articleFilter.value.trim().toLowerCase())) return false;
        }
        // created date
        if (!matchesCreated(t)) return false;
        if (!q) return true;
        const taskName = t.name || '';
        const brandName = t.brand?.name || '';
        return (
            taskName.toLowerCase().includes(q) ||
            brandName.toLowerCase().includes(q) ||
            (t.comment || '').toLowerCase().includes(q)
        );
    });
});

const displayed = computed(() => [...filtered.value].sort((a, b) => new Date(b.created_at) - new Date(a.created_at)));

// Status helpers (RU labels, synced with All.vue)
function statusLabel(status, hasAssignee) {
    if (status === 'on_review') return 'На проверке';
    if (status === 'accepted') return 'Принято';
    if (status === 'rework') return 'На доработку';
    if (status === 'created') return 'Создано';
    return hasAssignee ? 'Назначено' : '—';
}
function statusClass(status, hasAssignee) {
    if (status === 'on_review') return 'bg-warning';
    if (status === 'accepted') return 'bg-success';
    if (status === 'rework') return 'bg-danger';
    return hasAssignee ? 'bg-primary' : 'bg-secondary';
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

onMounted(() => {
    const Ctor = window.bootstrap?.Offcanvas;
    if (Ctor && offcanvasEl.value) {
        offcanvasInstance = new Ctor(offcanvasEl.value, { backdrop: true, keyboard: true, scroll: true });
        hasOffcanvas.value = true;
        offcanvasEl.value.addEventListener('show.bs.offcanvas', () => { offcanvasOpen.value = true; });
        offcanvasEl.value.addEventListener('hidden.bs.offcanvas', () => { offcanvasOpen.value = false; });
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

function closeSourceOffcanvas() { if (sourceOffcanvasInstance) sourceOffcanvasInstance.hide(); else sourceOffcanvasOpen.value = false; }

function openSourceCommentsOffcanvas(task) {
    const brandName = task.brand?.name || '';
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

// Status transition: send for review
const sending = ref(false);
const canSendForReview = computed(() => {
    // Only allow from assigned or rework
    const st = currentTask.value?.status;
    return st === 'assigned' || st === 'rework';
});
const sendLabel = computed(() => currentTask.value?.status === 'rework' ? 'Отправить на повторную проверку' : 'Отправить на проверку');

async function sendForReview() {
    if (!oc.value.taskId) return;
    sending.value = true;
    try {
        router.put(
            route('performer.tasks.update_status', { task: oc.value.taskId }),
            { status: 'on_review' },
            {
                preserveScroll: true,
                onSuccess: () => {
                    if (currentTask.value) currentTask.value.status = 'on_review';
                    // Also reflect in list
                    const idx = props.tasks.findIndex(x => x.id === oc.value.taskId);
                    if (idx >= 0) props.tasks[idx].status = 'on_review';
                },
            }
        );
    } finally { sending.value = false; }
}

function canSendForReviewRow(t) {
    // Only active for statuses: assigned (назначено) or rework (на доработку)
    return !!t && (t.status === 'assigned' || t.status === 'rework');
}
async function sendRowForReview(t) {
    if (!t || !t.id || !canSendForReviewRow(t)) return;
    sending.value = true;
    try {
        await router.put(
            route('performer.tasks.update_status', { task: t.id }),
            { status: 'on_review' },
            {
                preserveScroll: true,
                onSuccess: () => {
                    t.status = 'on_review';
                },
            }
        );
    } finally { sending.value = false; }
}
</script>

<template>
    <TablerLayout>

        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">Назначенные мне задания</div>
                    <div class="card-subtitle">Список заданий, назначенных вам.</div>
                </div>
                <div class="card-actions d-flex flex-wrap align-items-center gap-2">
                    <div class="input-group input-group-flat w-auto me-2">
                        <span class="input-group-text">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="icon icon-1">
                                <path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0" />
                                <path d="M21 21l-6 -6" />
                            </svg>
                        </span>
                        <input v-model="search" type="text" class="form-control" placeholder="Поиск..." />
                    </div>
                    <select v-model="statusFilter" class="form-select w-auto me-2" style="display:none;">
                        <option value="all">Все статусы</option>
                        <option value="assigned">Назначено</option>
                        <option value="on_review">На проверке</option>
                        <option value="rework">На доработку</option>
                        <option value="accepted">Принято</option>
                    </select>

                    <select v-model="brandFilter" class="form-select w-auto me-2">
                        <option value="all">Все бренды</option>
                        <option v-for="b in availableBrands" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>

                    <div class="input-group input-group-flat w-auto me-2">
                        <span class="input-group-text">Артикул</span>
                        <input v-model="articleFilter" type="text" class="form-control"
                            placeholder="Поиск по артикулу" />
                    </div>

                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <select v-model="createdFilter" class="form-select w-auto">
                            <option value="all">Любая дата</option>
                            <option value="today">Сегодня</option>
                            <option value="yesterday">Вчера</option>
                            <option value="date">Дата…</option>
                        </select>
                        <input v-if="createdFilter === 'date'" v-model="createdDate" type="date"
                            class="form-control w-auto" />
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Создан</th>
                            <th>Название</th>
                            <th>Артикул</th>
                            <th>Бренд</th>
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
                            <td colspan="10" class="text-center text-secondary py-4">Назначенных заданий нет</td>
                        </tr>
                        <tr v-for="t in displayed" :key="t.id">
                            <td>{{ new Date(t.created_at).toLocaleString('ru-RU') }}</td>
                            <td>{{ t.name || t.article?.name || t.article_name || '' }}</td>
                            <td>{{ t.article?.name || t.article_name || t.article || '—' }}</td>
                            <td>{{ t.brand?.name || '—' }}</td>
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
                                <span class="badge text-light" :class="statusClass(t.status, !!t.assignee_id)">{{
                                    statusLabel(t.status, !!t.assignee_id) }}</span>
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
                                    <button class="btn btn-warning btn-sm"
                                        :disabled="!canSendForReviewRow(t) || sending" @click="sendRowForReview(t)">НА
                                        ПРОВЕРКУ</button>
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
                                <button class="btn btn-warning btn-sm" :disabled="!canSendForReview || sending"
                                    @click="sendForReview">{{ sendLabel }}</button>
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
                    <div v-if="commentsLoading" class="text-secondary">Загрузка комментариев…</div>
                    <div v-else>
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
    </TablerLayout>
</template>

<style scoped>
.cursor-pointer {
    cursor: pointer;
}
</style>
