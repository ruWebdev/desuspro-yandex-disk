<script setup>
import { ref, onMounted, onUnmounted, computed, watch, nextTick } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';

// Пользователь и аутентификация
const page = usePage();
const user = computed(() => page.props?.auth?.user || null);
const roles = computed(() => (user.value?.roles || []).map(r => (typeof r === 'string' ? r : r.name)));
const isAdmin = computed(() => roles.value.some(r => ['Administrator', 'admin'].includes(r)));
const isManager = computed(() => roles.value.some(r => ['Manager', 'manager'].includes(r)));
const isPerformer = computed(() => roles.value.some(r => ['Performer', 'performer'].includes(r)));

// Состояние UI
const isDesktop = ref(window.innerWidth >= 992);
const searchFocused = ref(false);
const skinMode = ref(localStorage.getItem('skin-mode') || 'light');

// Установка skin mode
const setSkinMode = (mode) => {
  skinMode.value = mode;
  const html = document.documentElement;

  if (mode === 'dark') {
    html.setAttribute('data-skin', 'dark');
    localStorage.setItem('skin-mode', 'dark');
  } else {
    html.removeAttribute('data-skin');
    localStorage.removeItem('skin-mode');
  }
};

// Инициализация при монтировании
onMounted(() => {
  // Применить сохраненные настройки
  setSkinMode(skinMode.value);
});

// Очистка при размонтировании
onUnmounted(() => {
  // Ничего не нужно очищать
});

// Наблюдать за изменениями настроек
watch([skinMode], () => {
  setSkinMode(skinMode.value);
});
</script>

<template>
  <div class="header-main px-3 px-lg-4">

    <!-- Навигационное меню в виде Bootstrap pills -->
    <ul class="nav nav-pills me-3" v-if="user">
      <!-- Общий пункт для всех авторизованных пользователей -->
      <li class="nav-item">
        <Link :href="route('dashboard')"
          :class="{ 'active': $page.component.startsWith('Admin/Dashboard') || $page.component.startsWith('Manager/Dashboard') || $page.component.startsWith('Performer/Dashboard') }"
          class="nav-link">
        <i class="ri-dashboard-line"></i> Главная
        </Link>
      </li>

      <!-- Меню для Администратора -->
      <template v-if="isAdmin">
        <li class="nav-item">
          <Link :href="route('admin.brands.index')" :class="{ 'active': $page.url.startsWith('/admin/brands') }"
            class="nav-link">
          <i class="ri-flag-line"></i> Бренды
          </Link>
        </li>
        <li class="nav-item">
          <Link :href="route('users.managers.index')" :class="{ 'active': $page.url.startsWith('/users/managers') }"
            class="nav-link">
          <i class="ri-user-settings-line"></i> Менеджеры
          </Link>
        </li>
        <li class="nav-item">
          <Link :href="route('users.executors.index')" :class="{ 'active': $page.url.startsWith('/users/executors') }"
            class="nav-link">
          <i class="ri-user-star-line"></i> Исполнители
          </Link>
        </li>
        <li class="nav-item">
          <Link :href="route('task_types.index')" :class="{ 'active': $page.url.startsWith('/task-types') }"
            class="nav-link">
          <i class="ri-price-tag-3-line"></i> Типы задач
          </Link>
        </li>
        <li class="nav-item">
          <Link :href="route('admin.yandex.token')" :class="{ 'active': $page.url.startsWith('/admin/yd_token') }"
            class="nav-link">
          <i class="ri-cloud-line"></i> Токен ЯД
          </Link>
        </li>
      </template>

      <!-- Меню для Менеджера -->
      <template v-else-if="isManager">
        <li class="nav-item">
          <Link :href="route('manager.brands.index')" :class="{ 'active': $page.url.startsWith('/manager/brands') }"
            class="nav-link">
          <i class="ri-flag-line"></i> Бренды
          </Link>
        </li>
        <li class="nav-item">
          <Link :href="route('users.executors.index')" :class="{ 'active': $page.url.startsWith('/users/executors') }"
            class="nav-link">
          <i class="ri-user-star-line"></i> Исполнители
          </Link>
        </li>
      </template>

      <!-- Меню для Исполнителя -->
      <template v-else-if="isPerformer">
        <li class="nav-item">
          <Link :href="route('performer.tasks')" :class="{ 'active': $page.url.startsWith('/my-tasks') }"
            class="nav-link">
          <i class="ri-task-line"></i> Задачи
          </Link>
        </li>
      </template>
    </ul>

    <div class="form-search me-auto" :class="{ onfocus: searchFocused }">
      <input type="text" class="form-control" placeholder="Поиск" @focus="searchFocused = true"
        @blur="searchFocused = false">
      <i class="ri-search-line"></i>
    </div>

    <div class="dropdown dropdown-profile ms-3 ms-xl-4">
      <a href="#" class="dropdown-link" data-bs-toggle="dropdown" aria-expanded="false">
        <div class="avatar online"><img src="/assets/img/user.webp" alt=""></div>
      </a>
      <div class="dropdown-menu dropdown-menu-end mt-10-f">
        <div class="dropdown-menu-body">
          <div class="avatar avatar-xl online mb-3"><img src="/assets/img/user.webp" alt=""></div>
          <h5 class="mb-1 text-dark fw-semibold">{{ user?.name || 'User' }}</h5>
          <p class="fs-sm text-secondary">{{ user?.email || 'User' }}</p>

          <nav class="nav">
            <a href="/profile" class="dropdown-item"><i class="ri-edit-2-line"></i> Профиль</a>
            <a href="#" @click.prevent="$inertia.post(route('logout'))" class="dropdown-item">
              <i class="ri-logout-box-r-line"></i> Выход
            </a>
          </nav>
        </div>
      </div>
    </div>
  </div>

  <div class="main main-app p-3 p-lg-4">
    <slot />
  </div>
</template>

<style scoped>
/* Стили для навигационных pills */
.nav-pills .nav-link {
  border-radius: 0.375rem;
  margin-right: 0.25rem;
  padding: 0.5rem 0.75rem;
  font-size: 0.875rem;
  white-space: nowrap;
}

.nav-pills .nav-link i {
  margin-right: 0.25rem;
}

/* Адаптивные стили для pills */
@media (max-width: 991.98px) {
  .nav-pills {
    flex-wrap: wrap;
    margin-bottom: 0.5rem;
  }

  .nav-pills .nav-item {
    margin-bottom: 0.25rem;
  }

  .nav-pills .nav-link {
    font-size: 0.8rem;
    padding: 0.375rem 0.5rem;
  }
}

@media (max-width: 575.98px) {
  .nav-pills .nav-link span:not(.sr-only) {
    display: none;
  }

  .nav-pills .nav-link {
    padding: 0.375rem;
    min-width: 2.5rem;
    justify-content: center;
  }
}

/* Стили для анимаций и состояний */
.form-search.onfocus {
  border-color: var(--primary);
  box-shadow: 0 0 0 0.2rem rgba(var(--primary-rgb), 0.25);
}
</style>