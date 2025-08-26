<script setup>
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const navOpen = ref(false);
const page = usePage();
const user = computed(() => page.props?.auth?.user || null);

// Roles from shared props (expects user.roles as array of role names)
const roles = computed(() => (user.value?.roles || []).map(r => (typeof r === 'string' ? r : r.name)));
const isManager = computed(() => roles.value.includes('Manager'));
const isPhotographer = computed(() => roles.value.includes('Photographer'));
const isPhotoEditor = computed(() => roles.value.includes('PhotoEditor'));
</script>

<template>
  <div class="page">
    <!-- Top dark header -->
    <header class="navbar navbar-expand-md d-print-none" data-bs-theme="dark">
      <div class="container-fluid">
        <h1 class="navbar-brand navbar-brand-autodark d-none-navbar-horizontal pe-0 pe-md-3 m-0">
          <Link :href="route('dashboard')" class="text-reset text-decoration-none">{{ page.props?.appName || 'App' }}</Link>
        </h1>
        <!-- Navbar toggler controls the light menu below -->
        <button class="navbar-toggler" type="button" @click="navOpen = !navOpen" aria-label="Переключить навигацию" data-bs-toggle="collapse" data-bs-target="#navbar-menu" :aria-expanded="navOpen.toString()" aria-controls="navbar-menu">
          <span class="navbar-toggler-icon"></span>
        </button>
        <!-- Right: user dropdown/actions in top dark bar -->
        <div class="navbar-nav ms-auto align-items-center">
          <div v-if="user" class="nav-item dropdown">
            <a href="#" class="nav-link d-flex lh-1 text-reset p-0" data-bs-toggle="dropdown" aria-label="Открыть меню пользователя">
              <span class="avatar avatar-sm">{{ user.name?.charAt(0).toUpperCase() }}</span>
              <div class="d-none d-xl-block ps-2">
                <div>{{ user.name }}</div>
                <div class="mt-1 small text-secondary">{{ user.email }}</div>
              </div>
            </a>
            <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow" data-bs-theme="light">
              <Link class="dropdown-item" :href="route('profile.edit')">Профиль</Link>
              <div class="dropdown-divider"></div>
              <Link class="dropdown-item" :href="route('logout')" method="post" as="button">Выйти</Link>
            </div>
          </div>
        </div>
      </div>
    </header>

    <!-- Bottom light menu header -->
    <header class="navbar-expand-md">
      <div class="collapse navbar-collapse" id="navbar-menu" :class="{ show: navOpen }">
        <div class="navbar">
          <div class="container-fluid">
            <div class="row flex-column flex-md-row flex-fill align-items-center">
              <div class="col">
                <ul class="navbar-nav">
                  <template v-if="isManager">
                    <li class="nav-item">
                      <Link :href="route('dashboard')" class="nav-link" :class="{ active: route().current('dashboard') }">
                        <span class="nav-link-title">Панель</span>
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link :href="route('brands.index')" class="nav-link" :class="{ active: route().current('brands.*') }">
                        <span class="nav-link-title">Бренды</span>
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link :href="route('tasks.all')" class="nav-link" :class="{ active: route().current('tasks.all') }">
                        <span class="nav-link-title">Все задания</span>
                      </Link>
                    </li>
                    <li class="nav-item dropdown" :class="{ active: route().current('users.photographers.index') || route().current('users.photo_editors.index') }">
                      <a href="#navbar-users" class="nav-link dropdown-toggle" data-bs-toggle="dropdown" role="button" aria-expanded="false">
                        <span class="nav-link-title">Пользователи</span>
                      </a>
                      <div class="dropdown-menu">
                        <Link :href="route('users.photographers.index')" class="dropdown-item" :class="{ active: route().current('users.photographers.index') }">
                          Фотографы
                        </Link>
                        <Link :href="route('users.photo_editors.index')" class="dropdown-item" :class="{ active: route().current('users.photo_editors.index') }">
                          Фоторедакторы
                        </Link>
                      </div>
                    </li>
                  </template>
                  <template v-else>
                    <li class="nav-item">
                      <Link :href="isPhotographer ? route('photographer.tasks') : route('photo_editor.tasks')" class="nav-link"
                        :class="{ active: isPhotographer ? route().current('photographer.tasks') : route().current('photo_editor.tasks') }">
                        <span class="nav-link-title">Задания</span>
                      </Link>
                    </li>
                  </template>
                </ul>
              </div>
              <div class="col-auto ms-md-auto d-none d-md-flex">
                <!-- Optional right-side items for the light menu bar -->
              </div>
            </div>
          </div>
        </div>
      </div>
    </header>

    <div class="page-wrapper">
      <div v-if="$slots.header" class="page-header d-print-none">
        <div class="container-fluid">
          <div class="row align-items-center">
            <div class="col">
              <div class="page-pretitle">Обзор</div>
              <h2 class="page-title">
                <slot name="header" />
              </h2>
            </div>
          </div>
        </div>
      </div>

      <div class="page-body">
        <div class="container-fluid">
          <slot />
        </div>
      </div>
    </div>
  </div>
</template>
