<script setup>
const props = defineProps({
    show: { type: Boolean, default: false },
    assigningTask: { type: Object, default: null },
    assignUserId: { type: [Number, String], default: null },
    performers: { type: Array, required: true }
});

const emit = defineEmits(['close', 'submit', 'update:assignUserId']);
</script>

<template>
    <teleport to="body">
        <div class="modal fade" :class="{ show: show }" :style="show ? 'display: block;' : ''" id="assignModal"
            data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="assignModalLabel"
            aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="assignModalLabel">Назначить исполнителя</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                            @click="emit('close')"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="assignUserId" class="form-label">Исполнитель</label>
                            <select class="form-select" id="assignUserId" 
                                :value="assignUserId" 
                                @input="$emit('update:assignUserId', $event.target.value)">
                                <option :value="null">Не назначено</option>
                                <option v-for="performer in performers" :key="performer.id" :value="performer.id">
                                    {{ performer.name }}
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal"
                            @click="emit('close')">Отмена</button>
                        <button type="button" class="btn btn-primary" @click="emit('submit', assignUserId)"
                            :disabled="assignUserId === null || assignUserId === undefined || assignUserId === ''">Назначить</button>
                    </div>
                </div>
            </div>
        </div>
    </teleport>
</template>