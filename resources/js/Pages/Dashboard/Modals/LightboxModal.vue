<script setup>
const props = defineProps({
    show: { type: Boolean, default: false },
    lightboxSrc: { type: String, default: '' },
    lightboxType: { type: String, default: 'image' }
});

const emit = defineEmits(['close']);
</script>

<template>
    <teleport to="body">
        <div class="modal fade" :class="{ show: show }" :style="show ? 'display: block;' : ''" id="lightboxModal"
            data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true"
            @click.self="emit('close')">
            <div class="modal-dialog modal-xl" role="document">
                <div class="modal-content bg-dark">
                    <div class="modal-body p-0 d-flex justify-content-center align-items-center"
                        style="min-height: 60vh;">
                        <img v-if="lightboxSrc" :src="lightboxSrc" alt="preview"
                            style="max-width: 100%; max-height: 80vh;" />
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-light" @click="emit('close')">Закрыть</button>
                    </div>
                </div>
            </div>
        </div>
    </teleport>
</template>

<style>
 /* Ensure lightbox is always above offcanvas/backdrops */
 #lightboxModal {
   z-index: 2000 !important;
 }
 #lightboxModal .modal-dialog,
 #lightboxModal .modal-content {
   z-index: 2001 !important;
 }
 /* Its backdrop should also sit above offcanvas backdrop (Bootstrap offcanvas uses ~1040-1045) */
 .modal-backdrop.show {
   z-index: 1995 !important;
 }
</style>