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
const isSidebarOpen = ref(isDesktop.value);
const searchFocused = ref(false);
const sidebarFooterMenuOpen = ref(false);
const skinMode = ref(localStorage.getItem('skin-mode') || 'light');
const sidebarSkin = ref(localStorage.getItem('sidebar-skin') || 'default');

// Навигационные группы - всегда развернуты
const navGroups = ref({
  dashboard: true,  // Всегда развернуто
  apps: true,      // Всегда развернуто
  pages: true,     // Всегда развернуто
  components: true // Всегда развернуто
});

// Переключение боковой панели
const toggleSidebar = (event = null, forceClose = false) => {
  // Для кнопки preventDefault не требуется, просто останавливаем всплытие,
  // чтобы не сработал обработчик клика по документу
  if (event && typeof event.stopPropagation === 'function') {
    event.stopPropagation();
  }

  isSidebarOpen.value = forceClose ? false : !isSidebarOpen.value;
  updateBodyClasses();
};

// Переключение навигационной группы
// Убрали переключение групп, так как они всегда развернуты
const toggleNavGroup = () => { };

// Переключение меню футера
const toggleSidebarFooterMenu = () => {
  sidebarFooterMenuOpen.value = !sidebarFooterMenuOpen.value;
};

// Обновление классов body
const updateBodyClasses = () => {
  const body = document.body;
  const isDesktopView = window.innerWidth >= 992;

  // Обновляем состояние isDesktop при изменении размера окна
  isDesktop.value = isDesktopView;

  // Сбросить все классы
  body.classList.remove('sidebar-show', 'sidebar-hide', 'sidebar-mobile-show');

  // Управление классами в зависимости от состояния
  if (isSidebarOpen.value) {
    body.classList.add('sidebar-show');
  } else if (isDesktopView) {
    body.classList.add('sidebar-hide');
  }
};

// Обработка изменения размера окна
const handleResize = () => {
  const wasDesktop = isDesktop.value;
  isDesktop.value = window.innerWidth >= 992;

  // При переключении между мобильной и десктопной версией
  if (wasDesktop !== isDesktop.value) {
    if (isDesktop.value) {
      // При переходе на десктоп, всегда показываем сайдбар
      isSidebarOpen.value = true;
    }
    updateBodyClasses();
  }
};

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

// Установка sidebar skin
const setSidebarSkin = (skin) => {
  sidebarSkin.value = skin;
  localStorage.setItem('sidebar-skin', skin);
};

// Получение класса для сайдбара
const getSidebarClass = computed(() => {
  if (sidebarSkin.value === 'default') {
    return 'sidebar';
  }
  return `sidebar sidebar-${sidebarSkin.value}`;
});

// Обработчик клика вне элементов
const handleClickOutside = (event) => {
  const clickedOnMenuToggle = !!event.target.closest('.menu-link');
  const clickedInsideSidebar = !!event.target.closest('.sidebar');

  // Пропускаем обработку, если клик по кнопке меню или внутри сайдбара
  if (clickedOnMenuToggle || clickedInsideSidebar) {
    return;
  }

  // Закрываем сайдбар при клике вне его
  if (isSidebarOpen.value) {
    isSidebarOpen.value = false;
    updateBodyClasses();
  }

  // Закрыть меню футера при клике вне
  if (!event.target.closest('.sidebar-footer') && sidebarFooterMenuOpen.value) {
    sidebarFooterMenuOpen.value = false;
  }

  // Закрыть навигационные группы при клике вне
  if (!event.target.closest('.nav-item.has-sub') && !event.target.closest('.nav-label')) {
    Object.keys(navGroups.value).forEach(key => {
      navGroups.value[key] = false;
    });
  }
};

// Инициализация при монтировании
onMounted(() => {
  // Применить сохраненные настройки
  setSkinMode(skinMode.value);

  // Слушатель навигации Inertia
  router.on('navigate', () => {
    // Удаляем класс sidebar-show при навигации
    document.body.classList.remove('sidebar-show');
    isSidebarOpen.value = false;
  });

  // Добавить backdrop элемент
  const backdrop = document.createElement('div');
  backdrop.className = 'main-backdrop';
  document.body.appendChild(backdrop);

  // Обработчик клика по backdrop
  backdrop.addEventListener('click', (event) => {
    event.stopPropagation();
    event.preventDefault();
    isSidebarOpen.value = false;
    sidebarFooterMenuOpen.value = false;
    updateBodyClasses();
  });

  // Слушатели событий
  window.addEventListener('resize', handleResize);
  document.addEventListener('click', handleClickOutside);

  // Синхронизируем классы body с текущим состоянием сайдбара
  updateBodyClasses();

  // Инициализация Perfect Scrollbar если доступен
  if (typeof PerfectScrollbar !== 'undefined') {
    new PerfectScrollbar('#sidebarMenu', { suppressScrollX: true });
  }
});

// Очистка при размонтировании
onUnmounted(() => {
  window.removeEventListener('resize', handleResize);
  document.removeEventListener('click', handleClickOutside);

  // Удалить backdrop
  const backdrop = document.querySelector('.main-backdrop');
  if (backdrop) {
    backdrop.remove();
  }
});

// Наблюдать за изменениями настроек
watch([skinMode, sidebarSkin], () => {
  setSkinMode(skinMode.value);
});
</script>

<template>
  <div :class="getSidebarClass">
    <div class="sidebar-header">
      <a href="/" class="sidebar-logo">админпанель</a>
    </div>

    <div id="sidebarMenu" class="sidebar-body">
      <div class="nav-group show" :class="{ show: navGroups.dashboard }">
        <a href="#" class="nav-label" @click.prevent="toggleNavGroup('dashboard')">Меню</a>
        <ul class="nav nav-sidebar">
          <!-- Общий пункт для всех авторизованных пользователей -->
          <li class="nav-item" v-if="user">
            <Link :href="route('dashboard')"
              :class="{ 'active': $page.component.startsWith('Admin/Dashboard') || $page.component.startsWith('Manager/Dashboard') || $page.component.startsWith('Performer/Dashboard') }"
              class="nav-link" @click="toggleSidebar($event, true)">
            <i class="ri-dashboard-line"></i> <span>Главная</span>
            </Link>
          </li>

          <!-- Меню для Администратора -->
          <template v-if="isAdmin">
            <li class="nav-item">
              <Link :href="route('admin.brands.index')" :class="{ 'active': $page.url.startsWith('/admin/brands') }"
                class="nav-link" @click="toggleSidebar($event, true)">
              <i class="ri-flag-line"></i> <span>Бренды</span>
              </Link>
            </li>
            <li class="nav-item">
              <Link :href="route('users.managers.index')" :class="{ 'active': $page.url.startsWith('/users/managers') }"
                class="nav-link" @click="toggleSidebar($event, true)">
              <i class="ri-user-settings-line"></i> <span>Менеджеры</span>
              </Link>
            </li>
            <li class="nav-item">
              <Link :href="route('users.executors.index')"
                :class="{ 'active': $page.url.startsWith('/users/executors') }" class="nav-link"
                @click="toggleSidebar($event, true)">
              <i class="ri-user-star-line"></i> <span>Исполнители</span>
              </Link>
            </li>
            <li class="nav-item">
              <Link :href="route('task_types.index')" :class="{ 'active': $page.url.startsWith('/task-types') }"
                class="nav-link" @click="toggleSidebar($event, true)">
              <i class="ri-price-tag-3-line"></i> <span>Типы задач</span>
              </Link>
            </li>
            <li class="nav-item">
              <Link :href="route('admin.yandex.token')" :class="{ 'active': $page.url.startsWith('/admin/yd_token') }"
                class="nav-link" @click="toggleSidebar($event, true)">
              <i class="ri-cloud-line"></i> <span>Токен Яндекс.Диска</span>
              </Link>
            </li>
          </template>

          <!-- Меню для Менеджера -->
          <template v-else-if="isManager">
            <li class="nav-item">
              <Link :href="route('manager.brands.index')" :class="{ 'active': $page.url.startsWith('/manager/brands') }"
                class="nav-link" @click="toggleSidebar($event, true)">
              <i class="ri-flag-line"></i> <span>Бренды</span>
              </Link>
            </li>
            <li class="nav-item">
              <Link :href="route('users.executors.index')"
                :class="{ 'active': $page.url.startsWith('/users/executors') }" class="nav-link"
                @click="toggleSidebar($event, true)">
              <i class="ri-user-star-line"></i> <span>Исполнители</span>
              </Link>
            </li>
          </template>

          <!-- Меню для Исполнителя -->
          <template v-else-if="isPerformer">
            <li class="nav-item">
              <Link :href="route('performer.tasks')" :class="{ 'active': $page.url.startsWith('/my-tasks') }"
                class="nav-link" @click="toggleSidebar($event, true)">
              <i class="ri-task-line"></i> <span>Задачи</span>
              </Link>
            </li>
          </template>
        </ul>
      </div>
    </div>

    <div class="sidebar-footer" :class="{ 'footer-menu-show': sidebarFooterMenuOpen }">
      <div class="sidebar-footer-top">
        <div class="sidebar-footer-thumb">
          <img src="/assets/img/user.webp" alt="">
        </div>
        <div class="sidebar-footer-body">
          <h6><a href="/profile">{{ user?.name || 'User' }}</a></h6>
          <p>{{ user?.email || 'User' }}</p>
        </div>
        <a href="#" class="dropdown-link" @click.prevent="toggleSidebarFooterMenu">
          <i class="ri-arrow-down-s-line"></i>
        </a>
      </div>

      <div class="sidebar-footer-menu" v-if="sidebarFooterMenuOpen">
        <nav class="nav">
          <a href="/profile"><i class="ri-edit-2-line"></i> Профиль</a>
          <a href="#" @click.prevent="$inertia.post(route('logout'))">
            <i class="ri-logout-box-r-line"></i> Выход
          </a>
        </nav>
      </div>
    </div>
  </div>

  <div class="header-main px-3 px-lg-4">
    <button type="button" class="menu-link me-3 me-lg-4" @click.stop="toggleSidebar"
      style="background: none; border: none; padding: 0;">
      <i class="ri-menu-2-fill"></i>
    </button>

    <div class="form-search me-auto" :class="{ onfocus: searchFocused }">
      <input type="text" class="form-control" placeholder="Поиск" @focus="searchFocused = true"
        @blur="searchFocused = false">
      <i class="ri-search-line"></i>
    </div>

    <div class="dropdown dropdown-profile ms-3 ms-xl-4">
      <a href="#" class="dropdown-link">
        <div class="avatar online"><img src="/assets/img/user.webp" alt=""></div>
      </a>
      <div class="dropdown-menu dropdown-menu-end mt-10-f">
        <div class="dropdown-menu-body">
          <div class="avatar avatar-xl online mb-3"><img src="/assets/img/user.webp" alt=""></div>
          <h5 class="mb-1 text-dark fw-semibold">{{ user?.name || 'User' }}</h5>
          <p class="fs-sm text-secondary">{{ user?.email || 'User' }}</p>

          <nav class="nav">
            <a href="/profile"><i class="ri-edit-2-line"></i> Профиль</a>
            <a href="#" @click.prevent="$inertia.post(route('logout'))">
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
/* Стили для анимаций и состояний */
.sidebar-footer-menu {
  transition: all 0.3s ease;
}

.form-search.onfocus {
  border-color: var(--primary);
  box-shadow: 0 0 0 0.2rem rgba(var(--primary-rgb), 0.25);
}

/* Адаптивные стили */
@media (max-width: 991.98px) {
  .sidebar-show .main-backdrop {
    display: block;
    opacity: 1;
  }
}

.main-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.5);
  z-index: 1040;
  display: none;
}

body.sidebar-show .main-backdrop {
  display: block;
}
</style>