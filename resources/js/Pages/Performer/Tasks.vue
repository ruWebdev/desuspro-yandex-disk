<script setup>
import TablerLayout from '@/Layouts/TablerLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';

const props = defineProps({
    tasks: { type: Array, default: () => [] },
});

// Search / filter
const search = ref('');
// all|assigned|on_review|accepted|rejected
const statusFilter = ref('all');

function isAssigned(t) {
    return !!t.assignee_id;
}

function matchesFilter(t) {
    if (statusFilter.value === 'all') return true;
    if (statusFilter.value === 'assigned') return isAssigned(t) && !['on_review', 'accepted', 'rejected'].includes(t.status);
    return t.status === statusFilter.value;
}

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    return props.tasks.filter((t) => {
        if (!matchesFilter(t)) return false;
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

// Status helpers (RU labels)
function statusLabel(status, hasAssignee) {
    if (status === 'on_review') return 'На проверке';
    if (status === 'accepted') return 'Принято';
    if (status === 'rejected') return 'Не принято';
    return hasAssignee ? 'Назначено' : '—';
}
function statusClass(status, hasAssignee) {
    if (status === 'on_review') return 'bg-warning';
    if (status === 'accepted') return 'bg-success';
    if (status === 'rejected') return 'bg-danger';
    return hasAssignee ? 'bg-primary' : 'bg-secondary';
}

// Offcanvas state and logic
const offcanvasOpen = ref(false);
const hasOffcanvas = ref(false);
const offcanvasEl = ref(null);
let offcanvasInstance = null;

const oc = ref({ brandId: null, brandName: '', taskId: null, taskName: '' });
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
        taskName: t.name || '',
    };
    currentTask.value = { ...t };
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

// Yandex Files state
const filesLoading = ref(false);
const filesError = ref('');
const yandexItems = ref([]);
const publicFolderUrl = ref('');
// Photographer public URL shown in Information tab for PhotoEditor subtasks
const photographerPublicUrl = ref('');

const fileInputRef = ref(null);
const uploading = ref(false);
const uploadError = ref('');

const folderPath = computed(() => publicFolderUrl.value || '');

function yandexFolderPath() {
    if (!oc.value.brandName || !currentTask.value) return null;
    const subName = currentTask.value.name || '';
    if (!subName) return null;
    // Choose prefix based on ownership
    const prefix = currentTask.value.ownership === 'PhotoEditor' ? 'д_' : 'ф_';
    return `disk:/${oc.value.brandName}/${prefix}${subName}`;
}

function photographerFolderPath() {
    if (!oc.value.brandName || !currentTask.value) return null;
    const subName = currentTask.value.name || '';
    if (!subName) return null;
    return `disk:/${oc.value.brandName}/ф_${subName}`;
}

async function loadYandexFiles() {
    const path = yandexFolderPath();
    if (!path) return;
    filesLoading.value = true;
    filesError.value = '';
    try {
        try {
            const resCreate = await fetch(route('integrations.yandex.create_folder'), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'), 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ path }),
            });
            if (resCreate.ok) {
                const created = await resCreate.json();
                publicFolderUrl.value = created?.public_url || '';
            }
        } catch (e) { /* ignore */ }

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
    const path = photographerFolderPath();
    if (!path) { photographerPublicUrl.value = ''; return; }
    try {
        const res = await fetch(route('integrations.yandex.create_folder'), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'), 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ path }),
        });
        if (res.ok) {
            const data = await res.json();
            photographerPublicUrl.value = data?.public_url || '';
        }
    } catch (e) { /* ignore */ }
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
const canSendForReview = computed(() => currentTask.value && currentTask.value.status !== 'accepted' && currentTask.value.status !== 'on_review');
const sendLabel = computed(() => currentTask.value?.status === 'rejected' ? 'Отправить на повторную проверку' : 'Отправить на проверку');

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
</script>

<template>
    <TablerLayout>

        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">Назначенные мне задания</div>
                    <div class="card-subtitle">Список заданий, назначенных вам.</div>
                </div>
                <div class="card-actions d-flex flex-wrap">
                    <div class="input-group input-group-flat w-auto me-2">
                        <span class="input-group-text">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-1">
                                <path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0" />
                                <path d="M21 21l-6 -6" />
                            </svg>
                        </span>
                        <input v-model="search" type="text" class="form-control" placeholder="Поиск..." />
                    </div>
                    <select v-model="statusFilter" class="form-select w-auto me-2">
                        <option value="all">Все статусы</option>
                        <option value="assigned">Назначено</option>
                        <option value="on_review">На проверке</option>
                        <option value="rejected">Не принято</option>
                        <option value="accepted">Принято</option>
                    </select>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Задание</th>
                            <th>Бренд</th>
                            <th>Роль</th>
                            <th>Статус</th>
                            <th>Создано</th>
                            <th class="w-1">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="displayed.length === 0">
                            <td colspan="6" class="text-center text-secondary py-4">Назначенных заданий нет</td>
                        </tr>
                        <tr v-for="t in displayed" :key="t.id">
                            <td>{{ t.name || '—' }}</td>
                            <td>{{ t.brand?.name || '—' }}</td>
                            <td>
                                <span class="badge bg-secondary text-light">{{ t.ownership === 'PhotoEditor' ?
                                    'Фоторедактор' :
                                    'Фотограф' }}</span>
                            </td>
                            <td>
                                <span class="badge text-light" :class="statusClass(t.status, !!t.assignee_id)">{{
                                    statusLabel(t.status, !!t.assignee_id) }}</span>
                            </td>
                            <td>{{ new Date(t.created_at).toLocaleString('ru-RU') }}</td>
                            <td class="text-nowrap">
                                <button class="btn btn-sm btn-primary" @click="openOffcanvas(t)">Детали</button>
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
                            <button class="btn btn-primary"
                                :disabled="uploading || currentTask?.status === 'accepted'" @click="openUploader">
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
                                <li v-for="c in comments" :key="c.id"
                                    class="mb-2 d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-bold">{{ c.user?.name || '—' }} <span
                                                class="text-secondary small">{{ new
                                                    Date(c.created_at).toLocaleString('ru-RU') }}</span></div>
                                        <div style="white-space: pre-wrap;">{{ c.content }}</div>
                                    </div>
                                    <button class="btn btn-ghost-danger btn-sm" title="Удалить"
                                        @click="deleteComment(c)">Удалить</button>
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
    </TablerLayout>
</template>
