<script setup>
const props = defineProps({
    show: { type: Boolean, default: false },
    selectedCount: { type: Number, default: 0 },
    bulkAssignUserId: { type: [Number, String], default: null },
    performers: { type: Array, required: true }
});

const emit = defineEmits(['close', 'submit', 'update:bulkAssignUserId']);
</script>

<template>
    <teleport to="body">
        <div class="modal fade" :class="{ show: show }" :style="show ? 'display: block;' : ''" id="bulkAssignModal"
            data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="bulkAssignModalLabel"
            aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="bulkAssignModalLabel">Добавить исполнителя ({{ selectedCount }})
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                            @click="emit('close')"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Исполнитель</label>
                            <select class="form-select" :value="bulkAssignUserId"
                                @change="emit('update:bulkAssignUserId', $event.target.value)">
                                <option :value="''">— Не выбрано —</option>
                                <option v-for="u in performers" :key="u.id" :value="u.id">{{ u.name }}</option>
                            </select>
                        </div>
                        <div class="text-secondary small">Будут обновлены выбранные задания: назначен исполнитель и
                            статус «Назначено».</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal"
                            @click="emit('close')">Отмена</button>
                        <button type="button" class="btn btn-primary" @click="emit('submit', bulkAssignUserId)"
                            :disabled="bulkAssignUserId === null || bulkAssignUserId === undefined || bulkAssignUserId === ''">Назначить</button>
                    </div>
                </div>
            </div>
        </div>
    </teleport>
</template>