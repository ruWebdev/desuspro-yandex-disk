<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import TablerLayout from '@/Layouts/TablerLayout.vue';

const props = defineProps({
  tasks: { type: Array, required: true },
  brands: { type: Array, required: true },
});

// Filters
const search = ref('');
const brandFilter = ref(''); // brand id as string for select

const displayedTasks = computed(() => {
  const q = search.value.trim().toLowerCase();
  const brandId = brandFilter.value ? Number(brandFilter.value) : null;
  return props.tasks.filter(t => {
    const byBrand = brandId ? t.brand_id === brandId : true;
    const byQuery = q ? String(t.name || '').toLowerCase().includes(q) : true;
    return byBrand && byQuery;
  });
});

// Create task modal/form
const modalOpen = ref(false);
const createForm = ref({ name: '', brand_id: '', ownership: 'Photographer' });

function openCreate() {
  createForm.value = { name: '', brand_id: '', ownership: 'Photographer' };
  modalOpen.value = true;
}

function submitCreate() {
  if (!createForm.value.name || !createForm.value.brand_id) return;
  router.post(route('tasks.store'), createForm.value, {
    onSuccess: () => { modalOpen.value = false; },
  });
}
</script>

<template>
  <TablerLayout>
    <Head title="Все задания" />
    <template #header>Все задания</template>

    <div class="row g-3 align-items-end mb-3">
      <div class="col-12 col-md-4">
        <label class="form-label">Бренд</label>
        <select class="form-select" v-model="brandFilter">
          <option value="">Все бренды</option>
          <option v-for="b in brands" :key="b.id" :value="String(b.id)">{{ b.name }}</option>
        </select>
      </div>
      <div class="col-12 col-md-5">
        <label class="form-label">Поиск по заданиям</label>
        <input type="text" class="form-control" v-model="search" placeholder="Введите наименование задания" />
      </div>
      <div class="col-12 col-md-3 text-md-end">
        <button class="btn btn-primary" @click="openCreate">Добавить задание</button>
      </div>
    </div>

    <div class="card">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-vcenter card-table">
            <thead>
              <tr>
                <th>Задание</th>
                <th>Бренд</th>
                <th>Статус</th>
                <th>Ответственный</th>
                <th>Создан</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="displayedTasks.length === 0">
                <td colspan="5" class="text-center text-secondary py-4">Нет заданий</td>
              </tr>
              <tr v-for="t in displayedTasks" :key="t.id">
                <td>{{ t.name }}</td>
                <td>{{ t.brand?.name || (brands.find(b => b.id === t.brand_id)?.name) }}</td>
                <td>
                  <span class="badge" :class="{
                    'bg-secondary': t.status === 'created' || !t.status,
                    'bg-blue': t.status === 'assigned',
                    'bg-green': t.status === 'done',
                  }">{{ t.status || 'created' }}</span>
                </td>
                <td>{{ t.assignee?.name || '-' }}</td>
                <td>{{ new Date(t.created_at).toLocaleString('ru-RU') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Create Task Modal -->
    <teleport to="body">
      <div class="modal modal-blur fade" :class="{ show: modalOpen }" :style="modalOpen ? 'display:block;' : ''" tabindex="-1" role="dialog" aria-modal="true">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Новое задание</h5>
              <button type="button" class="btn-close" aria-label="Close" @click="modalOpen=false"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label">Наименование</label>
                <input type="text" class="form-control" v-model="createForm.name" placeholder="Введите наименование" />
              </div>
              <div class="mb-3">
                <label class="form-label">Бренд</label>
                <select class="form-select" v-model="createForm.brand_id">
                  <option value="" disabled>Выберите бренд</option>
                  <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
                </select>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-link" @click="modalOpen=false">Отмена</button>
              <button type="button" class="btn btn-primary" :disabled="!createForm.name || !createForm.brand_id" @click="submitCreate">Создать</button>
            </div>
          </div>
        </div>
      </div>
    </teleport>
  </TablerLayout>
</template>
