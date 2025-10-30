<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick, watch } from 'vue';
import { useToast } from 'vue-toastification';

// Props
const props = defineProps({
    tasks: { type: Array, required: true },
    brands: { type: Array, required: true },
    selectedIds: { type: Array, required: true },
    statusOptions: { type: Array, required: true },
    priorityOptions: { type: Array, required: true },
    loading: { type: Boolean, default: false },
    currentUser: { type: Object, default: null },
    hideScroll: { type: Boolean, default: false },
    fullHeight: { type: Boolean, default: false }
});

// Emits
const emit = defineEmits([
    'toggle-row',
    'select-all',
    'update-status',
    'update-priority',
    'open-assign',
    'open-comments',
    'open-source-comments',
    'open-source-files',
    'open-files',
    'copy-link',
    'open-link',
    'edit-task',
    'delete-task'
]);

// Computed
const selectAllVisible = computed({
    get() {
        const ids = props.tasks.map(t => t.id);
        return ids.length > 0 && ids.every(id => props.selectedIds.includes(id));
    },
    set(val) {
        const ids = props.tasks.map(t => t.id);
        emit('select-all', val, ids);
    }
});

const isManager = computed(() => {
    return props.currentUser && (
        props.currentUser.roles?.some(r => r.name === 'Manager' || r.name === 'manager') ||
        props.currentUser.is_manager
    );
});

const isPerformer = computed(() => {
    return props.currentUser && (
        props.currentUser.roles?.some(r => r.name === 'Performer' || r.name === 'performer') ||
        props.currentUser.is_performer
    );
});

const isAdmin = computed(() => {
    return props.currentUser && (
        props.currentUser.roles?.some(r => r.name === 'Administrator' || r.name === 'admin') ||
        props.currentUser.is_admin
    );
});

const toast = useToast();

const activeRowId = ref(null);
function onRowClick(task, event) {
    const target = event?.target;
    const rowId = task?.id;
    if (!rowId) return;
    const interactive = target && (target.closest('button, a, input, select, textarea, [role="button"], .btn, .form-check-input'));
    if (!interactive && activeRowId.value === rowId) {
        activeRowId.value = null;
    } else {
        activeRowId.value = rowId;
    }
}

// Helper functions
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
        case 'done': return 'Принята';
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
        case 'done': return 'bg-success';
        default: return 'bg-secondary';
    }
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
        case 'done': return '#198754';
        default: return '#495057';
    }
}

function priorityLabel(priority) {
    switch (priority) {
        case 'low': return 'Низкий';
        case 'medium': return 'Средний';
        case 'high': return 'Срочный';
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

function isSelected(id) {
    return props.selectedIds.includes(id);
}

// Open source file link in new window
function openSourceFileLink(task) {
    if (!task.source_files || !task.source_files.length || !task.source_files[0]) return;
    window.open(task.source_files[0], '_blank', 'noopener,noreferrer');
}

// Role-based status allowance
function isAllowedStatusValue(val) {
    if (isAdmin.value) return true;
    if (isPerformer.value) {
        return ['in_progress', 'on_review', 'question'].includes(val);
    }
    if (isManager.value) {
        return !['in_progress', 'on_review'].includes(val);
    }
    return true;
}

// Functions for source files and public link handling
function openSourceFilesOffcanvas(task) {
    // Emit event to parent to open source files offcanvas
    emit('open-source-files', task);
}

async function copySourcePublicLink(task) {
    if (!task || !task.source_files || !Array.isArray(task.source_files) || task.source_files.length === 0) {
        return;
    }

    const firstFile = task.source_files[0];
    if (!firstFile || !firstFile.trim()) {
        return;
    }

    try {
        const text = firstFile.trim();
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

        // Show success message
        toast.success('Ссылка скопирована в буфер обмена');

    } catch (e) {
        console.error('Copy failed', e);
        toast.error('Не удалось скопировать ссылку');
    }
}
// Dynamic height to align tbody bottom to viewport bottom
const wrapperRef = ref(null);
let ro = null;

function updateWrapperHeight() {
    const el = wrapperRef.value;
    if (!el) return;
    const rect = el.getBoundingClientRect();
    const viewportH = window.innerHeight || document.documentElement.clientHeight;
    const targetH = Math.max(0, Math.floor(viewportH - rect.top));
    el.style.height = targetH + 'px';
}

function setupObservers() {
    // Recalculate on viewport resize
    window.addEventListener('resize', updateWrapperHeight);
    // Recalculate when layout above changes size
    if ('ResizeObserver' in window) {
        ro = new ResizeObserver(() => updateWrapperHeight());
        // observe the nearest scroll container or document body as a fallback
        ro.observe(document.body);
    }
}

onMounted(async () => {
    await nextTick();
    updateWrapperHeight();
    setupObservers();
    updateBodyScrollClass();
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', updateWrapperHeight);
    if (ro) { try { ro.disconnect(); } catch (e) { } ro = null; }
    removeBodyScrollClass();
});

// Watch for hideScroll prop changes to update body class
watch(() => props.hideScroll, (newVal) => {
    updateBodyScrollClass();
});

// Also watch for window resize to re-evaluate scrollbar hiding
watch(() => window.innerHeight, () => {
    if (props.hideScroll) {
        updateBodyScrollClass();
    }
});

function addBodyScrollClass() {
    document.body.classList.add('hide-main-scroll');
    document.documentElement.classList.add('hide-main-scroll');
}

function removeBodyScrollClass() {
    document.body.classList.remove('hide-main-scroll');
    document.documentElement.classList.remove('hide-main-scroll');
}

function updateBodyScrollClass() {
    if (props.hideScroll) {
        // Only hide scrollbar if content fits in viewport
        const contentHeight = document.body.scrollHeight;
        const viewportHeight = window.innerHeight;
        if (contentHeight <= viewportHeight) {
            addBodyScrollClass();
        } else {
            removeBodyScrollClass();
        }
    } else {
        removeBodyScrollClass();
    }
}

</script>

<template>
    <div ref="wrapperRef" class="table-wrapper" :class="{ 'full-height': fullHeight }"
        :style="{ position: 'relative' }">
        <div id="tableBodyWrapper" :style="{ height: '100%', overflowY: 'auto' }">
            <table class="table">
                <thead>
                    <tr>
                        <th class="text-center">
                            <input type="checkbox" class="form-check-input" v-model="selectAllVisible" />
                        </th>
                        <th class="text-start">Создан</th>
                        <th class="text-start">Наименование задачи</th>
                        <th class="text-start">Бренд, Артикул</th>
                        <th class="text-start">Тип</th>
                        <th class="text-end">{{ isPerformer ? 'Принимающий' : 'Исполнитель' }}</th>
                        <th class="text-center w-1">Исходник</th>
                        <th class="text-center w-1">Результат</th>
                        <th class="text-start">Статус</th>
                        <th class="text-start">Приоритет</th>
                        <th v-if="!isPerformer" class="text-end">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="tasks.length === 0">
                        <td :colspan="isPerformer ? 10 : 11" class="text-center text-secondary py-4">Нет задач</td>
                    </tr>
                    <tr v-for="t in tasks" :key="t.id" style="font-size: 13px !important;"
                        :class="{ 'row-active': activeRowId === t.id }" @click="onRowClick(t, $event)">
                        <td class="text-center" style="vertical-align: middle;">
                            <input type="checkbox" class="form-check-input" :checked="isSelected(t.id)"
                                @change="emit('toggle-row', t.id)" />
                        </td>
                        <td class="text-start" style="vertical-align: middle;">{{ new
                            Date(t.created_at).toLocaleString('ru-RU') }}</td>
                        <td style="vertical-align: middle;">{{ t.name || t.article?.name || '' }}</td>
                        <td style="vertical-align: middle;">
                            {{t.brand?.name || (brands.find(b => b.id === t.brand_id)?.name)}}<br />{{ t.article?.name
                                || '' }}
                        </td>
                        <td style="vertical-align: middle;">{{ t.type?.name || '' }}</td>
                        <td style="vertical-align: middle;" class="text-end">
                            <template v-if="isPerformer">
                                <span v-if="t.creator" class="text-secondary">
                                    {{ [t.creator.last_name, t.creator.first_name,
                                    t.creator.middle_name].filter(Boolean).join(' ') || t.creator.name || 'Не указан' }}
                                </span>
                                <span v-else class="text-muted">Не указан</span>
                            </template>
                            <template v-else>
                                <span v-if="t.assignee?.name" class="text-secondary me-2">{{ t.assignee.name }}</span>
                                <button class="btn btn-sm btn-outline-primary" @click="emit('open-assign', t)">
                                    {{ t.assignee?.name ? 'Изменить' : 'Назначить' }}
                                </button>
                            </template>
                        </td>
                        <td style="vertical-align: middle;" class="text-center">
                            <div class="d-flex gap-1">
                                <button class="btn btn-icon btn-ghost-secondary"
                                    @click="emit('open-source-comments', t)">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round"
                                        class="icon icon-tabler icons-tabler-outline icon-tabler-message">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M8 9h8" />
                                        <path d="M8 13h6" />
                                        <path
                                            d="M18 4a3 3 0 0 1 3 3v8a3 3 0 0 1 -3 3h-5l-5 3v-3h-2a3 3 0 0 1 -3 -3v-8a3 3 0 0 1 3 -3h12z" />
                                    </svg>
                                </button>
                                <button class="btn btn-icon btn-ghost-primary" @click="openSourceFilesOffcanvas(t)">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round"
                                        class="icon icon-tabler icons-tabler-outline icon-tabler-paperclip">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path
                                            d="M15 7l-6.5 6.5a1.5 1.5 0 0 0 3 3l6.5 -6.5a3 3 0 0 0 -6 -6l-6.5 6.5a4.5 4.5 0 0 0 9 9l6.5 -6.5" />
                                    </svg>
                                </button>
                                <button class="btn btn-icon btn-ghost-secondary" @click="copySourcePublicLink(t)"
                                    :disabled="!t.source_files || !t.source_files.length || !t.source_files[0]"
                                    :class="{ 'opacity-50': !t.source_files || !t.source_files.length || !t.source_files[0] }"
                                    title="Копировать ссылку">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round"
                                        class="icon icon-tabler icons-tabler-outline icon-tabler-files">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M15 3v4a1 1 0 0 0 1 1h4" />
                                        <path
                                            d="M18 17h-7a2 2 0 0 1 -2 -2v-10a2 2 0 0 1 2 -2h4l5 5v7a2 2 0 0 1 -2 2z" />
                                        <path d="M16 17v2a2 2 0 0 1 -2 2h-7a2 2 0 0 1 -2 -2v-10a2 2 0 0 1 2 -2h2" />
                                    </svg>
                                </button>
                                <button class="btn btn-icon btn-ghost-primary" @click="openSourceFileLink(t)"
                                    :disabled="!t.source_files || !t.source_files.length || !t.source_files[0]"
                                    :class="{ 'opacity-50': !t.source_files || !t.source_files.length || !t.source_files[0] }"
                                    type="button">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round"
                                        class="icon icon-tabler icons-tabler-outline icon-tabler-external-link">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M12 6h-6a2 2 0 0 0 -2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-6" />
                                        <path d="M11 13l9 -9" />
                                        <path d="M15 4h5v5" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                        <td style="vertical-align: middle;" class="text-center">
                            <div v-if="t.folder_created === false" class="text-danger">Папка создается</div>
                            <div v-else class="d-flex gap-1">
                                <button class="btn btn-icon btn-ghost-secondary" @click="emit('open-comments', t)">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round"
                                        class="icon icon-tabler icons-tabler-outline icon-tabler-message">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M8 9h8" />
                                        <path d="M8 13h6" />
                                        <path
                                            d="M18 4a3 3 0 0 1 3 3v8a3 3 0 0 1 -3 3h-5l-5 3v-3h-2a3 3 0 0 1 -3 -3v-8a3 3 0 0 1 3 -3h12z" />
                                    </svg>
                                </button>
                                <button class="btn btn-icon btn-ghost-primary" @click="emit('open-files', t)">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round"
                                        class="icon icon-tabler icons-tabler-outline icon-tabler-paperclip">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path
                                            d="M15 7l-6.5 6.5a1.5 1.5 0 0 0 3 3l6.5 -6.5a3 3 0 0 0 -6 -6l-6.5 6.5a4.5 4.5 0 0 0 9 9l6.5 -6.5" />
                                    </svg>
                                </button>
                                <button class="btn btn-icon btn-ghost-primary" @click="emit('copy-link', t)"
                                    type="button">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round"
                                        class="icon icon-tabler icons-tabler-outline icon-tabler-files">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M15 3v4a1 1 0 0 0 1 1h4" />
                                        <path
                                            d="M18 17h-7a2 2 0 0 1 -2 -2v-10a2 2 0 0 1 2 -2h4l5 5v7a2 2 0 0 1 -2 2z" />
                                        <path d="M16 17v2a2 2 0 0 1 -2 2h-7a2 2 0 0 1 -2 -2v-10a2 2 0 0 1 2 -2h2" />
                                    </svg>
                                </button>
                                <button class="btn btn-icon btn-ghost-primary" @click="emit('open-link', t)"
                                    type="button">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round"
                                        class="icon icon-tabler icons-tabler-outline icon-tabler-external-link">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M12 6h-6a2 2 0 0 0 -2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-6" />
                                        <path d="M11 13l9 -9" />
                                        <path d="M15 4h5v5" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                        <td style="vertical-align: middle;">
                            <div class="d-flex align-items-center gap-2">
                                <select class="form-select form-select-sm w-auto" :value="t.status"
                                    :style="{ color: getStatusColor(t.status) }"
                                    :disabled="isPerformer && t.status === 'accepted'" @change="(e) => {
                                        if (isAllowedStatusValue(e.target.value)) {
                                            emit('update-status', t, e.target.value);
                                        } else {
                                            e.target.value = t.status; // Reset to current value if not allowed
                                        }
                                    }">
                                    <option v-for="s in statusOptions" :key="s.value" :value="s.value"
                                        :disabled="!isAllowedStatusValue(s.value)"
                                        :style="{ color: getStatusColor(s.value) }">
                                        {{ s.label }}
                                    </option>
                                </select>
                            </div>
                        </td>
                        <td style="vertical-align: middle;">
                            <div class="d-flex align-items-center gap-2">
                                <select v-if="!isPerformer" class="form-select form-select-sm w-auto"
                                    :value="t.priority || 'medium'"
                                    @change="(e) => emit('update-priority', t, e.target.value)">
                                    <option v-for="p in priorityOptions" :key="p.value" :value="p.value">{{ p.label }}
                                    </option>
                                </select>
                                <span v-else class="badge text-light" :class="priorityClass(t.priority || 'medium')">
                                    {{ priorityLabel(t.priority || 'medium') }}
                                </span>
                            </div>
                        </td>
                        <td v-if="!isPerformer" style="vertical-align: middle;">
                            <div class="d-flex gap-1 justify-content-end">
                                <button class="btn btn-icon btn-ghost-primary" @click="emit('edit-task', t)"
                                    type="button">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round"
                                        class="icon icon-tabler icons-tabler-outline icon-tabler-edit">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" />
                                        <path
                                            d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z" />
                                        <path d="M16 5l3 3" />
                                    </svg>
                                </button>
                                <button v-if="!isManager && !isPerformer" class="btn btn-icon btn-ghost-danger"
                                    @click="emit('delete-task', t)" type="button">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round"
                                        class="icon icon-tabler icons-tabler-outline icon-tabler-trash">
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
                    <tr v-if="loading && tasks.length">
                        <td :colspan="isPerformer ? 10 : 11" class="text-center py-2 text-secondary">
                            <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                            Загрузка...
                        </td>
                    </tr>
                    <tr ref="infiniteSentinelEl" style="height: 1px; visibility: hidden;"></tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<style scoped>
.table-wrapper {
    position: relative;
    height: calc(100vh - 200px);
}

.table-wrapper.full-height {
    height: 100vh;
}

#tableBodyWrapper {
    height: 100%;
    overflow-y: auto;
}


/* Hide scrollbar only when content fits in viewport */
:global(.hide-main-scroll) {
    scrollbar-width: none;
    /* Firefox */
    -ms-overflow-style: none;
    /* IE and Edge */
}

:global(.hide-main-scroll::-webkit-scrollbar) {
    display: none;
    /* Chrome, Safari, Opera */
}

.row-active {
    background-color: rgba(13, 110, 253, 0.08);
}
</style>