<script>

// Импорт разметки для проекта
import MainLayout from '@/Layouts/MainLayout.vue';
import axios from 'axios';

export default {
  layout: MainLayout
};

</script>

<script setup>
import { ref, computed, onMounted, inject } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import ContentLayout from '@/Layouts/ContentLayout.vue';
import { Modal } from 'bootstrap';
import { useToastService } from '@/plugins/toast';

// Получить сервис toast и fallback на встроенный сервис
const injectedToast = inject('toast', null);
const toast = injectedToast || useToastService();

const props = defineProps({
  users: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
})

const search = ref(props.filters.search || '')

function onSearch() {
  router.get(route('users.executors.index'), { search: search.value }, { preserveState: true, replace: true })
}

let createModal = null;
let editModal = null;
let deleteModal = null;
let deleteRestrictedModal = null;

const selected = ref(null);
const passwordError = ref('');

// Initialize modals when component is mounted
onMounted(() => {
  // We'll initialize modals on first use instead of here
});

// Function to get or initialize a modal
const getOrCreateModal = (id) => {
  const el = document.getElementById(id);
  if (!el) return null;

  // If modal instance exists, return it
  const existingInstance = Modal.getInstance?.(el);
  if (existingInstance) return existingInstance;

  // Otherwise create a new instance
  return new Modal(el);
};

const createForm = useForm({
  name: '',
  email: '',
  password: '',
  last_name: '',
  first_name: '',
  middle_name: '',
  executor_contacts: '',
  is_blocked: false,
})

const editForm = useForm({
  name: '',
  email: '',
  password: '',
  last_name: '',
  first_name: '',
  middle_name: '',
  executor_contacts: '',
  is_blocked: false,
})

// Валидация: все поля должны быть заполнены
const isFilled = (v) => typeof v === 'string' ? v.trim().length > 0 : !!v
const createInvalid = computed(() => !(
  isFilled(createForm.last_name) &&
  isFilled(createForm.first_name) &&
  // middle_name необязательно
  isFilled(createForm.email) &&
  isFilled(createForm.password)
))
const editInvalid = computed(() => !(
  isFilled(editForm.last_name) &&
  isFilled(editForm.first_name) &&
  // middle_name необязательно
  isFilled(editForm.email)
))

function openCreate() {
  createForm.reset();
  passwordError.value = '';

  // Get or create modal instance
  const modal = getOrCreateModal('modal-create-executor');
  if (modal) {
    createModal = modal;
    modal.show();
  } else {
    console.error('Could not find or initialize create modal');
  }
}

function openEdit(user) {
  selected.value = user;
  editForm.reset();

  // Разобрать полное имя на отдельные поля и подготовить форму ДО показа модалки
  const nameParts = (user.name || '').split(' ').filter(Boolean);
  editForm.last_name = nameParts[0] || '';
  editForm.first_name = nameParts[1] || '';
  editForm.middle_name = nameParts[2] || '';
  editForm.email = user.email;
  editForm.executor_contacts = user.executor_contacts || '';
  editForm.is_blocked = !!user.is_blocked;
  passwordError.value = '';

  // Затем показать модалку
  const modal = getOrCreateModal('modal-edit-executor');
  if (modal) {
    editModal = modal;
    modal.show();
  } else {
    console.error('Could not find or initialize edit modal');
  }
}

function openDelete(user) {
  selected.value = user;
  // Get or create modal instance
  const modal = getOrCreateModal('modal-delete-executor');
  if (modal) {
    deleteModal = modal;
    modal.show();
  } else {
    console.error('Could not find or initialize delete modal');
  }
}

function generatePassword(target) {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%'
  let pass = ''
  for (let i = 0; i < 12; i++) pass += chars[Math.floor(Math.random() * chars.length)]
  if (target === 'create') createForm.password = pass
  else editForm.password = pass
}

function submitCreate() {
  // Validate password length
  if (createForm.password.length > 0 && createForm.password.length < 8) {
    passwordError.value = 'Пароль должен содержать не менее 8 символов';
    return;
  }

  // вывести отображаемое имя из ФИО
  createForm.name = [createForm.last_name, createForm.first_name, createForm.middle_name].filter(Boolean).join(' ').trim();

  createForm.post(route('users.executors.store'), {
    onSuccess: () => {
      createForm.reset()
      createModal.hide()
      toast.success('Исполнитель успешно создан')
    },
    onError: (errors) => {
      const errorMessage = errors?.message || 'Ошибка при создании исполнителя'
      toast.error(errorMessage)
    }
  })
}

function submitEdit() {
  if (!selected.value) return;

  // Validate password length if provided
  const pwd = (editForm.password || '').trim();
  if (pwd.length > 0 && pwd.length < 8) {
    passwordError.value = 'Пароль должен содержать не менее 8 символов';
    return;
  }

  // вывести отображаемое имя из ФИО
  const name = [editForm.last_name, editForm.first_name, editForm.middle_name].filter(Boolean).join(' ').trim();
  const payload = {
    ...editForm.data(),
    name,
  };

  if (!(pwd.length > 0)) {
    delete payload.password;
  } else {
    payload.password = pwd;
  }

  editForm.transform(() => payload).put(route('users.executors.update', selected.value.id), {
    onSuccess: () => {
      editForm.reset()
      editModal.hide()
      toast.success('Данные исполнителя успешно обновлены')
    },
    onError: (errors) => {
      if (errors.password) {
        passwordError.value = errors.password
        toast.error('Ошибка валидации пароля')
      } else {
        const errorMessage = errors?.message || 'Ошибка при обновлении данных исполнителя'
        toast.error(errorMessage)
      }
    },
  });
}

function submitDelete() {
  if (!selected.value) return;

  router.delete(route('users.executors.destroy', selected.value.id), {
    preserveScroll: true,
    onSuccess: () => {
      deleteModal.hide()
      toast.success('Исполнитель успешно удален')
    },
    onError: (errors) => {
      if (errors.restricted) {
        deleteModal && deleteModal.hide()
        // Ensure restricted modal is available
        const modal = getOrCreateModal('modal-delete-restricted');
        if (modal) {
          deleteRestrictedModal = modal;
          modal.show();
        } else {
          console.error('Could not find or initialize restricted delete modal');
        }
      } else {
        const errorMessage = errors?.message || 'Ошибка при удалении исполнителя'
        toast.error(errorMessage)
      }
    },
  });
}
</script>

<template>

  <Head title="Исполнители" />
  <ContentLayout>

    <template #TopButtons>
      <div class="d-flex w-100">
        <div class="p-1 flex-fill">
          <input v-model="search" type="text" class="form-control" autocomplete="off"
            placeholder="Поиск по имени или e-mail..." @keyup.enter="onSearch" />
        </div>
        <div class="p-1">
          <button class="btn btn-primary" @click="openCreate">
            Новый исполнитель
          </button>
        </div>
      </div>
    </template>

    <div class="card">
      <div class="table-responsive">
        <table class="table table-vcenter">
          <thead>
            <tr>
              <th>ФИО</th>
              <th>E-mail</th>
              <th>Контакты исполнителя</th>
              <th>Заблокирован</th>
              <th class="w-1"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="u in props.users" :key="u.id">
              <td>
                <div class="fw-bold">{{ u.name }}</div>
              </td>
              <td>{{ u.email }}</td>
              <td>
                <div v-if="u.executor_contacts" class="text-secondary" style="white-space: pre-line; max-width: 260px;">
                  {{ u.executor_contacts }}
                </div>
                <div v-else class="text-muted">—</div>
              </td>
              <td>
                <span :class="['badge', 'text-light', u.is_blocked ? 'bg-red' : 'bg-green']">{{ u.is_blocked ? 'Да' :
                  'Нет'
                  }}</span>
              </td>
              <td class="text-end">
                <div class="btn-list flex-nowrap">
                  <button class="btn btn-sm" @click="openEdit(u)"><i class="ti ti-edit"></i> Изменить</button>
                  <button class="btn btn-sm btn-danger" @click="openDelete(u)"><i class="ti ti-trash"></i>
                    Удалить</button>
                </div>
              </td>
            </tr>
            <tr v-if="props.users.length === 0">
              <td colspan="5" class="text-center text-secondary py-5">Нет данных</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </ContentLayout>

  <!-- Create Modal -->
  <div class="modal fade" id="modal-create-executor" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
    aria-labelledby="createExecutorLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="createExecutorLabel">Новый исполнитель</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-2">
            <div class="col-md-4">
              <label class="form-label">Фамилия</label>
              <input v-model="createForm.last_name" type="text" class="form-control" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Имя</label>
              <input v-model="createForm.first_name" type="text" class="form-control" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Отчество</label>
              <input v-model="createForm.middle_name" type="text" class="form-control" />
            </div>
            <div class="col-md-6">
              <label class="form-label">E-mail</label>
              <input v-model="createForm.email" type="email" class="form-control" />
            </div>
            <div class="col-md-6">
              <label class="form-label">Пароль</label>
              <div class="input-group">
                <input v-model="createForm.password" type="text" class="form-control" @input="passwordError = ''" />
                <button type="button" class="btn btn-outline" @click="generatePassword('create')">Сгенерировать</button>
              </div>
              <div v-if="passwordError" class="text-danger mt-1">{{ passwordError }}</div>
            </div>
            <div class="col-md-12">
              <label class="form-label">Контакты исполнителя</label>
              <textarea v-model="createForm.executor_contacts" class="form-control" rows="3"
                placeholder="Телеграм, телефон, ссылки и т.п. (необязательно)"></textarea>
            </div>
            <div class="col-md-12">
              <label class="form-check">
                <input class="form-check-input" type="checkbox" v-model="createForm.is_blocked" />
                <span class="form-check-label">Заблокирован</span>
              </label>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn me-auto" data-bs-dismiss="modal">Закрыть</button>
          <button type="button" class="btn btn-primary" @click="submitCreate"
            :disabled="createForm.processing || createInvalid">
            Создать
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Edit Modal -->
  <div class="modal fade" id="modal-edit-executor" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
    aria-labelledby="editExecutorLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="editExecutorLabel">Изменить исполнителя</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-2">
            <div class="col-md-4">
              <label class="form-label">Фамилия</label>
              <input v-model="editForm.last_name" type="text" class="form-control" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Имя</label>
              <input v-model="editForm.first_name" type="text" class="form-control" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Отчество</label>
              <input v-model="editForm.middle_name" type="text" class="form-control" />
            </div>
            <div class="col-md-6">
              <label class="form-label">E-mail</label>
              <input v-model="editForm.email" type="email" class="form-control" />
            </div>
            <div class="col-md-6">
              <label class="form-label">Пароль</label>
              <div class="input-group">
                <input v-model="editForm.password" type="text" class="form-control" @input="passwordError = ''" />
                <button type="button" class="btn btn-outline" @click="generatePassword('edit')">Сгенерировать</button>
              </div>
              <div v-if="passwordError" class="text-danger mt-1">{{ passwordError }}</div>
            </div>
            <div class="col-md-12">
              <label class="form-label">Контакты исполнителя</label>
              <textarea v-model="editForm.executor_contacts" class="form-control" rows="3"
                placeholder="Телеграм, телефон, ссылки и т.п. (необязательно)"></textarea>
            </div>
            <div class="col-md-12">
              <label class="form-check">
                <input class="form-check-input" type="checkbox" v-model="editForm.is_blocked" />
                <span class="form-check-label">Заблокирован</span>
              </label>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn me-auto" data-bs-dismiss="modal">Закрыть</button>
          <button type="button" class="btn btn-primary" @click="submitEdit"
            :disabled="editForm.processing || editInvalid">Сохранить</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Delete Modal -->
  <div class="modal fade" id="modal-delete-executor" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
    aria-labelledby="deleteExecutorLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-body text-center py-4">
          <i class="ti ti-alert-triangle icon mb-2 text-warning" style="font-size: 2rem"></i>
          <p>Удалить пользователя «{{ selected?.name }}»?</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn me-auto" data-bs-dismiss="modal">Закрыть</button>
          <button type="button" class="btn btn-danger" @click="submitDelete">Удалить</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Delete Restricted Modal -->
  <div class="modal fade" id="modal-delete-restricted" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
    aria-labelledby="deleteRestrictedLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-body text-center py-4">
          <i class="ti ti-alert-triangle icon mb-2 text-warning" style="font-size: 2rem"></i>
          <p>Невозможно удалить пользователя, так как у него есть назначенные задачи.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-primary w-100" data-bs-dismiss="modal">OK</button>
        </div>
      </div>
    </div>
  </div>

</template>

<style>
/* Ensure modals always stack above backdrops (handles custom theme overrides) */
.modal-backdrop.show {
  z-index: 1050;
}

.modal.show {
  z-index: 1000000000;
}


/* If your theme raises backdrops higher, uncomment stronger values below */
/* .modal-backdrop.show { z-index: 2000; }
.modal.show { z-index: 2010; } */
</style>
