<script setup lang="ts">
import { ref, watch, onMounted, nextTick } from 'vue';
import type { UIMessage, LiveToolCall } from '../types';
import ChatMessage from './ChatMessage.vue';
import StreamingMessage from './StreamingMessage.vue';
import WelcomeScreen from './WelcomeScreen.vue';

const props = defineProps<{
  messages: UIMessage[];
  isLoading: boolean;
  compact?: boolean;
  isStreaming?: boolean;
  streamingText?: string;
  liveToolCalls?: LiveToolCall[];
}>();

defineEmits<{
  suggest: [text: string];
}>();

const list = ref<HTMLElement | null>(null);

function scrollToBottom() {
  nextTick(() => {
    if (list.value) {
      list.value.scrollTop = list.value.scrollHeight;
    }
  });
}

watch(() => props.messages, scrollToBottom, { deep: true });
watch(() => props.isLoading, scrollToBottom);
watch(() => props.streamingText, scrollToBottom);

onMounted(scrollToBottom);
</script>

<template>
  <div ref="list" class="co-pilot-messages">
    <WelcomeScreen
      v-if="messages.length === 0 && !isLoading && !isStreaming"
      :compact="compact"
      @suggest="$emit('suggest', $event)"
    />
    <ChatMessage
      v-for="(msg, i) in messages"
      :key="i"
      :message="msg"
    />
    <StreamingMessage
      v-if="isStreaming"
      :text="streamingText || ''"
      :tool-calls="liveToolCalls || []"
    />
    <div v-if="isLoading && !isStreaming" class="co-pilot-loading">
      <span class="co-pilot-loading__dot" />
      <span class="co-pilot-loading__dot" />
      <span class="co-pilot-loading__dot" />
    </div>
  </div>
</template>
