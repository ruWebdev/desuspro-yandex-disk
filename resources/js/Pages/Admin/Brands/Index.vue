<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
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
const showCreate = ref(false);
const createForm = useForm({ name: '' });
const creating = computed(() => createForm.processing);

function openCreate() {
    createForm.reset();
    showCreate.value = true;
}

function submitCreate() {
    createForm.post(route('admin.brands.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCreate.value = false;
            createForm.reset();
        },
    });
}

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
                        <button @click="openCreate" class="btn btn-primary">
                            <i class="ri-add-line me-1"></i> Добавить бренд
                        </button>
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
                            <input v-model="searchQuery" type="text" class="form-control"
                                placeholder="Поиск по названию">
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
                                            <Link :href="route('admin.brands.edit', brand.id)"
                                                class="btn btn-icon btn-outline-primary btn-sm me-1"
                                                title="Редактировать">
                                            <i class="ri-edit-line"></i>
                                            </Link>
                                            <button @click="deleteBrand(brand)"
                                                class="btn btn-icon btn-outline-danger btn-sm" title="Удалить">
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

    <!-- Create Modal -->
    <teleport to="body">
        <div v-if="showCreate">
            <div class="modal modal-blur fade show d-block" tabindex="-1" role="dialog" style="z-index: 1050;">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Добавить бренд</h5>
                            <button type="button" class="btn-close" aria-label="Close"
                                @click="showCreate = false"></button>
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
</template>
