<script setup>

import { computed } from 'vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link, usePage } from '@inertiajs/vue3';

// Пользователь и аутентификация
const page = usePage();
const user = computed(() => page.props?.auth?.user || null);
const roles = computed(() => (user.value?.roles || []).map(r => (typeof r === 'string' ? r : r.name)));
const isAdmin = computed(() => roles.value.some(r => ['Administrator', 'admin'].includes(r)));
const isManager = computed(() => roles.value.some(r => ['Manager', 'manager'].includes(r)));
const isPerformer = computed(() => roles.value.some(r => ['Performer', 'performer'].includes(r)));

const props = defineProps({
    currentPage: {
        type: String,
        defalut: null,
    },
});

const currentPath = computed(() => {
    props.currentPage.replace(/^http?:\/\//, '');
})

</script>

<template>
    <div class="sticky-top ms-0">

        <header class="navbar navbar-expand-md d-print-none" data-bs-theme="dark">
            <div class="container-xl">
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar-menu"
                    aria-controls="navbar-menu" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <h1 class="navbar-brand navbar-brand-autodark d-none-navbar-horizontal pe-0 pe-md-3">
                    <Link href="/dashboard"><span class="text-success">CRM</span>
                    </Link>
                </h1>
                <div class="navbar-nav flex-row order-md-last">

                    <div class="navbar navbar-light">
                        <div class="container-xl">
                            <ul class="nav nav-pills me-3" v-if="user">
                                <!-- Общий пункт для всех авторизованных пользователей -->
                                <li class="nav-item">
                                    <Link :href="route('dashboard')"
                                        :class="{ 'active-top-link': $page.component.startsWith('Admin/Dashboard') || $page.component.startsWith('Manager/Dashboard') || $page.component.startsWith('Performer/Dashboard') }"
                                        class="nav-link text-light">
                                    <i class="ri-dashboard-line"></i> Главная
                                    </Link>
                                </li>

                                <!-- Меню для Администратора -->
                                <template v-if="isAdmin">
                                    <li class="nav-item">
                                        <Link :href="route('admin.brands.index')"
                                            :class="{ 'active-top-link': $page.url.startsWith('/admin/brands') }"
                                            class="nav-link text-light">
                                        <i class="ri-flag-line"></i> Бренды
                                        </Link>
                                    </li>
                                    <li class="nav-item">
                                        <Link :href="route('users.managers.index')"
                                            :class="{ 'active-top-link': $page.url.startsWith('/users/managers') }"
                                            class="nav-link text-light">
                                        <i class="ri-user-settings-line"></i> Менеджеры
                                        </Link>
                                    </li>
                                    <li class="nav-item">
                                        <Link :href="route('users.executors.index')"
                                            :class="{ 'active-top-link': $page.url.startsWith('/users/executors') }"
                                            class="nav-link text-light">
                                        <i class="ri-user-star-line"></i> Исполнители
                                        </Link>
                                    </li>
                                    <li class="nav-item">
                                        <Link :href="route('task_types.index')"
                                            :class="{ 'active-top-link': $page.url.startsWith('/task-types') }"
                                            class="nav-link text-light">
                                        <i class="ri-price-tag-3-line"></i> Типы задач
                                        </Link>
                                    </li>
                                    <li class="nav-item">
                                        <Link :href="route('admin.yandex.token')"
                                            :class="{ 'active-top-link': $page.url.startsWith('/admin/yd_token') }"
                                            class="nav-link text-light">
                                        <i class="ri-cloud-line"></i> Токен ЯД
                                        </Link>
                                    </li>
                                    <li class="nav-item">
                                        <ResponsiveNavLink :href="route('logout')" method="post" as="button"
                                            class="dropdown-item">
                                            Выйти
                                        </ResponsiveNavLink>
                                    </li>
                                </template>

                                <!-- Меню для Менеджера -->
                                <template v-else-if="isManager">
                                    <li class="nav-item">
                                        <Link :href="route('manager.brands.index')"
                                            :class="{ 'active-top-link': $page.url.startsWith('/manager/brands') }"
                                            class="nav-link text-light">
                                        <i class="ri-flag-line"></i> Бренды
                                        </Link>
                                    </li>
                                    <li class="nav-item">
                                        <Link :href="route('users.executors.index')"
                                            :class="{ 'active-top-link': $page.url.startsWith('/users/executors') }"
                                            class="nav-link text-light">
                                        <i class="ri-user-star-line"></i> Исполнители
                                        </Link>
                                    </li>
                                    <li class="nav-item">
                                        <ResponsiveNavLink :href="route('logout')" method="post" as="button"
                                            class="dropdown-item">
                                            Выйти
                                        </ResponsiveNavLink>
                                    </li>
                                </template>

                                <!-- Меню для Исполнителя -->
                                <template v-else-if="isPerformer">
                                    <li class="nav-item">
                                        <ResponsiveNavLink :href="route('logout')" method="post" as="button"
                                            class="dropdown-item">
                                            Выйти
                                        </ResponsiveNavLink>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </div>

                    <div class="d-none d-md-flex">


                    </div>

                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link d-flex lh-1 text-reset p-0" data-bs-toggle="dropdown"
                            aria-label="Open user menu">
                            <span class="avatar avatar-sm" :style="'background-image: url()'"></span>
                            <div class="d-none d-xl-block ps-2">
                                <div>{{ $page.props.auth.user.name }}</div>
                                <!--<div class="mt-1 small text-muted">{{ $page.props.auth.user.roles[0].name }}</div>-->
                            </div>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                            <ResponsiveNavLink :href="route('logout')" method="post" as="button" class="dropdown-item">
                                Выйти из системы
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </div>
        </header>

    </div>
</template>