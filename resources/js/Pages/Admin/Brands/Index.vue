<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import DashByteLayout from '@/Layouts/DashByteLayout.vue';

defineOptions({
    layout: DashByteLayout,
});

const props = defineProps({
    brands: {
        type: Array,
        required: true,
    },
});

const searchQuery = ref('');

const filteredBrands = computed(() => {
    if (!searchQuery.value) return props.brands;
    const query = searchQuery.value.toLowerCase();
    return props.brands.filter(brand => 
        brand.name.toLowerCase().includes(query)
    );
});

const deleteBrand = (brand) => {
    if (confirm('Вы уверены, что хотите удалить этот бренд?')) {
        router.delete(route('admin.brands.destroy', brand.id), {
            preserveScroll: true,
            onSuccess: () => {
                // The page will be refreshed by Inertia
            },
        });
    }
};
</script>

<template>
    <Head title="Управление брендами" />

    <div class="page-header d-print-none">
        <div class="container-xl">
            <div class="row g-2 align-items-center">
                <div class="col">
                    <h2 class="page-title">
                        Управление брендами
                    </h2>
                </div>
                <div class="col-auto ms-auto d-print-none">
                    <div class="d-flex">
                        <Link :href="route('admin.brands.create')" class="btn btn-primary">
                            <i class="ri-add-line me-1"></i> Добавить бренд
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="page-body">
        <div class="container-xl">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3">
                        <div class="input-group input-group-flat w-auto me-2 mb-2 mb-md-0">
                            <span class="input-group-text">
                                <i class="ri-search-line"></i>
                            </span>
                            <input 
                                v-model="searchQuery" 
                                type="text" 
                                class="form-control" 
                                placeholder="Поиск по названию"
                            >
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-vcenter table-nowrap">
                            <thead>
                                <tr>
                                    <th>Название</th>
                                    <th>Дата создания</th>
                                    <th class="w-1">Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="brand in filteredBrands" :key="brand.id">
                                    <td>{{ brand.name }}</td>
                                    <td>{{ new Date(brand.created_at).toLocaleDateString('ru-RU') }}</td>
                                    <td class="text-nowrap">
                                        <div class="btn-list flex-nowrap">
                                            <Link 
                                                :href="route('admin.brands.edit', brand.id)" 
                                                class="btn btn-icon btn-outline-primary btn-sm me-1"
                                                title="Редактировать"
                                            >
                                                <i class="ri-edit-line"></i>
                                            </Link>
                                            <button 
                                                @click="deleteBrand(brand)" 
                                                class="btn btn-icon btn-outline-danger btn-sm"
                                                title="Удалить"
                                            >
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="filteredBrands.length === 0">
                                    <td colspan="3" class="text-center text-muted py-4">
                                        <i class="ri-information-line me-1"></i>
                                        Бренды не найдены
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
