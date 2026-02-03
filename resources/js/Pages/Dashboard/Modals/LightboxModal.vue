<script setup>
import { computed } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    lightboxSrc: { type: String, default: '' },
    lightboxType: { type: String, default: 'image' },
    items: { type: Array, default: () => [] },
    index: { type: Number, default: 0 },
    meta: { type: Object, default: null },
});

const emit = defineEmits(['close', 'prev', 'next', 'comment']);

function getFilenameFromUrl(url) {
    try {
        if (!url) return '';
        const u = new URL(url, window.location.origin);
        const path = u.pathname || '';
        const name = path.split('/').filter(Boolean).pop() || '';
        return decodeURIComponent(name);
    } catch (_) {
        // Fallback simple parse
        const q = (url || '').split('?')[0];
        const hash = q.split('#')[0];
        const name = hash.split('/').filter(Boolean).pop() || '';
        try { return decodeURIComponent(name); } catch { return name; }
    }
}

const displayName = computed(() => props.meta?.name ?? getFilenameFromUrl(props.lightboxSrc));
const hasDisplayName = computed(() => Boolean(displayName.value));

function onCommentClick() {
    const fname = props.meta?.name ?? getFilenameFromUrl(props.lightboxSrc);
    emit('comment', fname);
}

function onKeydown(e) {
    if (!props.show) return;
    if (e.key === 'ArrowLeft') { e.preventDefault(); emit('prev'); }
    else if (e.key === 'ArrowRight') { e.preventDefault(); emit('next'); }
    else if (e.key === 'Escape') { e.preventDefault(); emit('close'); }
}

if (typeof window !== 'undefined') {
    window.addEventListener('keydown', onKeydown);
}
</script>

<template>
    <teleport to="body">
        <div class="modal fade" :class="{ show: show }" :style="show ? 'display: block;' : ''" id="lightboxModal"
            data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true"
            @click.self="emit('close')">
            <div class="modal-dialog modal-xl" role="document">
                <div class="modal-content bg-dark position-relative">
                    <div class="modal-header border-0 pb-0">
                        <button type="button" class="btn btn-light btn-lg px-3 py-2" aria-label="Закрыть"
                            @click="emit('close')" title="Закрыть">
                            <i class="ti ti-x fs-3"></i>
                        </button>
                    </div>
                    <div class="modal-body p-0 d-flex justify-content-center align-items-center position-relative"
                        style="min-height: 60vh;">
                        <img v-if="lightboxSrc" :src="lightboxSrc" alt="preview"
                            style="max-width: 100%; max-height: 80vh;" />
                        <button v-if="items && items.length > 1"
                            class="btn btn-light position-absolute start-0 top-50 translate-middle-y ms-2"
                            style="opacity: 0.8;" @click.stop="emit('prev')">
                            ‹
                        </button>
                        <button v-if="items && items.length > 1"
                            class="btn btn-light position-absolute end-0 top-50 translate-middle-y me-2"
                            style="opacity: 0.8;" @click.stop="emit('next')">
                            ›
                        </button>
                    </div>
                    <div class="modal-footer border-0 justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-primary" @click="onCommentClick">КОММЕНТАРИЙ</button>
                            <div class="text-white-50 small" v-if="items && items.length">{{ index + 1 }} / {{
                                items.length }}</div>
                        </div>
                        <div class="text-black-50 text-truncate mx-2" v-if="hasDisplayName" :title="displayName">
                            {{ displayName }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </teleport>
</template>

<style>
/* Ensure lightbox is always above offcanvas/backdrops */
#lightboxModal {
    z-index: 10003000 !important;
}

#lightboxModal .modal-dialog,
#lightboxModal .modal-content {
    z-index: 10003001 !important;
}

/* Its backdrop should also sit above offcanvas backdrop (Bootstrap offcanvas uses ~1040-1045) */
.modal-backdrop.show {
    z-index: 10002995 !important;
}
</style>