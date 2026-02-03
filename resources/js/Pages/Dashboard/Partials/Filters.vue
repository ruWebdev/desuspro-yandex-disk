<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    // v-model props
    search: { type: String, default: '' },
    globalSearch: { type: String, default: '' },
    brandFilter: { type: [String, Number], default: '' },
    statusFilter: { type: String, default: '' },
    priorityFilter: { type: String, default: '' },
    articleFilter: { type: [String, Number], default: '' },
    performerFilter: { type: [String, Number], default: '' },
    createdFilter: { type: String, default: '' },
    createdDate: { type: String, default: '' },

    // data
    brands: { type: Array, required: true },
    performers: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
    priorityOptions: { type: Array, default: () => [] },
    filterArticles: { type: Array, default: () => [] },
    currentUser: { type: Object, default: null },
});

const emit = defineEmits([
    'update:search',
    'update:globalSearch',
    'update:brandFilter',
    'update:statusFilter',
    'update:priorityFilter',
    'update:articleFilter',
    'update:performerFilter',
    'update:createdFilter',
    'update:createdDate',
    'reset'
]);

// Access global inertia user as fallback
const page = usePage();
const user = computed(() => props.currentUser || page.props?.auth?.user || null);

// Normalize roles (strings or objects) and compute flags
const roleNames = computed(() => {
    const roles = user.value?.roles || [];
    return roles.map(r => (typeof r === 'string' ? r : r?.name || '')).map(n => n.toLowerCase());
});

const isAdminOrPerformer = computed(() => {
    if (!user.value) return false;
    const names = roleNames.value;
    const byName = names.includes('administrator') || names.includes('admin') || names.includes('performer');
    const byFlags = Boolean(user.value.is_admin) || Boolean(user.value.is_performer);
    return byName || byFlags;
});
</script>

<template>
    <div class="d-flex w-100">
        <div class="p-1 flex-fill">
            <input type="text" class="form-control" :value="globalSearch" placeholder="Общий поиск..."
                autocomplete="off" @input="$emit('update:globalSearch', $event.target.value)" />
        </div>
        <div class="p-1 flex-fill">
            <input type="text" class="form-control" :value="search" placeholder="Название..." autocomplete="off"
                @input="$emit('update:search', $event.target.value)" />
        </div>
        <div class="p-1 flex-fill">
            <select class="form-select" :value="brandFilter" @change="$emit('update:brandFilter', $event.target.value)">
                <option value="">Все бренды</option>
                <option v-for="b in brands" :key="b.id" :value="String(b.id)">{{ b.name }}</option>
            </select>
        </div>
        <div class="p-1 flex-fill">
            <select class="form-select" :value="statusFilter"
                @change="$emit('update:statusFilter', $event.target.value)">
                <option value="">Все статусы</option>
                <option v-for="status in statusOptions" :key="status.value" :value="status.value">{{ status.label }}
                </option>
            </select>
        </div>
        <div class="p-1 flex-fill">
            <select class="form-select" :value="priorityFilter"
                @change="$emit('update:priorityFilter', $event.target.value)">
                <option value="">Все приоритеты</option>
                <option v-for="p in priorityOptions" :key="p.value" :value="p.value">{{ p.label }}</option>
            </select>
        </div>
        <div class="p-1 flex-fill" style="display:none;">
            <select class="form-select" :value="articleFilter"
                @change="$emit('update:articleFilter', $event.target.value)" :disabled="!brandFilter">
                <option value="">Все артикулы</option>
                <option v-for="a in filterArticles" :key="a.id" :value="String(a.id)">{{ a.name }}</option>
            </select>
        </div>
        <div v-if="!isAdminOrPerformer" class="p-1 flex-fill">
            <select class="form-select" :value="performerFilter"
                @change="$emit('update:performerFilter', $event.target.value)">
                <option value="">Все исполнители</option>
                <option v-for="u in performers" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
            </select>
        </div>
        <div class="p-1 flex-fill">
            <select class="form-select" :value="createdFilter"
                @change="$emit('update:createdFilter', $event.target.value)">
                <option value="">Все даты</option>
                <option value="today">Сегодня</option>
                <option value="yesterday">Вчера</option>
                <option value="date">Дата…</option>
            </select>
        </div>
        <div class="p-1 flex-fill" v-if="createdFilter === 'date'">
            <input type="date" class="form-control" :value="createdDate"
                @input="$emit('update:createdDate', $event.target.value)" />
        </div>
        <div class="p-1 flex-fill">
            <button class="btn btn-secondary w-100" @click="$emit('reset')">СБРОСИТЬ ФИЛЬТРЫ</button>
        </div>
        <div v-if="!isAdminOrPerformer" class="p-1 flex-fill">
            <button class="btn btn-primary w-100" @click="$emit('create')">
                <i class="ti ti-plus me-1"></i> НОВАЯ ЗАДАЧА
            </button>
        </div>
    </div>
</template>
