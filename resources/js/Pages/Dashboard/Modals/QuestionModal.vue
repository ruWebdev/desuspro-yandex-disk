<script setup>
import { ref, watch, nextTick } from 'vue';

const props = defineProps({
  show: { type: Boolean, default: false },
  title: { type: String, default: 'Вопрос по задаче' },
  placeholder: { type: String, default: 'Опишите ваш вопрос…' }
});

const emit = defineEmits(['close', 'submit']);

const text = ref('');

watch(() => props.show, async (val) => {
  if (val) {
    text.value = '';
    await nextTick();
  }
});

function onClose() { emit('close'); }
function onSubmit() {
  const value = (text.value || '').trim();
  if (!value) return;
  emit('submit', value);
}
</script>

<template>
  <teleport to="body">
    <div class="modal fade" :class="{ show: show }" :style="show ? 'display: block;' : ''" tabindex="-1"
      aria-hidden="true" @click.self="onClose">
      <div class="modal-dialog modal-md">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ title }}</h5>
            <button type="button" class="btn-close" aria-label="Close" @click="onClose"></button>
          </div>
          <div class="modal-body">
            <textarea class="form-control" rows="4" v-model="text" :placeholder="placeholder"></textarea>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-link link-secondary" @click="onClose">Отмена</button>
            <button type="button" class="btn btn-primary" :disabled="!text.trim()" @click="onSubmit">Отправить</button>
          </div>
        </div>
      </div>
    </div>
  </teleport>
</template>
