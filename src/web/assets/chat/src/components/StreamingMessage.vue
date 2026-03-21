<script setup lang="ts">
import { computed } from 'vue';
import type { LiveToolCall } from '../types';
import { renderMarkdown } from '../utils/markdown';

const props = defineProps<{
  text: string;
  toolCalls: LiveToolCall[];
}>();

const hasToolCalls = computed(() => props.toolCalls.length > 0);

const isWaiting = computed(() => !props.text && !hasToolCalls.value);

const anyToolRunning = computed(() =>
  props.toolCalls.some((tc) => tc.status === 'running'),
);

const toolSummary = computed(() => {
  const total = props.toolCalls.length;
  if (anyToolRunning.value) {
    const done = props.toolCalls.filter((tc) => tc.status !== 'running').length;
    return done > 0 ? `Running tools (${done}/${total})…` : 'Running tools…';
  }
  return `${total} tool${total !== 1 ? 's' : ''} used`;
});
</script>

<template>
  <div class="co-pilot-message co-pilot-message--assistant co-pilot-message--streaming">
    <!-- Waiting / thinking indicator -->
    <div v-if="isWaiting || (hasToolCalls && !anyToolRunning && !text)" class="co-pilot-stream-status">
      <span class="co-pilot-stream-status__pulse" />
      <span class="co-pilot-stream-status__label">Thinking...</span>
    </div>

    <!-- Streamed text response -->
    <div
      v-if="text"
      class="co-pilot-message__content"
      v-html="renderMarkdown(text)"
    />
    <span v-if="text" class="co-pilot-cursor" />

    <!-- Collapsible tool call log -->
    <details v-if="hasToolCalls" class="co-pilot-tool-details" open>
      <summary class="co-pilot-tool-details__summary">
        <span v-if="anyToolRunning" class="co-pilot-tool-details__spinner" />
        <span>{{ toolSummary }}</span>
      </summary>
      <div class="co-pilot-tool-details__list">
        <div
          v-for="tc in toolCalls"
          :key="tc.id"
          class="co-pilot-tool-details__item"
        >
          <span v-if="tc.status === 'running'" class="co-pilot-tool-details__spinner" />
          <span
            v-else
            class="co-pilot-tool-details__icon"
            :class="tc.status === 'success'
              ? 'co-pilot-tool-details__icon--ok'
              : 'co-pilot-tool-details__icon--fail'"
          >
            {{ tc.status === 'success' ? '&#10003;' : '&#10007;' }}
          </span>
          <span class="co-pilot-tool-details__name">{{ tc.name }}</span>
        </div>
      </div>
    </details>
  </div>
</template>

<style scoped>
.co-pilot-tool-details {
  margin-top: 8px;
  border: 1px solid var(--gray-200);
  border-radius: 6px;
  font-size: 13px;
}

.co-pilot-tool-details__summary {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 10px;
  cursor: pointer;
  color: var(--gray-500);
  font-weight: 500;
  user-select: none;
  list-style: none;
}

.co-pilot-tool-details__summary::-webkit-details-marker {
  display: none;
}

.co-pilot-tool-details__summary::before {
  content: '▶';
  font-size: 9px;
  transition: transform 0.15s ease;
  flex-shrink: 0;
}

.co-pilot-tool-details[open] > .co-pilot-tool-details__summary::before {
  transform: rotate(90deg);
}

.co-pilot-tool-details__list {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 0 10px 8px;
}

.co-pilot-tool-details__item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  color: var(--gray-500);
  padding: 1px 0;
}

.co-pilot-tool-details__name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.co-pilot-tool-details__icon {
  font-size: 11px;
  width: 14px;
  text-align: center;
  flex-shrink: 0;
  font-weight: 700;
}

.co-pilot-tool-details__icon--ok {
  color: var(--teal-500, #14b8a6);
}

.co-pilot-tool-details__icon--fail {
  color: var(--red-500, #ef4444);
}

.co-pilot-tool-details__spinner {
  display: inline-block;
  width: 12px;
  height: 12px;
  border: 2px solid var(--gray-200);
  border-top-color: var(--blue-400);
  border-radius: 50%;
  animation: co-pilot-status-spin 0.8s linear infinite;
  flex-shrink: 0;
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

@keyframes co-pilot-status-spin {
  to {
    transform: rotate(360deg);
  }
}

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
</style>
