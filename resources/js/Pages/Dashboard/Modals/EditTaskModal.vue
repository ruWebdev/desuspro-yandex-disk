<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import { useToast } from 'vue-toastification';
import axios from 'axios';

const props = defineProps({
    show: { type: Boolean, default: false },
    editingTask: { type: Object, default: null },
    brands: { type: Array, required: true },
    taskTypes: { type: Array, required: true },
    performers: { type: Array, required: true }
});

const emit = defineEmits(['close', 'updated']);

const editForm = ref({
    name: '',
    brand_id: '',
    task_type_id: '',
    article_id: '',
    assignee_id: '',
    priority: 'medium',
    // initialize source_files from task or keep one empty field
    source_files: ['']
});

const editBrandArticles = ref([]);
const editArticleSearch = ref('');
const showEditArticleDropdown = ref(false);
const editing = ref(false);

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

// Handlers for dynamic FILE text fields (Edit modal)
function addEditSourceFileField() {
    if (!Array.isArray(editForm.value.source_files)) editForm.value.source_files = [''];
    editForm.value.source_files.push('');
}
function removeEditSourceFileField(idx) {
    if (!Array.isArray(editForm.value.source_files)) return;
    if (editForm.value.source_files.length <= 1) return; // keep at least one field
    editForm.value.source_files.splice(idx, 1);
}

function populateFromTask(task) {
    if (!task) return;
    editForm.value = {
        name: task.name || '',
        brand_id: String(task.brand_id || ''),
        task_type_id: String(task.task_type_id || ''),
        article_id: String(task.article_id || ''),
        assignee_id: String(task.assignee_id || ''),
        priority: task.priority || 'medium',
        // initialize source_files from task or keep one empty field
        source_files: Array.isArray(task.source_files) && task.source_files.length
            ? task.source_files.map(v => (v ?? '').toString())
            : ['']
    };
    editArticleSearch.value = '';
    showEditArticleDropdown.value = false;
}

async function ensureArticlesAndArticle(task) {
    if (!task) return;
    await loadEditArticlesForBrand(task.brand_id);
    if (task.article_id) {
        const exists = editBrandArticles.value.some(a => a.id == task.article_id);
        if (!exists && task.article) {
            editBrandArticles.value.unshift({
                id: task.article_id,
                name: task.article.name || `Артикул #${task.article_id}`,
                article_number: task.article.article_number
            });
        }
        editForm.value.article_id = task.article_id;
        const a = editBrandArticles.value.find(a => a.id == task.article_id);
        if (a) editArticleSearch.value = a.name || '';
        else if (task.article?.name) editArticleSearch.value = task.article.name;
    }
}

// When modal opens or editing task changes, populate form
watch(() => props.show, async (val) => {
    if (val && props.editingTask) {
        populateFromTask(props.editingTask);
        await nextTick();
        await ensureArticlesAndArticle(props.editingTask);
    }
});

watch(() => props.editingTask, async (t) => {
    if (props.show && t) {
        populateFromTask(t);
        await nextTick();
        await ensureArticlesAndArticle(t);
    }
});

function close() {
    emit('close');
}

const toast = useToast();

async function submitEdit() {
    if (!props.editingTask) return;

    // Validate required fields first
    if (!editForm.value.brand_id || !editForm.value.task_type_id) {
        toast.error('Пожалуйста, заполните все обязательные поля: бренд и тип задачи.');
        return;
    }

    // Validate article selection - must be selected from dropdown
    if (!editForm.value.article_id) {
        toast.error('Пожалуйста, выберите артикул из списка. Ручной ввод не допускается.');
        return;
    }

    const selected = editBrandArticles.value.find(a => a.id == editForm.value.article_id);
    if (!selected) {
        toast.error('Пожалуйста, выберите артикул из списка. Ручной ввод не допускается.');
        return;
    }

    const payload = {
        brand_id: editForm.value.brand_id ? Number(editForm.value.brand_id) : null,
        task_type_id: editForm.value.task_type_id ? Number(editForm.value.task_type_id) : null,
        article_id: editForm.value.article_id ? Number(editForm.value.article_id) : null,
        name: editForm.value.name?.trim() || undefined,
        assignee_id: editForm.value.assignee_id ? Number(editForm.value.assignee_id) : null,
        priority: editForm.value.priority || 'medium',
        // Only send non-empty text file entries
        ...(Array.isArray(editForm.value.source_files)
            ? (() => {
                const files = editForm.value.source_files
                    .map(v => (v ?? '').toString().trim())
                    .filter(v => v.length > 0);
                return files.length ? { source_files: files } : { source_files: [] };
            })()
            : { source_files: [] })
    };

    editing.value = true;
    try {
        await axios.put(route('brands.tasks.update', { brand: props.editingTask.brand_id, task: props.editingTask.id }), payload);
        emit('updated');
        close();
    } catch (error) {
        console.error('Error updating task:', error);
        toast.error('Произошла ошибка при сохранении задачи.');
    } finally {
        editing.value = false;
    }
}

function onEditArticleSearchInput() {
    showEditArticleDropdown.value = true;
    // Clear article_id if user is typing manually (not selecting from dropdown)
    const currentSearch = editArticleSearch.value.trim();
    const matchingArticle = editBrandArticles.value.find(a =>
        a.name.toLowerCase() === currentSearch.toLowerCase()
    );
    if (!matchingArticle) {
        editForm.value.article_id = '';
    }
}

function hideEditDropdown() {
    setTimeout(() => showEditArticleDropdown.value = false, 200);
}

function selectEditArticle(article) {
    editForm.value.article_id = String(article.id);
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

// Watch for edit brand changes
watch(() => editForm.value.brand_id, async (newBrandId) => {
    editForm.value.article_id = '';
    editArticleSearch.value = '';
    if (newBrandId) {
        await loadEditArticlesForBrand(Number(newBrandId));
    } else {
        editBrandArticles.value = [];
    }
});
</script>

<template>
    <teleport to="body">
        <div class="modal fade" :class="{ show: show }" :style="show ? 'display: block;' : ''" id="editModal"
            data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="editModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editModalLabel">Редактировать задание</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                            @click="close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Бренд</label>
                                <input type="text" class="form-control bg-light"
                                    :value="brands.find(b => b.id == editForm.brand_id)?.name || ''" readonly>
                                <input type="hidden" v-model="editForm.brand_id">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Тип задачи</label>
                                <input type="text" class="form-control bg-light"
                                    :value="taskTypes.find(tt => tt.id == editForm.task_type_id)?.name || ''" readonly>
                                <input type="hidden" v-model="editForm.task_type_id">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Артикул</label>
                                <input type="text" class="form-control bg-light"
                                    :value="editSelectedArticle?.name || ''" readonly>
                                <input type="hidden" v-model="editForm.article_id">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Приоритет</label>
                                <select class="form-select" :value="editForm.priority"
                                    @input="editForm.priority = $event.target.value">
                                    <option
                                        v-for="p in [{ value: 'low', label: 'Низкий' }, { value: 'medium', label: 'Средний' }, { value: 'high', label: 'Срочный' }]"
                                        :key="p.value" :value="p.value">
                                        {{ p.label }}
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Наименование задачи</label>
                                <input type="text" class="form-control" :value="editForm.name"
                                    @input="editForm.name = $event.target.value" placeholder="Наименование задачи" />
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Исполнитель</label>
                                <select class="form-select" :value="editForm.assignee_id"
                                    @input="editForm.assignee_id = $event.target.value">
                                    <option value="">Не назначен</option>
                                    <option v-for="u in performers" :key="u.id" :value="u.id">{{ u.name }}<span
                                            v-if="u.is_blocked"> — ЗАБЛОКИРОВАН</span></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal"
                            @click="close">Отмена</button>
                        <button type="button" class="btn btn-primary ms-auto" @click="submitEdit" :disabled="editing">
                            <span v-if="editing" class="spinner-border spinner-border-sm me-2" role="status"></span>
                            Сохранить
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </teleport>
</template>

<style scoped>
.cursor-pointer {
    cursor: pointer;
}

.hover-bg-light:hover {
    background-color: #f8f9fa;
}
</style>