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

// Offcanvas state for per-ownership view
const offcanvasOpen = ref(false);
const oc = ref({ brandId: null, brandName: '', taskId: null, taskName: '', ownership: 'Photographer' });

function openOffcanvas(task, ownership) {
  const brandName = task.brand?.name || (props.brands.find(b => b.id === task.brand_id)?.name) || '';
  oc.value = {
    brandId: task.brand_id,
    brandName,
    taskId: task.id,
    taskName: task.name,
    ownership,
  };
  offcanvasOpen.value = true;
}

// Row actions: edit / delete
function onEditTask(t) {
  const name = window.prompt('Новое наименование задания', t.name || '');
  if (!name) return;
  router.put(route('brands.tasks.update', { brand: t.brand_id, task: t.id }), { name });
}

function onDeleteTask(t) {
  if (!window.confirm('Удалить задание и связанные данные?')) return;
  router.delete(route('brands.tasks.destroy', { brand: t.brand_id, task: t.id }));
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
                <th>Ответственный</th>
                <th>Статус</th>
                <th class="w-1">ПОДЗАДАНИЯ</th>
                <th>Создан</th>
                <th class="w-1">ДЕЙСТВИЯ</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="displayedTasks.length === 0">
                <td colspan="7" class="text-center text-secondary py-4">Нет заданий</td>
              </tr>
              <tr v-for="t in displayedTasks" :key="t.id">
                <td>{{ t.name }}</td>
                <td>{{t.brand?.name || (brands.find(b => b.id === t.brand_id)?.name)}}</td>
                <td>{{ t.assignee?.name || '-' }}</td>
                <td>
                  <span class="badge" :class="{
                    'bg-secondary': t.status === 'created' || !t.status,
                    'bg-blue': t.status === 'assigned',
                    'bg-green': t.status === 'done',
                  }">{{ t.status || 'created' }}</span>
                </td>
                <td class="text-nowrap">
                  <div class="btn-list d-flex flex-nowrap align-items-center gap-2" style="white-space: nowrap;">
                    <button class="btn btn-ghost-primary btn-sm" @click="openOffcanvas(t, 'Photographer')"
                      title="Открыть подзадачу: ФОТОГРАФ">
                      ФОТОГРАФ
                    </button>
                    <button class="btn btn-ghost-purple btn-sm" @click="openOffcanvas(t, 'PhotoEditor')"
                      title="Открыть подзадачу: ФОТОРЕДАКТОР">
                      ФОТОРЕДАКТОР
                    </button>
                  </div>
                </td>
                <td>{{ new Date(t.created_at).toLocaleString('ru-RU') }}</td>
                <td class="text-nowrap">
                  <div class="btn-list d-flex flex-nowrap align-items-center gap-2">
                    <button class="btn btn-icon btn-ghost-primary" @click="onEditTask(t)" title="Редактировать">
                      <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24"
                        stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round"
                        stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M4 20h4l10.5 -10.5a2.828 2.828 0 1 0 -4 -4l-10.5 10.5v4" />
                        <path d="M13.5 6.5l4 4" />
                      </svg>
                    </button>
                    <button class="btn btn-icon btn-ghost-danger" @click="onDeleteTask(t)" title="Удалить">
                      <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="16" height="16" viewBox="0 0 24 24"
                        stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round"
                        stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M4 7h16" />
                        <path d="M10 11v6" />
                        <path d="M14 11v6" />
                        <path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" />
                        <path d="M9 7v-2a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v2" />
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Right offcanvas -->
    <teleport to="body">
      <div class="offcanvas offcanvas-end" :class="{ show: offcanvasOpen }"
        :style="offcanvasOpen ? 'visibility: visible;' : ''" tabindex="-1" role="dialog">
        <div class="offcanvas-header">
          <h5 class="offcanvas-title">
            {{ oc.brandName }} / {{ oc.taskName }}<br />
            <span class="badge text-light" :class="oc.ownership === 'Photographer' ? 'bg-blue' : 'bg-purple'">
              {{ oc.ownership === 'Photographer' ? 'Фотограф' : 'Фоторедактор' }}
            </span>
          </h5>
          <button type="button" class="btn-close text-reset" aria-label="Close" @click="offcanvasOpen = false"></button>
        </div>
        <div class="offcanvas-body">
          <!-- TODO: content to be defined later -->
          <div class="text-secondary">Содержимое offcanvas будет добавлено позже.</div>
        </div>
      </div>
    </teleport>

    <!-- Create Task Modal -->
    <teleport to="body">
      <div class="modal modal-blur fade" :class="{ show: modalOpen }" :style="modalOpen ? 'display:block;' : ''"
        tabindex="-1" role="dialog" aria-modal="true">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Новое задание</h5>
              <button type="button" class="btn-close" aria-label="Close" @click="modalOpen = false"></button>
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
              <button type="button" class="btn btn-link" @click="modalOpen = false">Отмена</button>
              <button type="button" class="btn btn-primary" :disabled="!createForm.name || !createForm.brand_id"
                @click="submitCreate">Создать</button>
            </div>
          </div>
        </div>
      </div>
    </teleport>
  </TablerLayout>
</template>
