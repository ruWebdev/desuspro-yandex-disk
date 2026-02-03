<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue';
import { useToast } from 'vue-toastification';
import { Offcanvas } from 'bootstrap';

const props = defineProps({
    show: { type: Boolean, default: false },
    task: { type: Object, default: null },
    brands: { type: Array, required: true },
    currentUser: { type: Object, default: null },
    // 'comments' | 'files'
    initialTab: { type: String, default: 'comments' }
});

// React to parent-controlled visibility
watch(() => props.show, async (val) => {
    await nextTick();
    if (val) {
        // Initialize context from task
        if (props.task) {
            const brandName = props.task.brand?.name || (props.brands?.find(b => b.id === props.task.brand_id)?.name) || '';
            sourceOc.value = {
                brandId: props.task.brand_id,
                brandName,
                taskId: props.task.id,
                taskName: props.task.name || props.task.article?.name || '',
            };
        }

        // Set initial tab and load/init data
        sourceActiveTab.value = props.initialTab === 'files' ? 'files' : 'comments';
        if (sourceActiveTab.value === 'files') {
            initSourceFilesFromTask(props.task);
        } else {
            loadSourceComments();
        }

        if (sourceOffcanvasInstance) {
            sourceOffcanvasInstance.show();
        }
    } else {
        if (sourceOffcanvasInstance) {
            try { sourceOffcanvasInstance.hide(); } catch (e) { /* noop */ }
        }
    }
});

const emit = defineEmits(['close', 'show', 'source-files-updated', 'open-lightbox']);

const isPerformer = computed(() => {
    return props.currentUser && (
        props.currentUser.roles?.some(r => r.name === 'Performer' || r.name === 'performer') ||
        props.currentUser.is_performer
    );
});

const toast = useToast();

const sourceOffcanvasEl = ref(null);
let sourceOffcanvasInstance = null;
const hasSourceOffcanvas = ref(false);

// Source comments state
const sourceComments = ref([]);
const newSourceComment = ref('');
const sourceSubmitting = ref(false);
const selectedCommentImages = ref([]);
const commentImageInput = ref(null);

// Active tab: 'comments' | 'files'
const sourceActiveTab = ref('comments');

// Editable list of source files for the task
const sourceFiles = ref(['']);
const sourceOc = ref({ brandId: null, brandName: '', taskId: null, taskName: '' });

onMounted(async () => {
    await nextTick();
    if (sourceOffcanvasEl.value && !sourceOffcanvasInstance) {
        sourceOffcanvasInstance = new Offcanvas(sourceOffcanvasEl.value, { backdrop: true, keyboard: true, scroll: true });
        hasSourceOffcanvas.value = true;
        sourceOffcanvasEl.value.addEventListener('show.bs.offcanvas', () => emit('show'));
        sourceOffcanvasEl.value.addEventListener('hidden.bs.offcanvas', () => emit('close'));
    }
});

watch(sourceOffcanvasEl, (el) => {
    if (el && !sourceOffcanvasInstance) {
        sourceOffcanvasInstance = new Offcanvas(el, { backdrop: true, keyboard: true, scroll: true });
        hasSourceOffcanvas.value = true;
        el.addEventListener('show.bs.offcanvas', () => emit('show'));
        el.addEventListener('hidden.bs.offcanvas', () => emit('close'));
    }
});

function getXsrfToken() {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function closeSourceOffcanvas() {
    if (sourceOffcanvasInstance) sourceOffcanvasInstance.hide();
    else emit('close');
}

function isUrl(text) {
    if (!text) return false;
    try {
        const u = new URL(text);
        return u.protocol === 'http:' || u.protocol === 'https:';
    } catch { return false; }
}

function initSourceFilesFromTask(task) {
    // Ensure source_files is an array, even if it's null/undefined
    const list = Array.isArray(task?.source_files) ? task.source_files : [];
    const cleaned = list.map(v => (v ?? '').toString()).filter(v => v.trim().length > 0);
    sourceFiles.value = cleaned.length ? cleaned : [''];
}

function addSourceFilesField() {
    if (!Array.isArray(sourceFiles.value)) sourceFiles.value = [''];
    sourceFiles.value.push('');
}

function removeSourceFilesField(idx) {
    if (!Array.isArray(sourceFiles.value)) return;
    if (sourceFiles.value.length <= 1) return;
    sourceFiles.value.splice(idx, 1);
}

// Prevent duplicate entries and values equal to task.public_link
watch(() => sourceFiles.value, (arr) => {
    try {
        if (!Array.isArray(arr)) return;
        const normalized = arr.map(v => (v ?? '').toString().trim());
        const seen = new Set();
        const taskPublicLink = (props.task?.public_link ?? '').toString().trim();

        normalized.forEach((val, idx) => {
            if (!val) return; // allow empty

            // Disallow equality to task public_link
            if (taskPublicLink && val === taskPublicLink) {
                sourceFiles.value[idx] = '';
                toast.warning('Значение не может совпадать с публичной ссылкой задачи');
                return;
            }

            // Disallow duplicates across fields
            if (seen.has(val)) {
                sourceFiles.value[idx] = '';
                toast.warning('Дубликаты значений в "Файл(ы) исходника" недопустимы');
            } else {
                seen.add(val);
            }
        });
    } catch (e) { console.error(e); }
}, { deep: true });

async function saveSourceFiles() {
    if (!sourceOc.value.taskId) return;

    const normalized = (Array.isArray(sourceFiles.value) ? sourceFiles.value : [])
        .map(v => (v ?? '').toString().trim());
    const files = normalized.filter(v => v.length > 0);

    const payload = { source_files: files };

    try {
        const response = await fetch(route('brands.tasks.update', {
            brand: sourceOc.value.brandId,
            task: sourceOc.value.taskId
        }), {
            method: 'PUT',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': getXsrfToken()
            },
            body: JSON.stringify(payload),
            credentials: 'same-origin' // важно для работы с куками
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const result = await response.json();

        emit('source-files-updated', {
            taskId: sourceOc.value.taskId,
            sourceFiles: files
        });

        toast.success('Файлы исходника сохранены успешно');
    } catch (e) {
        console.error('Error saving source files:', e);
        toast.error('Не удалось сохранить файлы исходника');
    }
}


function openSourceCommentsOffcanvas(task) {
    const brandName = task.brand?.name || (props.brands?.find(b => b.id === task.brand_id)?.name) || '';
    sourceOc.value = {
        brandId: task.brand_id,
        brandName,
        taskId: task.id,
        taskName: task.name || task.article?.name || '',
    };
    sourceComments.value = [];
    sourceActiveTab.value = 'comments';
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
        // Fallback if Bootstrap Offcanvas is not available
    }

    loadSourceComments();
}

function openSourceFilesOffcanvas(task) {
    const brandName = task.brand?.name || (props.brands?.find(b => b.id === task.brand_id)?.name) || '';
    sourceOc.value = {
        brandId: task.brand_id,
        brandName,
        taskId: task.id,
        taskName: task.name || task.article?.name || '',
    };

    // Initialize source files from task data
    initSourceFilesFromTask(task);

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
        // Fallback if Bootstrap Offcanvas is not available
    }

    sourceActiveTab.value = 'files';
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

async function loadSourceComments() {
    if (!sourceOc.value?.taskId || !sourceOc.value?.brandId) { sourceComments.value = []; return; }
    const url = route('brands.tasks.source_comments.index', { brand: sourceOc.value.brandId, task: sourceOc.value.taskId });
    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
    sourceComments.value = await res.json();
}

async function addSourceComment() {
    if (!sourceOc.value?.taskId || (!newSourceComment.value.trim() && (!selectedCommentImages.value?.length))) return;
    sourceSubmitting.value = true;

    try {
        const baseUrl = route('brands.tasks.source_comments.store', { brand: sourceOc.value.brandId, task: sourceOc.value.taskId });
        const headersBase = { 'Accept': 'application/json', 'X-XSRF-TOKEN': getXsrfToken() };

        const files = Array.isArray(selectedCommentImages.value) ? selectedCommentImages.value : [];

        if (files.length > 0) {
            for (let i = 0; i < files.length; i++) {
                const formData = new FormData();
                formData.append('content', i === 0 ? (newSourceComment.value.trim() || '') : '');
                formData.append('image', files[i]);

                const res = await fetch(baseUrl, {
                    method: 'POST',
                    headers: headersBase,
                    body: formData,
                    credentials: 'same-origin'
                });

                if (!res.ok) {
                    const errorData = await res.json().catch(() => ({}));
                    if (errorData?.errors?.content?.[0]) { toast.error(errorData.errors.content[0]); return; }
                    throw new Error(`HTTP ${res.status}`);
                }
                const data = await res.json();
                if (data?.comment) sourceComments.value.push(data.comment);
            }
            clearSourceCommentForm();
        } else {
            const res = await fetch(baseUrl, {
                method: 'POST',
                headers: {
                    ...headersBase,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ content: newSourceComment.value.trim() }),
                credentials: 'same-origin'
            });

            if (!res.ok) {
                const errorData = await res.json().catch(() => ({}));
                if (errorData?.errors?.content?.[0]) { toast.error(errorData.errors.content[0]); return; }
                throw new Error(`HTTP ${res.status}`);
            }
            const data = await res.json();
            if (data?.comment) sourceComments.value.push(data.comment);
            clearSourceCommentForm();
        }
    } catch (e) {
        console.error(e);
        toast.error('Ошибка при добавлении комментария. Попробуйте ещё раз.');
    } finally { sourceSubmitting.value = false; }
}


function clearSourceCommentForm() {
    newSourceComment.value = '';
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

async function deleteSourceComment(comment) {
    if (!sourceOc.value?.taskId || !canDeleteComment(comment)) return;

    if (!confirm('Вы уверены, что хотите удалить этот комментарий?')) {
        return;
    }

    const url = route('brands.tasks.source_comments.destroy', {
        brand: sourceOc.value.brandId,
        task: sourceOc.value.taskId,
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

        sourceComments.value = sourceComments.value.filter(x => x.id !== comment.id);
    } catch (e) {
        console.error(e);
        toast.error('Не удалось удалить комментарий');
    }
}

function onCommentImagesSelected(event) {
    const files = event.target.files;
    selectedCommentImages.value = files ? Array.from(files) : [];
}

function openCommentFileDialog() {
    if (commentImageInput.value) {
        commentImageInput.value.value = null;
        commentImageInput.value.click();
    }
}

function onCommentPaste(e) {
    try {
        const items = e.clipboardData?.items || [];
        const images = [];
        for (const it of items) {
            if (it.kind === 'file') {
                const file = it.getAsFile();
                if (file && /^image\//i.test(file.type)) images.push(file);
            }
        }
        if (images.length) {
            selectedCommentImages.value = [...selectedCommentImages.value, ...images];
            e.preventDefault();
        }
    } catch (_) { }
}

async function addImagesFromClipboard() {
    try {
        if (!navigator.clipboard?.read) {
            toast.info('Браузер не поддерживает чтение из буфера. Используйте Ctrl+V в поле комментария.');
            return;
        }
        const items = await navigator.clipboard.read();
        const newFiles = [];
        for (const item of items) {
            for (const type of item.types) {
                if (type.startsWith('image/')) {
                    const blob = await item.getType(type);
                    const file = new File([blob], `clipboard-${Date.now()}.${type.split('/')[1] || 'png'}`, { type });
                    newFiles.push(file);
                }
            }
        }
        if (newFiles.length) {
            selectedCommentImages.value = [...selectedCommentImages.value, ...newFiles];
            toast.success(`Добавлено изображений: ${newFiles.length}`);
        } else {
            toast.info('В буфере обмена не найдено изображений. Скопируйте изображение и попробуйте снова.');
        }
    } catch (e) {
        console.warn('Clipboard read failed', e);
        toast.error('Не удалось прочитать буфер обмена. Разрешите доступ или используйте Ctrl+V.');
    }
}

function getObjectURL(file) {
    try {
        const URL_ = (typeof window !== 'undefined' && (window.URL || window.webkitURL)) || null;
        return URL_ ? URL_.createObjectURL(file) : '';
    } catch (_) { return ''; }
}

</script>

<template>
    <teleport to="body">
        <div class="offcanvas offcanvas-end w-50" id="task-source-offcanvas" ref="sourceOffcanvasEl" tabindex="-1"
            role="dialog" aria-hidden="true" :aria-labelledby="'task-source-offcanvas-title'"
            :class="{ show: show && !hasSourceOffcanvas }"
            :style="show && !hasSourceOffcanvas ? 'visibility: visible; z-index: 1045;' : ''">
            <div class="offcanvas-header">
                <button type="button" class="btn btn-icon btn-outline-secondary btn-lg px-3 py-2 me-3"
                    aria-label="Закрыть" @click="closeSourceOffcanvas" title="Закрыть">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="icon icon-tabler icons-tabler-outline icon-tabler-x">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M18 6l-12 12" />
                        <path d="M6 6l12 12" />
                    </svg>
                </button>
                <h5 class="offcanvas-title" :id="'task-source-offcanvas-title'">
                    {{task?.brand?.name || (brands?.find(b => b.id === task?.brand_id)?.name) || ''}} / Исходник
                </h5>
            </div>
            <div class="offcanvas-body">
                <div class="mb-3">
                    <ul class="nav nav-pills">
                        <li class="nav-item">
                            <button type="button" class="nav-link" :class="{ active: sourceActiveTab === 'comments' }"
                                @click="() => { sourceActiveTab = 'comments'; loadSourceComments(); }">
                                Комментарии
                            </button>
                        </li>
                        <li class="nav-item">
                            <button type="button" class="nav-link" :class="{ active: sourceActiveTab === 'files' }"
                                @click="() => { sourceActiveTab = 'files'; initSourceFilesFromTask(task); }">
                                Файлы
                            </button>
                        </li>
                    </ul>
                </div>
                <div v-if="sourceActiveTab === 'comments'">
                    <div v-if="sourceComments.length === 0" class="text-secondary mb-2">Комментариев пока нет.</div>
                    <ul class="list-unstyled">
                        <li v-for="c in sourceComments" :key="c.id"
                            class="mb-3 d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="fw-bold">{{ c.user?.name || '—' }} <span class="text-secondary small">{{ new
                                    Date(c.created_at).toLocaleString('ru-RU') }}</span></div>
                                <div v-if="c.content" style="white-space: pre-wrap;">{{ c.content }}</div>
                                <div v-if="c.image_path" class="mt-2">
                                    <img :src="'/storage/' + c.image_path" class="img-fluid rounded cursor-pointer"
                                        style="max-width: 300px; max-height: 200px;"
                                        @click="() => emit('open-lightbox', '/storage/' + c.image_path, null, sourceComments.filter(x => x.image_path).map(x => '/storage/' + x.image_path))" />
                                </div>
                            </div>
                            <button v-if="canDeleteComment(c)" class="btn btn-ghost-danger btn-sm ms-2" title="Удалить"
                                @click="deleteSourceComment(c)">Удалить</button>
                        </li>
                    </ul>
                    <div class="mt-3">
                        <form @submit.prevent="addSourceComment">
                            <div class="mb-2">
                                <textarea v-model="newSourceComment" rows="2" class="form-control"
                                    placeholder="Новый комментарий…" @paste="onCommentPaste"></textarea>
                            </div>
                            <div class="mb-2">
                                <input type="file" ref="commentImageInput" accept="image/*" multiple class="d-none"
                                    @change="onCommentImagesSelected" />
                                <button type="button" class="btn btn-outline-secondary btn-sm"
                                    @click="openCommentFileDialog">
                                    Вложение
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm ms-1"
                                    @click="addImagesFromClipboard">
                                    Из буфера обмена
                                </button>
                                <div v-if="selectedCommentImages && selectedCommentImages.length"
                                    class="mt-2 d-flex flex-wrap gap-2">
                                    <div v-for="(f, idx) in selectedCommentImages" :key="idx"
                                        class="border rounded p-1">
                                        <img :src="getObjectURL(f)" style="height: 60px; width: auto;" />
                                    </div>
                                </div>
                                <small class="text-secondary d-block mt-1">Максимальный размер: 5MB на файл. Можно
                                    вставлять изображение из буфера обмена (Ctrl+V).</small>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-secondary me-2"
                                    @click="clearSourceCommentForm">Очистить</button>
                                <button type="submit" class="btn btn-primary"
                                    :disabled="!newSourceComment.trim() && (!selectedCommentImages || selectedCommentImages.length === 0) || sourceSubmitting">Добавить</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div v-else>
                    <div class="mb-3 d-flex align-items-center justify-content-between">
                        <label class="form-label m-0">Файл(ы) исходника</label>
                    </div>
                    <div v-for="(f, idx) in sourceFiles" :key="idx" class="input-group mb-2">
                        <input type="text" class="form-control" v-model="sourceFiles[idx]"
                            placeholder="Укажите ссылку или название файла" :readonly="isPerformer" />
                        <a v-if="isUrl(sourceFiles[idx])" class="btn btn-outline-secondary" :href="sourceFiles[idx]"
                            target="_blank" rel="noopener">Открыть</a>
                        <button v-if="!isPerformer" type="button" class="btn btn-outline-danger"
                            @click="removeSourceFilesField(idx)" :disabled="sourceFiles.length <= 1">-</button>
                    </div>
                    <div class="form-text">Добавляйте ссылки (кликабельны) или простые названия файлов.</div>
                    <div v-if="!isPerformer" class="d-flex justify-content-end gap-2 mt-3">
                        <button type="button" class="btn btn-outline-primary" @click="addSourceFilesField">+</button>
                        <button type="button" class="btn btn-primary" @click="saveSourceFiles">Сохранить</button>
                    </div>
                </div>
            </div>
        </div>
        <div v-if="show && !hasSourceOffcanvas" class="modal-backdrop fade show" style="z-index: 1040;"
            @click="closeSourceOffcanvas"></div>
    </teleport>
</template>