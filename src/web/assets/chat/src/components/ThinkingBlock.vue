<script setup lang="ts">
import { ref } from 'vue';
import { renderMarkdown } from '../utils/markdown';

const props = defineProps<{
  content: string;
  isStreaming?: boolean;
}>();

const expanded = ref(props.isStreaming ?? true);
</script>

<template>
  <div class="co-pilot-thinking">
    <button
      type="button"
      class="co-pilot-thinking__toggle"
      @click="expanded = !expanded"
    >
      <span class="co-pilot-thinking__icon">{{ expanded ? '▾' : '▸' }}</span>
      <span class="co-pilot-thinking__label">Thinking</span>
      <span v-if="isStreaming" class="co-pilot-thinking__pulse" />
    </button>
    <div
      v-if="expanded"
      class="co-pilot-thinking__content"
      v-html="renderMarkdown(content)"
    />
  </div>
</template>

<style scoped>
.co-pilot-thinking {
  margin-bottom: 8px;
  border: 1px solid var(--gray-200);
  border-radius: 8px;
  overflow: hidden;
}

.co-pilot-thinking__toggle {
  display: flex;
  align-items: center;
  gap: 6px;
  width: 100%;
  padding: 6px 10px;
  background: var(--gray-050);
  border: none;
  cursor: pointer;
  font-size: 12px;
  color: var(--gray-500);
  text-align: left;
}

.co-pilot-thinking__toggle:hover {
  background: var(--gray-100);
}

.co-pilot-thinking__icon {
  font-size: 10px;
}

.co-pilot-thinking__label {
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.co-pilot-thinking__pulse {
  width: 6px;
  height: 6px;
  background: var(--blue-400);
  border-radius: 50%;
  animation: co-pilot-pulse 1.5s ease-in-out infinite;
}

@keyframes co-pilot-pulse {
  0%,
  100% {
    opacity: 0.4;
  }
  50% {
    opacity: 1;
  }
}

.co-pilot-thinking__content {
  padding: 8px 10px;
  font-size: 12px;
  color: var(--gray-500);
  line-height: 1.5;
  max-height: 200px;
  overflow-y: auto;
  border-top: 1px solid var(--gray-200);
}

.co-pilot-thinking__content :deep(p) {
  margin: 0 0 4px;
}
.co-pilot-thinking__content :deep(p:last-child) {
  margin-bottom: 0;
}
</style>
