<script setup>
const props = defineProps({
    show: { type: Boolean, default: false },
    renaming: { type: Object, default: null },
    renameName: { type: String, default: '' }
});

const emit = defineEmits(['cancel', 'submit']);
</script>

<template>
    <teleport to="body">
        <div class="modal fade" :class="{ show: show }" :style="show ? 'display: block;' : ''" id="renameModal"
            data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="renameModalLabel"
            aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="renameModalLabel">Переименовать задачу</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                            @click="emit('cancel')"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label">Новое название</label>
                        <input type="text" class="form-control" :value="renameName"
                            @input="$emit('update:renameName', $event.target.value)"
                            @keyup.enter="emit('submit', renameName)" />
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn me-auto" @click="emit('cancel')">Отмена</button>
                        <button type="button" class="btn btn-primary"
                            @click="emit('submit', renameName)">Сохранить</button>
                    </div>
                </div>
            </div>
        </div>
    </teleport>
</template>