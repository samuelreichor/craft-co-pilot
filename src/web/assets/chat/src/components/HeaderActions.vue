<script setup lang="ts">
defineProps<{
  conversationId: number | null;
  isExporting: boolean;
}>();

defineEmits<{
  'new-chat': [];
  'export-debug': [];
}>();
</script>

<template>
  <div class="flex" style="gap: 8px; align-items: center">
    <button
      v-if="conversationId"
      type="button"
      class="btn"
      :disabled="isExporting"
      title="Export debug log"
      @click="$emit('export-debug')"
    >
      {{ isExporting ? 'Exporting...' : 'Export Debug' }}
    </button>
    <button type="button" class="btn submit" @click="$emit('new-chat')">
      New Chat +
    </button>
  </div>
</template>
