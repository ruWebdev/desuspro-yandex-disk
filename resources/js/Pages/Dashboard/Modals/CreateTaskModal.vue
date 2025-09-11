<script setup>
import { ref, computed, watch } from 'vue';
import { useToast } from 'vue-toastification';
import axios from 'axios';

const props = defineProps({
    show: { type: Boolean, default: false },
    brands: { type: Array, required: true },
    taskTypes: { type: Array, required: true },
    performers: { type: Array, required: true }
});

const toast = useToast();

const emit = defineEmits(['close', 'created']);

const createForm = ref({
    name: '',
    brand_id: '',
    task_type_id: '',
    article_id: '',
    assignee_id: '',
    priority: 'medium',
    // Text fields for FILE links/names (optional)
    source_files: [''],
    // Optional source comment to persist along with the task
    source_comment: ''
});

const brandArticles = ref([]);
const creating = ref(false);
const articleSearch = ref('');
const selectedArticle = ref(null);
const showArticleDropdown = ref(false);

const filteredArticles = computed(() => {
    if (!articleSearch.value) return brandArticles.value;
    const search = articleSearch.value.toLowerCase();
    return brandArticles.value.filter(a =>
        a.name.toLowerCase().includes(search) ||
        (a.description && a.description.toLowerCase().includes(search))
    );
});

// Button availability: require brand, article, and task type
const canSubmit = computed(() => {
    return !!(createForm.value.brand_id && createForm.value.task_type_id && createForm.value.article_id);
});

async function loadArticlesForBrand(brandId) {
    brandArticles.value = [];
    if (!brandId) return;
    try {
        const response = await axios.get(route('brands.articles.index', { brand: brandId }));
        if (response.data && Array.isArray(response.data.data)) {
            brandArticles.value = response.data.data;
        }
    } catch (error) {
        console.error('Error loading articles:', error);
    }
}

function onArticleSearchInput() {
    showArticleDropdown.value = true;
}

function hideDropdown() {
    setTimeout(() => {
        showArticleDropdown.value = false;
    }, 200);
}

function selectArticle(article) {
    createForm.value.article_id = article.id;
    selectedArticle.value = article;
    articleSearch.value = article.name;
    showArticleDropdown.value = false;
}

function open() {
    createForm.value = {
        name: '',
        brand_id: '',
        task_type_id: '',
        article_id: '',
        assignee_id: '',
        priority: 'medium',
        source_files: [''],
        source_comment: ''
    };
    brandArticles.value = [];
    emit('open');
}

function close() {
    emit('close');
}

async function submitCreate() {
    // Enforce article selection from list
    const selected = brandArticles.value.find(a => a.id == createForm.value.article_id);
    if (!selected) { toast.error('Пожалуйста, выберите артикул из списка. Ручной ввод не допускается.'); return; }

    const payload = {
        brand_id: createForm.value.brand_id ? Number(createForm.value.brand_id) : null,
        task_type_id: createForm.value.task_type_id ? Number(createForm.value.task_type_id) : null,
        article_id: createForm.value.article_id ? Number(createForm.value.article_id) : null,
        name: createForm.value.name?.trim() || undefined,
        assignee_id: createForm.value.assignee_id ? Number(createForm.value.assignee_id) : null,
        priority: createForm.value.priority || 'medium',
        // optional source comment
        ...(createForm.value.source_comment && createForm.value.source_comment.trim().length
            ? { source_comment: createForm.value.source_comment.trim() }
            : {}),
        // Only send non-empty text file entries
        ...(Array.isArray(createForm.value.source_files)
            ? (() => {
                const files = createForm.value.source_files
                    .map(v => (v ?? '').toString().trim())
                    .filter(v => v.length > 0);
                return files.length ? { source_files: files } : {};
            })()
            : {})
    };
    if (!payload.brand_id || !payload.task_type_id || !payload.article_id) return;
    creating.value = true;
    try {
        await axios.post(route('tasks.store'), payload);
        emit('created');
        close();
    } catch (error) {
        console.error('Error creating task:', error);
    } finally {
        creating.value = false;
    }
}

// Handlers for dynamic FILE text fields (Create modal)
function addSourceFileField() {
    if (!Array.isArray(createForm.value.source_files)) createForm.value.source_files = [''];
    createForm.value.source_files.push('');
}
function removeSourceFileField(idx) {
    if (!Array.isArray(createForm.value.source_files)) return;
    if (createForm.value.source_files.length <= 1) return; // keep at least one field
    createForm.value.source_files.splice(idx, 1);
}

// Prevent duplicate non-empty values across FILE(s) fields
watch(() => createForm.value.source_files, (arr) => {
    try {
        if (!Array.isArray(arr)) return;
        const normalized = arr.map(v => (v ?? '').toString().trim());
        const seen = new Set();
        normalized.forEach((val, idx) => {
            if (!val) return; // allow empty values
            if (seen.has(val)) {
                // Clear the duplicate entry and inform user
                createForm.value.source_files[idx] = '';
                toast.warning('Дубликаты значений в поле ФАЙЛ(ы) недопустимы');
            } else {
                seen.add(val);
            }
        });
    } catch (e) {
        console.error(e);
    }
}, { deep: true });

// Watch for selected article changes
watch(selectedArticle, (newVal) => {
    try {
        if (newVal) {
            articleSearch.value = newVal.name;
        }
    } catch (e) {
        console.error(e);
    }
});

// Watch for brand changes
watch(() => createForm.value.brand_id, (newVal) => {
    if (newVal) {
        loadArticlesForBrand(Number(newVal));
    } else {
        brandArticles.value = [];
    }
    createForm.value.article_id = '';
    articleSearch.value = '';
});

// Reset all form data on each modal open
watch(() => props.show, (val) => {
    if (val) {
        createForm.value = {
            name: '',
            brand_id: '',
            task_type_id: '',
            article_id: '',
            assignee_id: '',
            priority: 'medium',
            source_files: [''],
            source_comment: ''
        };
        brandArticles.value = [];
        articleSearch.value = '';
        selectedArticle.value = null;
        showArticleDropdown.value = false;
    }
});
</script>

<template>
    <teleport to="body">
        <div class="modal fade" :class="{ show: show }" :style="show ? 'display: block;' : ''" id="createModal"
            data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="createModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="createModalLabel">Создать задачу</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                            @click="close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Бренд</label>
                                <select class="form-select" v-model="createForm.brand_id">
                                    <option value="">Выберите бренд</option>
                                    <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Тип задачи</label>
                                <select class="form-select" v-model="createForm.task_type_id">
                                    <option value="">Выберите тип</option>
                                    <option v-for="tt in taskTypes" :key="tt.id" :value="tt.id">{{ tt.name }}</option>
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
                                    <option v-for="u in performers" :key="u.id" :value="u.id">{{ u.name }}<span
                                            v-if="u.is_blocked"> — ЗАБЛОКИРОВАН</span></option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Приоритет</label>
                                <select class="form-select" v-model="createForm.priority">
                                    <option
                                        v-for="p in [{ value: 'low', label: 'Низкий' }, { value: 'medium', label: 'Средний' }, { value: 'high', label: 'Высокий' }, { value: 'urgent', label: 'Срочный' }]"
                                        :key="p.value" :value="p.value">
                                        {{ p.label }}
                                    </option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label d-flex align-items-center justify-content-between">
                                    <span>ФАЙЛ(ы)</span>
                                    <div>
                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                            @click="addSourceFileField">+
                                        </button>
                                    </div>
                                </label>
                                <div v-for="(f, idx) in createForm.source_files" :key="idx" class="input-group mb-2">
                                    <input type="text" class="form-control" v-model="createForm.source_files[idx]"
                                        placeholder="Укажите ссылку или название файла" />
                                    <button type="button" class="btn btn-outline-danger"
                                        @click="removeSourceFileField(idx)"
                                        :disabled="createForm.source_files.length <= 1">-</button>
                                </div>
                                <div class="form-text">Добавляйте текстовые значения (например ссылки) для связанных
                                    файлов.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">КОММЕНТАРИЙ</label>
                                <textarea class="form-control" rows="3" v-model="createForm.source_comment"
                                    placeholder="Комментарий к исходнику (необязательно)"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal"
                            @click="close">Отмена</button>
                        <button type="button" class="btn btn-primary ms-auto" @click="submitCreate"
                            :disabled="creating || !canSubmit">
                            <span v-if="creating" class="spinner-border spinner-border-sm me-2" role="status"></span>
                            {{ creating ? 'Создание...' : 'Создать' }}
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