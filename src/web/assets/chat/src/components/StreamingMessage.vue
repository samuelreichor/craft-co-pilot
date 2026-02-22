<script setup lang="ts">
import { computed } from 'vue';
import type { LiveToolCall } from '../types';
import { renderMarkdown } from '../utils/markdown';
import ThinkingBlock from './ThinkingBlock.vue';

const props = defineProps<{
  text: string;
  thinking?: string;
  toolCalls: LiveToolCall[];
}>();

const activeToolCall = computed(() =>
  [...props.toolCalls].reverse().find((tc) => tc.status === 'running') ?? null,
);

const showThinkingState = computed(
  () => !props.text && !props.thinking && !activeToolCall.value,
);
</script>

<template>
  <div class="co-pilot-message co-pilot-message--assistant co-pilot-message--streaming">
    <ThinkingBlock
      v-if="thinking"
      :content="thinking"
      :is-streaming="true"
    />
    <div
      v-if="text"
      class="co-pilot-message__content"
      v-html="renderMarkdown(text)"
    />
    <div v-if="showThinkingState" class="co-pilot-stream-status">
      <span class="co-pilot-stream-status__pulse" />
      <span class="co-pilot-stream-status__label">Thinking...</span>
    </div>
    <div v-if="activeToolCall" class="co-pilot-stream-status">
      <span class="co-pilot-stream-status__spinner" />
      <span class="co-pilot-stream-status__label">{{ activeToolCall.name }}</span>
    </div>
    <span v-if="text" class="co-pilot-cursor" />
  </div>
</template>

<style scoped>
.co-pilot-cursor {
  display: inline-block;
  width: 2px;
  height: 14px;
  background: var(--blue-400);
  margin-left: 2px;
  vertical-align: text-bottom;
  animation: co-pilot-blink 1s step-end infinite;
}

@keyframes co-pilot-blink {
  50% {
    opacity: 0;
  }
}

.co-pilot-stream-status {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 4px 0;
  font-size: 13px;
  color: var(--gray-500);
}

.co-pilot-stream-status__label {
  font-weight: 500;
}

.co-pilot-stream-status__pulse {
  width: 8px;
  height: 8px;
  background: var(--blue-400);
  border-radius: 50%;
  animation: co-pilot-status-pulse 1.5s ease-in-out infinite;
}

@keyframes co-pilot-status-pulse {
  0%,
  100% {
    opacity: 0.3;
  }
  50% {
    opacity: 1;
  }
}

.co-pilot-stream-status__spinner {
  display: inline-block;
  width: 12px;
  height: 12px;
  border: 2px solid var(--gray-200);
  border-top-color: var(--blue-400);
  border-radius: 50%;
  animation: co-pilot-status-spin 0.8s linear infinite;
}

@keyframes co-pilot-status-spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
