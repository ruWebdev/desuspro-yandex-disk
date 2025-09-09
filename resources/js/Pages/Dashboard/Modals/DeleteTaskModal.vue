<script setup>
const props = defineProps({
    show: { type: Boolean, default: false },
    deleting: { type: Object, default: null }
});

const emit = defineEmits(['cancel', 'confirm']);
</script>

<template>
    <teleport to="body">
        <div class="modal fade" :class="{ show: show }" :style="show ? 'display: block;' : ''" id="deleteModal"
            data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="deleteModalLabel"
            aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-status bg-danger"></div>
                    <div class="modal-body text-center py-4">
                        <h3>Удалить задачу?</h3>
                        <div class="text-muted">
                            Вы уверены, что хотите удалить задачу <strong>{{ deleting?.name || '' }}</strong>?<br>
                            Это действие нельзя отменить.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <div class="w-100">
                            <div class="row">
                                <div class="col">
                                    <button class="btn w-100" @click="emit('cancel')">
                                        Отмена
                                    </button>
                                </div>
                                <div class="col">
                                    <button class="btn btn-danger w-100" @click="emit('confirm')">
                                        Удалить
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </teleport>
</template>