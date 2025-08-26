<script setup>
import TablerLayout from '@/Layouts/TablerLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
  subtasks: { type: Array, default: () => [] },
});

// Search / filter
const search = ref('');
const statusFilter = ref('all'); // all|created|accepted|rejected

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase();
  return props.subtasks.filter((s) => {
    if (statusFilter.value !== 'all' && s.status !== statusFilter.value) return false;
    if (!q) return true;
    const taskName = s.task?.name || '';
    const brandName = s.task?.brand?.name || '';
    return (
      taskName.toLowerCase().includes(q) ||
      brandName.toLowerCase().includes(q) ||
      (s.comment || '').toLowerCase().includes(q)
    );
  });
});

const displayed = computed(() => [...filtered.value].sort((a, b) => new Date(b.created_at) - new Date(a.created_at)));
</script>

<template>
  <TablerLayout>
    <Head title="Мои задания" />
    <template #header>Мои задания</template>

    <div class="card">
      <div class="card-header">
        <div class="row w-full">
          <div class="col">
            <h3 class="card-title mb-0">Назначенные мне подзадачи</h3>
            <p class="text-secondary m-0">Список подзадач роли «Фотограф», назначенных вам.</p>
          </div>
          <div class="col-md-auto col-sm-12">
            <div class="ms-auto d-flex flex-wrap btn-list">
              <div class="input-group input-group-flat w-auto me-2">
                <span class="input-group-text">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    class="icon icon-1">
                    <path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0" />
                    <path d="M21 21l-6 -6" />
                  </svg>
                </span>
                <input v-model="search" type="text" class="form-control" placeholder="Поиск..." />
              </div>
              <select v-model="statusFilter" class="form-select w-auto me-2">
                <option value="all">Все статусы</option>
                <option value="created">Создано</option>
                <option value="accepted">Принято</option>
                <option value="rejected">Отклонено</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-vcenter card-table">
          <thead>
            <tr>
              <th>Задание</th>
              <th>Бренд</th>
              <th>Статус</th>
              <th>Создано</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="displayed.length === 0">
              <td colspan="4" class="text-center text-secondary py-4">Назначенных подзадач нет</td>
            </tr>
            <tr v-for="s in displayed" :key="s.id">
              <td>{{ s.task?.name || '—' }}</td>
              <td>{{ s.task?.brand?.name || '—' }}</td>
              <td>
                <span class="badge" :class="{
                  'bg-secondary': s.status === 'created' || !s.status,
                  'bg-green': s.status === 'accepted',
                  'bg-danger': s.status === 'rejected',
                }">{{ s.status || 'created' }}</span>
              </td>
              <td>{{ new Date(s.created_at).toLocaleString('ru-RU') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </TablerLayout>
</template>
