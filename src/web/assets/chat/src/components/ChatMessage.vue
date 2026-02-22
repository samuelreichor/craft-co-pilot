<script setup lang="ts">
import { computed } from 'vue';
import type { UIMessage } from '../types';
import { renderMarkdown, escapeHtml } from '../utils/markdown';
import AttachmentPill from './AttachmentPill.vue';
import ThinkingBlock from './ThinkingBlock.vue';

const props = defineProps<{
  message: UIMessage;
}>();

const isUser = computed(() => props.message.role === 'user');
const isAssistant = computed(() => props.message.role === 'assistant');

const renderedContent = computed(() => {
  if (isUser.value) {
    return escapeHtml(props.message.content || '');
  }
  return renderMarkdown(props.message.content || '');
});

const hasAttachments = computed(
  () => props.message.attachments && props.message.attachments.length > 0,
);

const hasToolCalls = computed(
  () => props.message.toolCalls && props.message.toolCalls.length > 0,
);

const modifiedEntries = computed(() => {
  if (!props.message.toolCalls) return [];
  const seen: Record<number, boolean> = {};
  return props.message.toolCalls.filter((tc) => {
    if (!tc.cpEditUrl || !tc.entryId) return false;
    if (seen[tc.entryId]) return false;
    seen[tc.entryId] = true;
    return true;
  });
});

const hasTokens = computed(
  () => props.message.inputTokens || props.message.outputTokens,
);
</script>

<template>
  <div
    class="co-pilot-message"
    :class="{
      'co-pilot-message--user': isUser,
      'co-pilot-message--assistant': isAssistant,
    }"
  >
    <div v-if="hasAttachments" class="co-pilot-message__attachments">
      <AttachmentPill
        v-for="(att, i) in message.attachments"
        :key="i"
        :attachment="att"
        :readonly="true"
      />
    </div>
    <ThinkingBlock
      v-if="message.thinking"
      :content="message.thinking"
      :is-streaming="false"
    />
    <div class="co-pilot-message__content" v-html="renderedContent" />
    <div v-if="hasToolCalls" class="co-pilot-message__checklist">
      <div
        v-for="(tc, i) in message.toolCalls"
        :key="i"
        class="co-pilot-message__check-item"
      >
        <span
          class="co-pilot-message__check-icon"
          :class="
            tc.success
              ? 'co-pilot-message__check-icon--ok'
              : 'co-pilot-message__check-icon--fail'
          "
        >
          {{ tc.success ? '&#10003;' : '&#10007;' }}
        </span>
        <span class="co-pilot-message__check-name">{{ tc.name }}</span>
      </div>
    </div>
    <div v-if="modifiedEntries.length" class="co-pilot-message__entries">
      <a
        v-for="entry in modifiedEntries"
        :key="entry.entryId!"
        :href="entry.cpEditUrl!"
        class="co-pilot-message__entry-link"
      >
        {{ entry.entryTitle || 'Entry #' + entry.entryId }} &#8599;
      </a>
    </div>
    <div v-if="hasTokens" class="co-pilot-message__tokens">
      {{ message.inputTokens }} in / {{ message.outputTokens }} out tokens
    </div>
  </div>
</template>
