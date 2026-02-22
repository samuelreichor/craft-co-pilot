<script setup lang="ts">
import type { LiveToolCall } from '../types';

defineProps<{
  tool: LiveToolCall;
}>();
</script>

<template>
  <div class="co-pilot-tool-status">
    <span
      class="co-pilot-tool-status__icon"
      :class="`co-pilot-tool-status__icon--${tool.status}`"
    >
      <span v-if="tool.status === 'running'" class="co-pilot-tool-status__spinner" />
      <span v-else-if="tool.status === 'success'">&#10003;</span>
      <span v-else>&#10007;</span>
    </span>
    <span class="co-pilot-tool-status__name">{{ tool.name }}</span>
  </div>
</template>

<style scoped>
.co-pilot-tool-status {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  padding: 3px 0;
  color: var(--gray-600);
}

.co-pilot-tool-status__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 16px;
  height: 16px;
  font-weight: 600;
}

.co-pilot-tool-status__icon--success {
  color: var(--green-500);
}

.co-pilot-tool-status__icon--error {
  color: var(--red-500);
}

.co-pilot-tool-status__icon--running {
  color: var(--blue-400);
}

.co-pilot-tool-status__spinner {
  display: inline-block;
  width: 12px;
  height: 12px;
  border: 2px solid var(--gray-200);
  border-top-color: var(--blue-400);
  border-radius: 50%;
  animation: co-pilot-spin 0.8s linear infinite;
}

@keyframes co-pilot-spin {
  to {
    transform: rotate(360deg);
  }
}

.co-pilot-tool-status__name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
