<script setup>
import TablerLayout from '@/Layouts/TablerLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
  brands: { type: Array, default: () => [] },
});

// Search and sorting
const searchQuery = ref('');
const filtered = computed(() => {
  const q = searchQuery.value.trim().toLowerCase();
  if (!q) return props.brands;
  return props.brands.filter((b) => (b.name || '').toLowerCase().includes(q));
});
const displayed = computed(() => {
  return [...filtered.value].sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
});

// Forms
const createForm = useForm({ name: '' });
const editForm = useForm({ name: '' });
const deleteForm = useForm({});

const creating = computed(() => createForm.processing);
const updating = computed(() => editForm.processing);

// State
const showCreate = ref(false);
const showEdit = ref(false);
const showDelete = ref(false);
const editingBrand = ref(null);
const brandToDelete = ref(null);

// Articles off-canvas state
const articlesOpen = ref(false);
const articlesBrand = ref(null);
const articles = ref([]);
const articlesLoading = ref(false);
const articleName = ref('');
const bulkFile = ref(null);
const articlesErrors = ref({});

const anyModalOpen = computed(() => showCreate.value || showEdit.value || showDelete.value);
watch(anyModalOpen, (open) => {
  if (open) document.body.classList.add('modal-open');
  else document.body.classList.remove('modal-open');
});

function openCreate() {
  createForm.reset();
  showCreate.value = true;
}

function submitCreate() {
  createForm.post(route('brands.store'), {
    preserveScroll: true,
    onSuccess: () => {
      showCreate.value = false;
      createForm.reset();
    },
  });
}

function startEdit(brand) {
  editingBrand.value = brand;
  editForm.reset();
  editForm.name = brand.name;
  showEdit.value = true;
}

function cancelEdit() {
  showEdit.value = false;
  editingBrand.value = null;
  editForm.reset();
}

function submitEdit() {
  if (!editingBrand.value) return;
  editForm.put(route('brands.update', editingBrand.value.id), {
    preserveScroll: true,
    onSuccess: () => {
      cancelEdit();
    },
  });
}

function startDelete(brand) {
  brandToDelete.value = brand;
  showDelete.value = true;
}

function cancelDelete() {
  brandToDelete.value = null;
  showDelete.value = false;
}

function submitDelete() {
  if (!brandToDelete.value) return;
  deleteForm.delete(route('brands.destroy', brandToDelete.value.id), {
    preserveScroll: true,
    onSuccess: () => {
      cancelDelete();
    },
  });
}

// Articles helpers
function openArticles(brand) {
  articlesBrand.value = brand;
  articlesOpen.value = true;
  loadArticles();
}

function closeArticles() {
  articlesOpen.value = false;
  articlesBrand.value = null;
  articles.value = [];
  articleName.value = '';
  bulkFile.value = null;
  articlesErrors.value = {};
}

async function loadArticles() {
  if (!articlesBrand.value) return;
  articlesLoading.value = true;
  try {
    const { data } = await window.axios.get(route('brands.articles.index', articlesBrand.value.id));
    articles.value = data?.data || [];
  } catch (e) {
    console.error(e);
  } finally {
    articlesLoading.value = false;
  }
}

async function addArticle() {
  if (!articlesBrand.value || !articleName.value.trim()) return;
  articlesErrors.value = {};
  try {
    await window.axios.post(route('brands.articles.store', articlesBrand.value.id), { name: articleName.value.trim() });
    articleName.value = '';
    await loadArticles();
  } catch (e) {
    if (e.response?.status === 422) articlesErrors.value = e.response.data.errors || {};
  }
}

async function bulkUploadArticles(evt) {
  if (!articlesBrand.value || !evt?.target?.files?.length) return;
  const file = evt.target.files[0];
  const form = new FormData();
  form.append('file', file);
  try {
    await window.axios.post(route('brands.articles.bulk_upload', articlesBrand.value.id), form, { headers: { 'Content-Type': 'multipart/form-data' } });
    evt.target.value = '';
    await loadArticles();
  } catch (e) {
    console.error(e);
  }
}

async function deleteArticle(article) {
  if (!articlesBrand.value) return;
  try {
    await window.axios.delete(route('brands.articles.destroy', { brand: articlesBrand.value.id, article: article.id }));
    await loadArticles();
  } catch (e) {
    console.error(e);
  }
}
</script>

<template>

  <Head title="Бренды" />
  <TablerLayout>
    <template #header>Бренды</template>

    <div class="row row-deck">
      <div class="col-12">
        <div class="card">
          <div class="card-table">
            <div class="card-header">
              <div class="row w-full">
                <div class="col">
                  <h3 class="card-title mb-0">Справочник брендов</h3>
                  <p class="text-secondary m-0">Создание, редактирование и удаление брендов.</p>
                </div>
                <div class="col-md-auto col-sm-12">
                  <div class="ms-auto d-flex flex-wrap btn-list">
                    <div class="input-group input-group-flat w-auto me-2">
                      <span class="input-group-text">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                          stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                          class="icon icon-1">
                          <path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0" />
                          <path d="M21 21l-6 -6" />
                        </svg>
                      </span>
                      <input v-model="searchQuery" type="text" class="form-control" autocomplete="off"
                        placeholder="Поиск по наименованию" />
                    </div>
                    <button class="btn btn-primary" @click="openCreate">Добавить</button>
                  </div>
                </div>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-vcenter table-selectable">
                <thead>
                  <tr>
                    <th class="w-1">#</th>
                    <th>Наименование</th>
                    <th>Создан</th>
                    <th class="w-1">Действия</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="displayed.length === 0">
                    <td colspan="4" class="text-center text-secondary py-4">Нет брендов для отображения</td>
                  </tr>
                  <tr v-for="(b, i) in displayed" :key="b.id">
                    <td>{{ i + 1 }}</td>
                    <td>{{ b.name }}</td>
                    <td>{{ new Date(b.created_at).toLocaleString('ru-RU') }}</td>
                    <td>
                      <div class="btn-list flex-nowrap">
                        <button class="btn btn-outline-secondary btn-sm" @click="openArticles(b)">
                          Артикулы
                        </button>
                        <Link class="btn btn-outline-primary btn-sm" :href="route('brands.tasks.index', b.id)">
                        Задания
                        </Link>
                        <button class="btn btn-ghost-primary btn-icon" aria-label="Редактировать" @click="startEdit(b)">
                          <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24"
                            viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M4 20h4l10.5 -10.5a2.828 2.828 0 1 0 -4 -4l-10.5 10.5v4" />
                            <path d="M13.5 6.5l4 4" />
                          </svg>
                        </button>
                        <button class="btn btn-ghost-danger btn-icon" aria-label="Удалить" @click="startDelete(b)">
                          <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24"
                            viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M4 7h16" />
                            <path d="M10 11v6" />
                            <path d="M14 11v6" />
                            <path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" />
                            <path d="M9 7v-2a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v2" />
                          </svg>
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </TablerLayout>

  <!-- Articles Off-canvas (Артикулы) -->
  <teleport to="body">
    <div v-if="articlesOpen" class="offcanvas offcanvas-end show" tabindex="-1" style="visibility: visible; width: 520px; z-index: 1050;">
      <div class="offcanvas-header">
        <h5 class="offcanvas-title">Артикулы — {{ articlesBrand?.name }}</h5>
        <button type="button" class="btn-close" aria-label="Close" @click="closeArticles"></button>
      </div>
      <div class="offcanvas-body">
        <div class="mb-3">
          <label class="form-label">Добавить артикул</label>
          <div class="input-group">
            <input v-model="articleName" type="text" class="form-control" placeholder="Наименование артикула" @keyup.enter="addArticle" />
            <button class="btn btn-primary" @click="addArticle">Добавить</button>
          </div>
          <div v-if="Object.keys(articlesErrors).length" class="text-danger small mt-1">
            <div v-for="(err, key) in articlesErrors" :key="key">{{ err }}</div>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">Массовая загрузка (.txt, каждая строка — отдельный артикул)</label>
          <input type="file" accept=".txt" class="form-control" @change="bulkUploadArticles" />
        </div>

        <div class="mb-2 d-flex align-items-center justify-content-between">
          <h3 class="card-title m-0">Список артикулов</h3>
          <span class="text-secondary" v-if="articlesLoading">Загрузка...</span>
        </div>

        <div class="list-group list-group-flush border-top">
          <div v-if="!articlesLoading && articles.length === 0" class="text-secondary py-4 text-center">Нет артикулов</div>
          <div v-for="a in articles" :key="a.id" class="list-group-item d-flex justify-content-between align-items-center">
            <div class="text-truncate">{{ a.name }}</div>
            <button class="btn btn-sm btn-link text-danger" @click="deleteArticle(a)">Удалить</button>
          </div>
        </div>
      </div>
    </div>
    <div v-if="articlesOpen" class="offcanvas-backdrop fade show" style="z-index: 1040;" @click="closeArticles"></div>
  </teleport>

  <!-- Create Modal -->
  <teleport to="body">
    <div v-if="showCreate">
      <div class="modal modal-blur fade show d-block" tabindex="-1" role="dialog" style="z-index: 1050;">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Добавить бренд</h5>
              <button type="button" class="btn-close" aria-label="Close" @click="showCreate = false"></button>
            </div>
            <div class="modal-body">
              <form @submit.prevent="submitCreate">
                <label class="form-label">Наименование</label>
                <input v-model="createForm.name" type="text" class="form-control" required />
                <div v-if="Object.keys(createForm.errors).length" class="text-danger small mt-2">
                  <div v-for="(err, key) in createForm.errors" :key="key">{{ err }}</div>
                </div>
              </form>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn me-auto" @click="showCreate = false">Отмена</button>
              <button :disabled="creating" type="button" class="btn btn-primary" @click="submitCreate">
                <span v-if="creating" class="spinner-border spinner-border-sm me-2" />
                Создать
              </button>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-backdrop fade show" style="z-index: 1040;" @click="showCreate = false"></div>
    </div>
  </teleport>

  <!-- Edit Modal -->
  <teleport to="body">
    <div v-if="showEdit && editingBrand">
      <div class="modal modal-blur fade show d-block" tabindex="-1" role="dialog" style="z-index: 1050;">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Редактирование бренда</h5>
              <button type="button" class="btn-close" aria-label="Close" @click="cancelEdit"></button>
            </div>
            <div class="modal-body">
              <form @submit.prevent="submitEdit">
                <label class="form-label">Наименование</label>
                <input v-model="editForm.name" type="text" class="form-control" required />
                <div v-if="Object.keys(editForm.errors).length" class="text-danger small mt-2">
                  <div v-for="(err, key) in editForm.errors" :key="key">{{ err }}</div>
                </div>
              </form>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn me-auto" @click="cancelEdit">Отмена</button>
              <button :disabled="updating" type="button" class="btn btn-primary" @click="submitEdit">
                <span v-if="updating" class="spinner-border spinner-border-sm me-2" />
                Сохранить
              </button>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-backdrop fade show" style="z-index: 1040;" @click="cancelEdit"></div>
    </div>
  </teleport>

  <!-- Delete Modal -->
  <teleport to="body">
    <div v-if="showDelete && brandToDelete">
      <div class="modal modal-blur fade show d-block" tabindex="-1" role="dialog" style="z-index: 1050;">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Удалить бренд</h5>
              <button type="button" class="btn-close" aria-label="Close" @click="cancelDelete"></button>
            </div>
            <div class="modal-body">
              Вы уверены, что хотите удалить бренд <strong>{{ brandToDelete?.name }}</strong>? Это действие необратимо.
            </div>
            <div class="modal-footer">
              <button type="button" class="btn me-auto" @click="cancelDelete">Отмена</button>
              <button :disabled="deleteForm.processing" type="button" class="btn btn-danger" @click="submitDelete">
                <span v-if="deleteForm.processing" class="spinner-border spinner-border-sm me-2" />
                Удалить
              </button>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-backdrop fade show" style="z-index: 1040;" @click="cancelDelete"></div>
    </div>
  </teleport>
</template>
