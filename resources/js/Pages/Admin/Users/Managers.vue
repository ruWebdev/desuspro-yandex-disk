<script>

// Импорт разметки для проекта
import MainLayout from '@/Layouts/MainLayout.vue';
import axios from 'axios';

export default {
    layout: MainLayout
};

</script>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { Modal } from 'bootstrap'
import ContentLayout from '@/Layouts/ContentLayout.vue';

const props = defineProps({
    users: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
})

const search = ref(props.filters.search || '')

function onSearch() {
    router.get(route('users.managers.index'), { search: search.value }, { preserveState: true, replace: true })
}

// Refs for modals
const createModal = ref(null)
const editModal = ref(null)
const deleteModal = ref(null)
const deleteRestrictedModal = ref(null)

// Modal instances
let createModalInstance = null
let editModalInstance = null
let deleteModalInstance = null
let deleteRestrictedModalInstance = null

// Other refs
const selected = ref(null)

// Initialize modals when component is mounted
onMounted(() => {
    createModalInstance = new Modal(document.getElementById('modal-create-manager'))
    editModalInstance = new Modal(document.getElementById('modal-edit-manager'))
    deleteModalInstance = new Modal(document.getElementById('modal-delete-manager'))
    deleteRestrictedModalInstance = new Modal(document.getElementById('modal-delete-restricted-manager'))
})

// Cleanup modals when component is unmounted
onBeforeUnmount(() => {
    if (createModalInstance) createModalInstance.dispose()
    if (editModalInstance) editModalInstance.dispose()
    if (deleteModalInstance) deleteModalInstance.dispose()
    if (deleteRestrictedModalInstance) deleteRestrictedModalInstance.dispose()
})

const createForm = useForm({
    name: '',
    email: '',
    password: '',
    last_name: '',
    first_name: '',
    middle_name: '',
    is_blocked: false,
    can_edit_result: false,
})

const editForm = useForm({
    name: '',
    email: '',
    password: '',
    last_name: '',
    first_name: '',
    middle_name: '',
    is_blocked: false,
    can_edit_result: false,
})

// Validation: all fields must be filled
const isFilled = (v) => typeof v === 'string' ? v.trim().length > 0 : !!v
const createInvalid = computed(() => !(
    isFilled(createForm.last_name) &&
    isFilled(createForm.first_name) &&
    isFilled(createForm.middle_name) &&
    isFilled(createForm.email) &&
    isFilled(createForm.password)
))
const editInvalid = computed(() => !(
    isFilled(editForm.last_name) &&
    isFilled(editForm.first_name) &&
    isFilled(editForm.middle_name) &&
    isFilled(editForm.email)
))

function openCreate() {
    createForm.reset()
    createModalInstance.show()
}

function openEdit(user) {
    selected.value = user
    editForm.reset()
    editForm.email = user.email
    editForm.last_name = user.last_name
    editForm.first_name = user.first_name
    editForm.middle_name = user.middle_name
    editForm.is_blocked = !!user.is_blocked
    editForm.can_edit_result = !!user.can_edit_result
    editModalInstance.show()
}

function openDelete(user) {
    selected.value = user
    deleteModalInstance.show()
}

function generatePassword(target) {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%'
    let pass = ''
    for (let i = 0; i < 12; i++) pass += chars[Math.floor(Math.random() * chars.length)]
    if (target === 'create') createForm.password = pass
    else editForm.password = pass
}

function submitCreate() {
    // derive display name from FIO
    createForm.name = [createForm.last_name, createForm.first_name, createForm.middle_name].filter(Boolean).join(' ').trim()
    createForm.post(route('users.managers.store'), {
        onSuccess: () => {
            createModalInstance.hide()
            createForm.reset()
        },
    })
}

function submitEdit() {
    if (!selected.value) return
    // derive display name from FIO
    const name = [editForm.last_name, editForm.first_name, editForm.middle_name].filter(Boolean).join(' ').trim()
    const payload = {
        name,
        email: editForm.email,
        last_name: editForm.last_name,
        first_name: editForm.first_name,
        middle_name: editForm.middle_name,
        is_blocked: editForm.is_blocked,
        can_edit_result: editForm.can_edit_result,
    }
    const pwd = (editForm.password || '').trim()
    if (pwd.length > 0) payload.password = pwd
    router.put(route('users.managers.update', selected.value.id), payload, {
        onSuccess: () => { editModalInstance.hide() },
    })
}

function submitDelete() {
    if (!selected.value) return
    router.delete(route('users.managers.destroy', selected.value.id), {
        onSuccess: () => { deleteModalInstance.hide() },
        onError: (errors) => {
            if (errors.delete) {
                deleteModalInstance.hide()
                deleteRestrictedModalInstance.show()
            }
        },
    })
}
</script>

<template>

    <Head title="Менеджеры" />
    <ContentLayout>

        <template #TopButtons>
            <div class="d-flex w-100">
                <div class="p-1 flex-fill">
                    <input v-model="search" type="text" class="form-control" autocomplete="off"
                        placeholder="Поиск по имени или e-mail..." @keyup.enter="onSearch" />
                </div>
                <div class="p-1">
                    <button class="btn btn-primary" @click="openCreate">
                        Новый менеджер
                    </button>
                </div>
            </div>
        </template>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-vcenter">
                    <thead>
                        <tr>
                            <th>ФИО</th>
                            <th>E-mail</th>
                            <th>Заблокирован</th>
                            <th>Править Результат</th>
                            <th class="w-1"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="u in props.users" :key="u.id">
                            <td>
                                <div class="fw-bold">{{ u.name }}</div>
                            </td>
                            <td>{{ u.email }}</td>
                            <td>
                                <span :class="['badge', 'text-light', u.is_blocked ? 'bg-red' : 'bg-green']">{{
                                    u.is_blocked ? 'Да' :
                                        'Нет'
                                }}</span>
                            </td>
                            <td>
                                <span
                                    :class="['badge', 'text-light', u.can_edit_result ? 'bg-green' : 'bg-secondary']">{{
                                        u.can_edit_result ? 'Да' : 'Нет'
                                    }}</span>
                            </td>
                            <td class="text-end">
                                <div class="btn-list flex-nowrap">
                                    <button class="btn btn-sm" @click="openEdit(u)"><i class="ti ti-edit"></i>
                                        Изменить</button>
                                    <button class="btn btn-sm btn-danger" @click="openDelete(u)"><i
                                            class="ti ti-trash"></i>
                                        Удалить</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="props.users.length === 0">
                            <td colspan="5" class="text-center text-secondary py-5">Нет данных</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </ContentLayout>

    <!-- Create Modal -->
    <div class="modal fade" id="modal-create-manager" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
        aria-labelledby="modalCreateManagerLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Новый менеджер</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label">Фамилия</label>
                            <input v-model="createForm.last_name" type="text" class="form-control" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Имя</label>
                            <input v-model="createForm.first_name" type="text" class="form-control" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Отчество</label>
                            <input v-model="createForm.middle_name" type="text" class="form-control" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">E-mail</label>
                            <input v-model="createForm.email" type="email" class="form-control" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Пароль</label>
                            <div class="input-group">
                                <input v-model="createForm.password" type="text" class="form-control" />
                                <button class="btn btn-outline"
                                    @click="generatePassword('create')">Сгенерировать</button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-check">
                                <input class="form-check-input" type="checkbox" v-model="createForm.is_blocked" />
                                <span class="form-check-label">Заблокирован</span>
                            </label>
                        </div>
                        <div class="col-md-6">
                            <label class="form-check">
                                <input class="form-check-input" type="checkbox" v-model="createForm.can_edit_result" />
                                <span class="form-check-label">Править Результат</span>
                            </label>
                            <small class="text-muted d-block">Разрешает заменять и архивировать файлы результата</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn me-auto" data-bs-dismiss="modal">Отмена</button>
                    <button class="btn btn-primary" @click="submitCreate"
                        :disabled="createForm.processing || createInvalid">
                        Создать
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="modal-edit-manager" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
        aria-labelledby="modalEditManagerLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Редактирование менеджера</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label">Фамилия</label>
                            <input v-model="editForm.last_name" type="text" class="form-control" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Имя</label>
                            <input v-model="editForm.first_name" type="text" class="form-control" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Отчество</label>
                            <input v-model="editForm.middle_name" type="text" class="form-control" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">E-mail</label>
                            <input v-model="editForm.email" type="email" class="form-control" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Пароль</label>
                            <div class="input-group">
                                <input v-model="editForm.password" type="text" class="form-control" />
                                <button class="btn btn-outline" @click="generatePassword('edit')">Сгенерировать</button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-check">
                                <input class="form-check-input" type="checkbox" v-model="editForm.is_blocked" />
                                <span class="form-check-label">Заблокирован</span>
                            </label>
                        </div>
                        <div class="col-md-6">
                            <label class="form-check">
                                <input class="form-check-input" type="checkbox" v-model="editForm.can_edit_result" />
                                <span class="form-check-label">Править Результат</span>
                            </label>
                            <small class="text-muted d-block">Разрешает заменять и архивировать файлы результата</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn me-auto" data-bs-dismiss="modal">Отмена</button>
                    <button class="btn btn-primary" @click="submitEdit"
                        :disabled="editForm.processing || editInvalid">Сохранить</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="modal-delete-manager" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
        aria-labelledby="modalDeleteManagerLabel" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center py-4">
                    <h3>Удаление менеджера</h3>
                    <div class="text-muted mb-3">Вы уверены, что хотите удалить этого менеджера?</div>
                    <p>«{{ selected?.name }}»?</p>
                </div>
                <div class="modal-footer">
                    <button class="btn me-auto" data-bs-dismiss="modal">Отмена</button>
                    <button class="btn btn-danger" @click="submitDelete">Удалить</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Restricted Modal -->
    <div class="modal fade" id="modal-delete-restricted-manager" data-bs-backdrop="static" data-bs-keyboard="false"
        tabindex="-1" aria-labelledby="modalDeleteRestrictedManagerLabel" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center py-4">
                    <i class="ti ti-alert-triangle icon mb-2 text-danger" style="font-size: 2rem"></i>
                    <h3>Удаление невозможно</h3>
                    <div class="text-muted mb-3">Невозможно удалить менеджера, так как с ним связаны заказы.</div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary w-100" data-bs-dismiss="modal">Закрыть</button>
                </div>
            </div>
        </div>
    </div>

</template>

<style scoped>
/* Fix z-index issue with modals - ensure modal content is above backdrop */
:deep(.modal) {
    z-index: 1055 !important;
}

:deep(.modal-backdrop) {
    z-index: 1050 !important;
}
</style>
