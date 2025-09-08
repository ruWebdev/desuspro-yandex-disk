<script setup>
import DashByteLayout from '@/Layouts/DashByteLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
  mustVerifyEmail: { type: Boolean },
  status: { type: String },
});

const user = usePage().props.auth.user;
const roles = computed(() => (user?.roles || []).map(r => (typeof r === 'string' ? r : r.name)));
const isPhotographer = computed(() => roles.value.includes('Photographer'));
const isPhotoEditor = computed(() => roles.value.includes('PhotoEditor'));
const isDeletionRestricted = computed(() => isPhotographer.value || isPhotoEditor.value);

// Profile form
const profileForm = useForm({
  name: user.name ?? '',
  last_name: user.last_name ?? '',
  first_name: user.first_name ?? '',
  middle_name: user.middle_name ?? '',
  email: user.email ?? '',
});

function submitProfile() {
  profileForm.patch(route('profile.update'));
}

// Password form
const currentPasswordInput = ref(null);
const newPasswordInput = ref(null);
const passwordForm = useForm({
  current_password: '',
  password: '',
  password_confirmation: '',
});

function submitPassword() {
  passwordForm.put(route('password.update'), {
    preserveScroll: true,
    onSuccess: () => passwordForm.reset(),
    onError: () => {
      if (passwordForm.errors.password) {
        passwordForm.reset('password', 'password_confirmation');
        newPasswordInput.value?.focus?.();
      }
      if (passwordForm.errors.current_password) {
        passwordForm.reset('current_password');
        currentPasswordInput.value?.focus?.();
      }
    },
  });
}

// Delete form
const showDeleteModal = ref(false);
const deletePasswordInput = ref(null);
const deleteForm = useForm({ password: '' });

function confirmDelete() {
  showDeleteModal.value = true;
  setTimeout(() => deletePasswordInput.value?.focus?.(), 0);
}

function closeDelete() {
  showDeleteModal.value = false;
  deleteForm.clearErrors();
  deleteForm.reset();
}

function submitDelete() {
  deleteForm.delete(route('profile.destroy'), {
    preserveScroll: true,
    onSuccess: () => closeDelete(),
    onError: () => deletePasswordInput.value?.focus?.(),
    onFinish: () => deleteForm.reset(),
  });
}
</script>

<template>

  <Head title="Профиль" />

  <DashByteLayout>
    <template #header>
      Профиль
    </template>

    <div class="row g-3">
      <!-- Deletion forbidden notice -->
      <div v-if="props.status === 'forbidden-delete'" class="col-12">
        <div class="alert alert-warning" role="alert">
          Удаление аккаунта недоступно для вашей роли.
        </div>
      </div>
      <!-- Profile info -->
      <div class="col-12 col-lg-6">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title mb-0">Личная информация</h3>
          </div>
          <div class="card-body">
            <form @submit.prevent="submitProfile">
              <div class="mb-3">
                <label class="form-label" for="name">Отображаемое имя</label>
                <input id="name" type="text" class="form-control" v-model="profileForm.name" autocomplete="name" />
                <div v-if="profileForm.errors.name" class="text-danger small mt-1">{{ profileForm.errors.name }}</div>
              </div>

              <div class="row g-2">
                <div class="col-12 col-sm-4">
                  <label class="form-label" for="last_name">Фамилия</label>
                  <input id="last_name" type="text" class="form-control" v-model="profileForm.last_name"
                    autocomplete="family-name" />
                  <div v-if="profileForm.errors.last_name" class="text-danger small mt-1">{{
                    profileForm.errors.last_name }}
                  </div>
                </div>
                <div class="col-12 col-sm-4">
                  <label class="form-label" for="first_name">Имя</label>
                  <input id="first_name" type="text" class="form-control" v-model="profileForm.first_name"
                    autocomplete="given-name" />
                  <div v-if="profileForm.errors.first_name" class="text-danger small mt-1">{{
                    profileForm.errors.first_name
                  }}</div>
                </div>
                <div class="col-12 col-sm-4">
                  <label class="form-label" for="middle_name">Отчество</label>
                  <input id="middle_name" type="text" class="form-control" v-model="profileForm.middle_name"
                    autocomplete="additional-name" />
                  <div v-if="profileForm.errors.middle_name" class="text-danger small mt-1">{{
                    profileForm.errors.middle_name }}</div>
                </div>
              </div>

              <div class="mt-3">
                <label class="form-label" for="email">Email</label>
                <input id="email" type="email" class="form-control" v-model="profileForm.email"
                  autocomplete="username" />
                <div v-if="profileForm.errors.email" class="text-danger small mt-1">{{ profileForm.errors.email }}</div>
              </div>

              <div v-if="props.mustVerifyEmail && user.email_verified_at === null" class="mt-2">
                <div class="text-muted small">Ваш email не подтверждён.</div>
                <Link :href="route('verification.send')" method="post" as="button" class="btn btn-link px-0">Отправить
                письмо с подтверждением</Link>
                <div v-show="props.status === 'verification-link-sent'" class="text-success small">Ссылка отправлена.
                </div>
              </div>

              <div class="d-flex align-items-center gap-2 mt-3">
                <button type="submit" class="btn btn-primary" :disabled="profileForm.processing">
                  <span v-if="profileForm.processing" class="spinner-border spinner-border-sm me-2" />
                  Сохранить
                </button>
                <span v-if="profileForm.recentlySuccessful" class="text-muted small">Сохранено.</span>
              </div>

              <div class="row mt-4 g-2">
                <div class="col-12 col-sm-6">
                  <div class="text-muted small">Статус доступа</div>
                  <span :class="['badge', user.is_blocked ? 'bg-red' : 'bg-green']">{{ user.is_blocked ? 'Заблокирован'
                    :
                    'Разрешен' }}</span>
                </div>
                <div class="col-12 col-sm-6">
                  <div class="text-muted small">Аккаунт создан</div>
                  <div class="small">{{ new Date(user.created_at).toLocaleString() }}</div>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Password -->
      <div class="col-12 col-lg-6">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title mb-0">Смена пароля</h3>
          </div>
          <div class="card-body">
            <form @submit.prevent="submitPassword">
              <div class="mb-3">
                <label class="form-label" for="current_password">Текущий пароль</label>
                <input id="current_password" ref="currentPasswordInput" type="password" class="form-control"
                  v-model="passwordForm.current_password" autocomplete="current-password" />
                <div v-if="passwordForm.errors.current_password" class="text-danger small mt-1">{{
                  passwordForm.errors.current_password }}</div>
              </div>
              <div class="mb-3">
                <label class="form-label" for="password">Новый пароль</label>
                <input id="password" ref="newPasswordInput" type="password" class="form-control"
                  v-model="passwordForm.password" autocomplete="new-password" />
                <div v-if="passwordForm.errors.password" class="text-danger small mt-1">{{ passwordForm.errors.password
                }}
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label" for="password_confirmation">Подтверждение пароля</label>
                <input id="password_confirmation" type="password" class="form-control"
                  v-model="passwordForm.password_confirmation" autocomplete="new-password" />
                <div v-if="passwordForm.errors.password_confirmation" class="text-danger small mt-1">{{
                  passwordForm.errors.password_confirmation }}</div>
              </div>
              <div class="d-flex align-items-center gap-2">
                <button type="submit" class="btn btn-primary" :disabled="passwordForm.processing">
                  <span v-if="passwordForm.processing" class="spinner-border spinner-border-sm me-2" />
                  Сохранить
                </button>
                <span v-if="passwordForm.recentlySuccessful" class="text-muted small">Сохранено.</span>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Delete account (hidden for Photographer/PhotoEditor) -->
      <div v-if="!isDeletionRestricted" class="col-12">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title mb-0 text-danger">Удаление аккаунта</h3>
          </div>
          <div class="card-body">
            <button class="btn btn-danger" @click="confirmDelete">Удалить аккаунт</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete modal -->
    <teleport to="body">
      <div v-if="showDeleteModal && !isDeletionRestricted" class="modal modal-blur fade show d-block" tabindex="-1"
        role="dialog" style="z-index: 1050;">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Удалить аккаунт</h5>
              <button type="button" class="btn-close" aria-label="Close" @click="closeDelete"></button>
            </div>
            <div class="modal-body">
              <p class="mb-2">Вы уверены, что хотите удалить аккаунт? Это действие необратимо.</p>
              <label class="form-label" for="delete_password">Пароль</label>
              <input id="delete_password" ref="deletePasswordInput" v-model="deleteForm.password" type="password"
                class="form-control" placeholder="Пароль" />
              <div v-if="deleteForm.errors.password" class="text-danger small mt-1">{{ deleteForm.errors.password }}
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn me-auto" @click="closeDelete">Отмена</button>
              <button type="button" class="btn btn-danger" :disabled="deleteForm.processing" @click="submitDelete">
                <span v-if="deleteForm.processing" class="spinner-border spinner-border-sm me-2" />
                Удалить аккаунт
              </button>
            </div>
          </div>
        </div>
      </div>
    </teleport>
  </DashByteLayout>
</template>
