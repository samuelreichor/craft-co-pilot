<script setup lang="ts">
import type { LiveToolCall } from '../types';
import ToolCallStatus from './ToolCallStatus.vue';

defineProps<{
  tools: LiveToolCall[];
}>();
</script>

<template>
  <div v-if="tools.length > 0" class="co-pilot-todo-list">
    <ToolCallStatus v-for="tool in tools" :key="tool.id" :tool="tool" />
  </div>
</template>

<style scoped>
.co-pilot-todo-list {
  margin: 8px 0;
  padding: 8px 10px;
  background: var(--gray-050);
  border-radius: 6px;
  border: 1px solid var(--gray-200);
}
</style>
