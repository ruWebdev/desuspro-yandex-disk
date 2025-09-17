<script setup>
import { ref, onMounted, watch, nextTick, computed } from 'vue';
import { useToast } from 'vue-toastification';
import { Offcanvas } from 'bootstrap';

const props = defineProps({
    show: { type: Boolean, default: false },
    task: { type: Object, default: null },
    brands: { type: Array, required: true },
    activeTab: { type: String, default: 'comments' },
    currentUser: { type: Object, default: null }
});

const toast = useToast();

// Role helpers
const isPerformer = computed(() => {
    return props.currentUser && (
        props.currentUser.roles?.some(r => r.name === 'Performer' || r.name === 'performer') ||
        props.currentUser.is_performer
    );
});

const isManager = computed(() => {
    return props.currentUser?.roles?.some(r =>
        r.name.toLowerCase() === 'manager' ||
        r.name.toLowerCase() === 'менеджер' ||
        props.currentUser.is_manager
    );
});

// React to parent-controlled visibility
watch(() => props.show, async (val) => {
    await nextTick();
    if (val) {
        // Set public folder URL from task
        publicFolderUrl.value = props.task?.public_link || '';

        // Capture stable IDs for comment operations
        if (props.task) {
            commentCtx.value = { brandId: props.task.brand_id, taskId: props.task.id };
        } else {
            commentCtx.value = { brandId: null, taskId: null };
        }

        if (offcanvasInstance) {
            offcanvasInstance.show();
        }
        // Lazy load content depending on active tab
        if (props.activeTab === 'files') {
            loadYandexFiles();
        } else {
            loadComments();
        }
    } else {
        if (offcanvasInstance) {
            try { offcanvasInstance.hide(); } catch (e) { /* noop */ }
        }
    }
});

const emit = defineEmits(['close', 'update-tab', 'open-lightbox']);

// Watch for tab changes to ensure publicFolderUrl is set
watch(() => props.activeTab, (newTab) => {
    if (newTab === 'files' && props.task?.public_link && !publicFolderUrl.value) {
        publicFolderUrl.value = props.task.public_link;
    }
});

const offcanvasEl = ref(null);
let offcanvasInstance = null;
const hasOffcanvas = ref(false);

// Comments state
const commentsLoading = ref(false);
const comments = ref([]);
const newComment = ref('');
const submitting = ref(false);
const commentImageInput = ref(null);
const selectedCommentImages = ref([]);
// Stable identifiers to avoid race conditions
const commentCtx = ref({ brandId: null, taskId: null });

// Files state
const filesLoading = ref(false);
const filesError = ref('');
const yandexItems = ref([]);
const publicFolderUrl = ref('');
const fileInputRef = ref(null);
const uploading = ref(false);
const uploadError = ref('');

onMounted(async () => {
    await nextTick();
    if (offcanvasEl.value && !offcanvasInstance) {
        offcanvasInstance = new Offcanvas(offcanvasEl.value, { backdrop: true, keyboard: true, scroll: true });
        hasOffcanvas.value = true;
        offcanvasEl.value.addEventListener('show.bs.offcanvas', () => emit('show'));
        offcanvasEl.value.addEventListener('hidden.bs.offcanvas', () => emit('close'));
    }
});

watch(offcanvasEl, (el) => {
    if (el && !offcanvasInstance) {
        offcanvasInstance = new Offcanvas(el, { backdrop: true, keyboard: true, scroll: true });
        hasOffcanvas.value = true;
        el.addEventListener('show.bs.offcanvas', () => emit('show'));
        el.addEventListener('hidden.bs.offcanvas', () => emit('close'));
    }
});

function closeOffcanvas() {
    if (offcanvasInstance) offcanvasInstance.hide();
    else emit('close');
}

function openCommentsOffcanvas(task) {
    const brandName = task.brand?.name || (props.brands.find(b => b.id === task.brand_id)?.name) || '';
    // Set task data
    comments.value = [];
    newComment.value = '';
    publicFolderUrl.value = task.public_link || '';
    emit('open-comments', task);
    if (offcanvasInstance) offcanvasInstance.show();
}

function openFilesOffcanvas(task) {
    const brandName = task.brand?.name || (props.brands.find(b => b.id === task.brand_id)?.name) || '';
    // Set task data
    publicFolderUrl.value = task.public_link || '';
    emit('open-files', task);
    if (offcanvasInstance) offcanvasInstance.show();
}

async function loadComments() {
    if (!commentCtx.value?.taskId || !commentCtx.value?.brandId) { comments.value = []; return; }
    const url = route('brands.tasks.comments.index', { brand: commentCtx.value.brandId, task: commentCtx.value.taskId });
    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
    comments.value = await res.json();
}

async function addComment() {
    if (!commentCtx.value?.taskId || (!newComment.value.trim() && (!selectedCommentImages.value || selectedCommentImages.value.length === 0))) return;
    submitting.value = true;
    try {
        const baseUrl = route('brands.tasks.comments.store', { brand: commentCtx.value.brandId, task: commentCtx.value.taskId });
        const headersBase = { 'Accept': 'application/json', 'X-XSRF-TOKEN': getXsrfToken() };

        const files = Array.isArray(selectedCommentImages.value) ? selectedCommentImages.value : [];
        if (files.length > 0) {
            for (let i = 0; i < files.length; i++) {
                const formData = new FormData();
                formData.append('content', i === 0 ? (newComment.value.trim() || '') : '');
                formData.append('image', files[i]);
                const res = await fetch(baseUrl, {
                    method: 'POST', headers: headersBase, body: formData, credentials: 'same-origin'
                });
                if (!res.ok) {
                    const errorData = await res.json().catch(() => ({}));
                    if (errorData?.errors?.content?.[0]) { toast.error(errorData.errors.content[0]); return; }
                    throw new Error(`HTTP ${res.status}`);
                }
                const data = await res.json();
                if (data?.comment) comments.value.push(data.comment);
            }
            clearCommentForm();
        } else {
            const headers = { ...headersBase, 'Content-Type': 'application/json' };
            const body = JSON.stringify({ content: newComment.value.trim() });
            const res = await fetch(baseUrl, { method: 'POST', headers, body });
            if (!res.ok) {
                const errorData = await res.json().catch(() => ({}));
                if (errorData?.errors?.content?.[0]) { toast.error(errorData.errors.content[0]); return; }
                throw new Error(`HTTP ${res.status}`);
            }
            const data = await res.json();
            if (data?.comment) comments.value.push(data.comment);
            clearCommentForm();
        }
    } catch (e) {
        console.error(e);
        toast.error('Ошибка при добавлении комментария. Попробуйте ещё раз.');
    } finally { submitting.value = false; }
}

function onCommentImagesSelected(event) {
    const files = event.target.files;
    selectedCommentImages.value = files ? Array.from(files) : [];
}

function clearCommentForm() {
    newComment.value = '';
    selectedCommentImages.value = [];
    if (commentImageInput.value) {
        commentImageInput.value.value = null;
    }
}

function canDeleteComment(comment) {
    if (!props.currentUser) return false;

    const isAdmin = props.currentUser.roles?.some(r => r.name === 'Administrator' || r.name === 'admin') || props.currentUser.is_admin;
    // Administrators can delete any comment at any time
    if (isAdmin) return true;

    // Only the author can delete their own comment
    const isOwnComment = comment.user_id === props.currentUser.id;
    if (!isOwnComment) return false;

    // Non-admins: allow deletion only within 1 minute of creation
    const createdAt = new Date(comment.created_at).getTime();
    if (!createdAt || Number.isNaN(createdAt)) return false;
    const elapsed = Date.now() - createdAt;
    const windowMs = 60 * 1000; // 1 minute
    return elapsed <= windowMs;
}

async function deleteComment(comment) {
    if (!commentCtx.value?.taskId || !canDeleteComment(comment)) return;

    if (!confirm('Вы уверены, что хотите удалить этот комментарий?')) {
        return;
    }

    const url = route('brands.tasks.comments.destroy', {
        brand: commentCtx.value.brandId,
        task: commentCtx.value.taskId,
        comment: comment.id
    });

    try {
        const response = await fetch(url, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getXsrfToken()
            },
            credentials: 'same-origin'
        });

        if (!response.ok) {
            throw new Error('Ошибка при удалении комментария');
        }

        comments.value = comments.value.filter(x => x.id !== comment.id);
    } catch (e) {
        console.error(e);
        toast.error('Не удалось удалить комментарий');
    }
}

function getXsrfToken() {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function copyFolderPath() {
    const text = publicFolderUrl.value;
    if (!text) {
        toast.error('URL папки не найден');
        return;
    }
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
        toast.success('URL скопирован в буфер обмена');
    } catch (e) {
        console.error('Copy failed', e);
        toast.error('Не удалось скопировать URL');
    }
}

function openFolderUrl() {
    const url = publicFolderUrl.value;
    if (!url) {
        toast.error('URL папки не найден');
        return;
    }
    window.open(url, '_blank');
}

function sanitizeName(name) {
    if (!name) return '';
    name = name.replace(/[\\\n\r\t]/g, ' ');
    name = name.replace(/\//g, '-');
    return name.trim();
}

function yandexFolderPath() {
    if (!props.task) return null;
    const brandName = sanitizeName(props.task.brand?.name || (props.brands.find(b => b.id === props.task.brand_id)?.name) || '');
    const typeName = sanitizeName(props.task.type?.name || '');
    const leafBase = sanitizeName(props.task.article?.name || props.task.name || '');
    if (!brandName || !leafBase) return null;
    const prefix = (props.task.type?.prefix || '').toLowerCase();
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

        // Set the public URL from the API response
        if (data && data.public_url) {
            publicFolderUrl.value = data.public_url;
        }

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

// Delete a single file from Yandex.Disk
async function deleteYandexItem(item) {
    if (!item || item.type !== 'file') return;
    try {
        // Confirm deletion
        if (!confirm(`Удалить файл «${item.name}» из Яндекс.Диска?`)) return;

        // Determine path
        let reqPath = item.path;
        if (!reqPath) {
            const folder = yandexFolderPath();
            if (!folder) return;
            reqPath = `${folder}/${item.name}`;
        }

        const res = await fetch(route('integrations.yandex.delete'), {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ path: reqPath, permanently: false })
        });

        if (!res.ok) {
            const txt = await res.text().catch(() => '');
            throw new Error(`Ошибка удаления (${res.status}): ${txt}`);
        }

        toast.success('Файл удалён');
        await loadYandexFiles();
    } catch (e) {
        console.error('deleteYandexItem failed', e);
        toast.error('Не удалось удалить файл. Попробуйте ещё раз.');
    }
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
                headers: { 'X-XSRF-TOKEN': getXsrfToken() },
                credentials: 'same-origin',
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

function isImageName(name) {
    return /\.(jpe?g|png|gif|webp|bmp|svg|heic|heif)$/i.test(name || '');
}

// Prefer best preview URL from item.sizes, then preview/file
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

async function viewYandexItemInLightbox(item) {
    if (!item || item.type !== 'file') return;
    // Try resolver-based approach first
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        const res = await fetch(route('integrations.yandex.resolve_from_item'), {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ item })
        });
        if (res.ok) {
            const data = await res.json();
            const href = data?.href;
            if (href) {
                // Pipe via temp public download for safe inline viewing and cleanup
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
                    if (temp?.url) { emit('open-lightbox', temp.url, { id: temp.id, path: temp.path }); return; }
                }
                emit('open-lightbox', href);
                return;
            }
        }
    } catch (e) {
        console.warn('resolve_from_item failed, trying direct URL', e);
    }

    // Fallback: try direct sizes URL
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
                if (temp?.url) { emit('open-lightbox', temp.url, { id: temp.id, path: temp.path }); return; }
            }
        } catch { }
        emit('open-lightbox', direct);
        return;
    }

    // Final fallback: backend public download url flow for non-image or unknown types
    return downloadYandexItem(item);
}
</script>

<template>
    <teleport to="body">
        <div class="offcanvas offcanvas-end w-50" id="task-offcanvas" ref="offcanvasEl" tabindex="-1" role="dialog"
            aria-hidden="true" :aria-labelledby="'task-offcanvas-title'" :class="{ show: show && !hasOffcanvas }"
            :style="show && !hasOffcanvas ? 'visibility: visible; z-index: 1045;' : ''">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title" :id="'task-offcanvas-title'">
                    {{task?.brand?.name || (brands?.find(b => b.id === task?.brand_id)?.name) || ''}} / {{ task?.name
                        || task?.article?.name || '' }}
                </h5>
                <button type="button" class="btn-close text-reset" aria-label="Close" @click="closeOffcanvas"></button>
            </div>
            <div class="offcanvas-body">
                <div class="mb-3">
                    <ul class="nav nav-pills">
                        <li class="nav-item">
                            <button type="button" class="nav-link" :class="{ active: activeTab === 'comments' }"
                                @click="emit('update-tab', 'comments')">
                                Комментарии
                            </button>
                        </li>
                        <li class="nav-item">
                            <button type="button" class="nav-link" :class="{ active: activeTab === 'files' }"
                                @click="emit('update-tab', 'files')">
                                Файлы
                            </button>
                        </li>
                    </ul>
                </div>

                <div v-if="activeTab === 'files'">
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
                                            :class="it.type === 'dir' ? 'bg-secondary' : 'bg-primary'">{{ it.type ===
                                                'dir' ? 'Папка' : 'Файл' }}</span>
                                        <span>{{ it.name }}</span>
                                        <span v-if="it.size && it.type === 'file'" class="text-secondary small">{{
                                            (it.size / 1024 / 1024).toFixed(2) }} MB</span>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button v-if="it.type === 'file'" class="btn btn-sm btn-outline-primary"
                                            @click="() => viewYandexItemInLightbox(it)">ПОСМОТРЕТЬ</button>
                                        <button v-if="it.type === 'file' && isPerformer"
                                            class="btn btn-sm btn-outline-danger"
                                            :disabled="props.task?.status === 'accepted'"
                                            @click="() => deleteYandexItem(it)">УДАЛИТЬ</button>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div v-if="!isManager" class="mt-3 d-flex align-items-center gap-2">
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
                                    <div class="fw-bold">{{ c.user?.name || '—' }} <span class="text-secondary small">{{
                                        new Date(c.created_at).toLocaleString('ru-RU') }}</span></div>
                                    <div v-if="c.content" style="white-space: pre-wrap;">{{ c.content }}</div>
                                    <div v-if="c.image_path" class="mt-2">
                                        <img :src="'/storage/' + c.image_path" class="img-fluid rounded cursor-pointer"
                                            style="max-width: 300px; max-height: 200px;"
                                            @click="() => emit('open-lightbox', '/storage/' + c.image_path)" />
                                    </div>
                                </div>
                                <button v-if="canDeleteComment(c)" class="btn btn-ghost-danger btn-sm ms-2" title="Удалить"
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
                                    <input type="file" ref="commentImageInput" accept="image/*" multiple
                                        class="form-control" @change="onCommentImagesSelected" />
                                    <small class="text-secondary">Максимальный размер: 5MB на файл</small>
                                </div>
                                <div class="d-flex justify-content-end">
                                    <button type="button" class="btn btn-secondary me-2"
                                        @click="clearCommentForm">Очистить</button>
                                    <button type="submit" class="btn btn-primary"
                                        :disabled="!newComment.trim() && (!selectedCommentImages || selectedCommentImages.length === 0) || submitting">Добавить</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div v-if="show && !hasOffcanvas" class="modal-backdrop fade show" style="z-index: 1040;"
            @click="closeOffcanvas"></div>
    </teleport>
</template>