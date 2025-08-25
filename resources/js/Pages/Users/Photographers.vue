<script setup>
import TablerLayout from '@/Layouts/TablerLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
  users: { type: Array, default: () => [] },
  role: { type: String, default: 'Photographer' },
});

// Search
const searchQuery = ref('');
const filteredUsers = computed(() => {
  const q = searchQuery.value.trim().toLowerCase();
  if (!q) return props.users;
  return props.users.filter((u) =>
    (u.name || '').toLowerCase().includes(q) || (u.email || '').toLowerCase().includes(q)
  );
});

const createForm = useForm({
  name: '',
  email: '',
  password: '',
});

const editingUser = ref(null);
const editForm = useForm({
  name: '',
  email: '',
  password: '', // optional on update
});

const creating = computed(() => createForm.processing);
const updating = computed(() => editForm.processing);

// Modal state
const showCreateModal = ref(false);
const showEditModal = ref(false);

// Manage body class when any modal is open
const anyModalOpen = computed(() => showCreateModal.value || showEditModal.value);
watch(anyModalOpen, (open) => {
  if (open) document.body.classList.add('modal-open');
  else document.body.classList.remove('modal-open');
});

function generatePassword(target = 'create') {
  const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+';
  let pwd = '';
  for (let i = 0; i < 12; i++) pwd += chars[Math.floor(Math.random() * chars.length)];
  if (target === 'create') createForm.password = pwd;
  else editForm.password = pwd;
}

function submitCreate() {
  createForm.post(route('users.photographers.store'), {
    onSuccess: () => {
      createForm.reset();
      showCreateModal.value = false;
    },
  });
}

function startEdit(user) {
  editingUser.value = user;
  editForm.reset();
  editForm.name = user.name;
  editForm.email = user.email;
  showEditModal.value = true;
}

function cancelEdit() {
  editingUser.value = null;
  editForm.reset();
  showEditModal.value = false;
}

function submitEdit() {
  if (!editingUser.value) return;
  editForm.put(route('users.photographers.update', editingUser.value.id), {
    preserveScroll: true,
    onSuccess: () => {
      cancelEdit();
    },
  });
}
</script>

<template>

  <Head title="Пользователи — Фотографы" />
  <TablerLayout>
    <template #header>Пользователи / Фотографы</template>

    <div class="row row-deck">
      <div class="col-12">
        <div class="card">
          <div class="card-table">
            <div class="card-header">
              <div class="row w-full">
                <div class="col">
                  <h3 class="card-title mb-0">Фотографы</h3>
                  <p class="text-secondary m-0">Управление пользователями с ролью Фотограф.</p>
                </div>
                <div class="col-md-auto col-sm-12">
                  <div class="ms-auto d-flex flex-wrap btn-list">
                    <div class="input-group input-group-flat w-auto">
                      <span class="input-group-text">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                          stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                          class="icon icon-1">
                          <path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0" />
                          <path d="M21 21l-6 -6" />
                        </svg>
                      </span>
                      <input id="advanced-table-search" v-model="searchQuery" type="text" class="form-control"
                        autocomplete="off" placeholder="Поиск по имени или email" />
                      <span class="input-group-text">
                        <kbd>ctrl + K</kbd>
                      </span>
                    </div>
                    <a href="#" class="btn btn-icon" aria-label="Кнопка">
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="icon icon-1">
                        <path d="M5 12m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" />
                        <path d="M12 12m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" />
                        <path d="M19 12m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" />
                      </svg>
                    </a>
                    <div class="dropdown">
                      <a href="#" class="btn dropdown-toggle" data-bs-toggle="dropdown">Экспорт</a>
                      <div class="dropdown-menu">
                        <a class="dropdown-item" href="#">CSV</a>
                        <a class="dropdown-item" href="#">XLSX</a>
                        <a class="dropdown-item" href="#">PDF</a>
                      </div>
                    </div>
                    <button class="btn btn-primary" @click="showCreateModal = true">Добавить</button>
                  </div>
                </div>
              </div>
            </div>
            <div id="advanced-table">
              <div class="table-responsive">
                <table class="table table-vcenter table-selectable">
                  <thead>
                    <tr>
                      <th class="w-1"></th>
                      <th>
                        <button class="table-sort d-flex justify-content-between" data-sort="sort-name">Имя</button>
                      </th>
                      <th>
                        <button class="table-sort d-flex justify-content-between" data-sort="sort-email">Email</button>
                      </th>
                      <th class="w-1">Действия</th>
                    </tr>
                  </thead>
                  <tbody class="table-tbody">
                    <tr v-for="(u, idx) in filteredUsers" :key="u.id">
                      <td>
                        <input class="form-check-input m-0 align-middle table-selectable-check" type="checkbox"
                          :aria-label="`Выбрать пользователя ${idx + 1}`" />
                      </td>
                      <td class="sort-name">{{ u.name }}</td>
                      <td class="sort-email">{{ u.email }}</td>
                      <td class="sort-actions py-0">
                        <span class="on-unchecked">
                          <div class="btn-list">
                            <button class="btn btn-ghost-primary btn-icon" aria-label="Редактировать" @click="startEdit(u)">
                              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24"
                                viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M4 20h4l10.5 -10.5a2.828 2.828 0 1 0 -4 -4l-10.5 10.5v4" />
                                <path d="M13.5 6.5l4 4" />
                              </svg>
                            </button>
                          </div>
                        </span>
                        <div class="on-checked">
                          <div class="d-flex justify-content-end">
                            <a href="#" class="btn btn-2 btn-icon" aria-label="Ещё">
                              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="icon icon-2">
                                <path d="M5 12m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" />
                                <path d="M12 12m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" />
                                <path d="M19 12m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" />
                              </svg>
                            </a>
                          </div>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div class="card-footer d-flex align-items-center">
                <div class="dropdown">
                  <a class="btn dropdown-toggle" data-bs-toggle="dropdown">
                    <span id="page-count" class="me-1">20</span>
                    <span>записей</span>
                  </a>
                  <div class="dropdown-menu">
                    <a class="dropdown-item" href="#">10 записей</a>
                    <a class="dropdown-item" href="#">20 записей</a>
                    <a class="dropdown-item" href="#">50 записей</a>
                    <a class="dropdown-item" href="#">100 записей</a>
                  </div>
                </div>
                <ul class="pagination m-0 ms-auto">
                  <li class="page-item disabled">
                    <a class="page-link" href="#" tabindex="-1" aria-disabled="true">
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="icon icon-1">
                        <path d="M15 6l-6 6l6 6" />
                      </svg>
                    </a>
                  </li>
                  <li class="page-item active"><a class="page-link" href="#">1</a></li>
                  <li class="page-item"><a class="page-link" href="#">2</a></li>
                  <li class="page-item"><a class="page-link" href="#">3</a></li>
                  <li class="page-item">
                    <a class="page-link" href="#">
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="icon icon-1">
                        <path d="M9 6l6 6l-6 6" />
                      </svg>
                    </a>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- Teleported Create Modal -->
    <teleport to="body">
      <div v-if="showCreateModal">
        <div class="modal modal-blur fade show d-block" tabindex="-1" role="dialog" style="z-index: 1050;">
          <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Добавить фотографа</h5>
                <button type="button" class="btn-close" aria-label="Close" @click="showCreateModal = false"></button>
              </div>
              <div class="modal-body">
                <form @submit.prevent="submitCreate">
                  <div class="row g-2">
                    <div class="col-12 col-md-6">
                      <label class="form-label">Имя</label>
                      <input v-model="createForm.name" type="text" class="form-control" required />
                    </div>
                    <div class="col-12 col-md-6">
                      <label class="form-label">Email</label>
                      <input v-model="createForm.email" type="email" class="form-control" required />
                    </div>
                    <div class="col-12">
                      <label class="form-label">Пароль</label>
                      <div class="input-group">
                        <input v-model="createForm.password" type="text" class="form-control"
                          placeholder="Минимум 8 символов" required />
                        <button class="btn" type="button" @click="generatePassword('create')">Сгенерировать</button>
                      </div>
                    </div>
                  </div>
                  <div v-if="createForm.hasErrors" class="text-danger small mt-2">
                    <div v-for="(err, key) in createForm.errors" :key="key">{{ err }}</div>
                  </div>
                </form>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn me-auto" @click="showCreateModal = false">Отмена</button>
                <button :disabled="creating" type="button" class="btn btn-primary" @click="submitCreate">
                  <span v-if="creating" class="spinner-border spinner-border-sm me-2" />
                  Создать
                </button>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-backdrop fade show" style="z-index: 1040;" @click="showCreateModal = false"></div>
      </div>
    </teleport>

    <!-- Teleported Edit Modal -->
    <teleport to="body">
      <div v-if="showEditModal && editingUser">
        <div class="modal modal-blur fade show d-block" tabindex="-1" role="dialog" style="z-index: 1050;">
          <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Редактирование пользователя</h5>
                <button type="button" class="btn-close" aria-label="Close" @click="cancelEdit"></button>
              </div>
              <div class="modal-body">
                <form @submit.prevent="submitEdit">
                  <div class="row g-2">
                    <div class="col-12 col-md-6">
                      <label class="form-label">Имя</label>
                      <input v-model="editForm.name" type="text" class="form-control" required />
                    </div>
                    <div class="col-12 col-md-6">
                      <label class="form-label">Email</label>
                      <input v-model="editForm.email" type="email" class="form-control" required />
                    </div>
                    <div class="col-12">
                      <label class="form-label">Пароль (опционально)</label>
                      <div class="input-group">
                        <input v-model="editForm.password" type="text" class="form-control"
                          placeholder="Оставьте пустым, чтобы не менять" />
                        <button class="btn" type="button" @click="generatePassword('edit')">Сгенерировать</button>
                      </div>
                    </div>
                  </div>
                  <div v-if="editForm.hasErrors" class="text-danger small mt-2">
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
  </TablerLayout>
</template>
