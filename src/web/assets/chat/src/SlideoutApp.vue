<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { apiPost } from './composables/useCraftApi';
import { useDebugExport } from './composables/useDebugExport';
import { useCommandHandler } from './composables/useCommandHandler';
import { parseAttachmentsFromContent } from './utils/attachments';
import type { ConversationSummary, UIMessage } from './types';
import ChatPanel from './components/ChatPanel.vue';

const props = defineProps<{
  contextId: number;
  siteHandle?: string | null;
  executionMode?: string | null;
}>();

const activeExecutionMode = ref(props.executionMode || 'supervised');

const chatPanel = ref<InstanceType<typeof ChatPanel> | null>(null);
const dropdownWrap = ref<HTMLElement | null>(null);
const { isExporting, exportDebug } = useDebugExport();
const conversations = ref<ConversationSummary[]>([]);
const activeConversationId = ref<number | null>(null);
const { handleCommand } = useCommandHandler({
  activeConversationId,
  onNewChat: () => newChat(),
  onCompact: (summary) => {
    chatPanel.value?.setMessages([
      { role: 'assistant', content: summary, toolCalls: null, inputTokens: 0, outputTokens: 0 },
    ]);
  },
});
const showDropdown = ref(false);
const historyLoaded = ref(false);

function handleClickOutside(e: MouseEvent) {
  if (
    showDropdown.value &&
    dropdownWrap.value &&
    !dropdownWrap.value.contains(e.target as Node)
  ) {
    showDropdown.value = false;
  }
}

async function fetchConversations() {
  try {
    const data = await apiPost<ConversationSummary[]>(
      'co-pilot/chat/get-entry-conversations',
      { contextId: props.contextId },
    );
    conversations.value = data || [];
  } catch {
    conversations.value = [];
  }
}

async function loadConversation(id: number) {
  try {
    const data = await apiPost<{
      id: number | null;
      messages: Array<{ role: string; content: string }>;
    }>('co-pilot/chat/load-conversation', { id });

    if (data.id) {
      activeConversationId.value = data.id;
      chatPanel.value?.setConversationId(data.id);
      chatPanel.value?.setMessages(
        (data.messages || [])
          .filter((m) => m.role === 'user' || m.role === 'assistant')
          .map((m) => {
            const raw =
              typeof m.content === 'string'
                ? m.content
                : JSON.stringify(m.content);
            if (m.role === 'user') {
              const parsed = parseAttachmentsFromContent(raw);
              return {
                role: 'user' as const,
                content: parsed.content,
                attachments: parsed.attachments,
                toolCalls: null,
                inputTokens: 0,
                outputTokens: 0,
              };
            }
            return {
              role: 'assistant' as const,
              content: raw,
              toolCalls: null,
              inputTokens: 0,
              outputTokens: 0,
            };
          }),
      );
    }
  } catch (err) {
    console.error('Failed to load conversation:', err);
  }
}

async function loadHistory() {
  if (historyLoaded.value || !props.contextId) return;
  historyLoaded.value = true;

  await fetchConversations();
}

function selectConversation(id: number) {
  showDropdown.value = false;
  if (id === activeConversationId.value) return;
  loadConversation(id);
}

function newChat() {
  showDropdown.value = false;
  activeConversationId.value = null;
  chatPanel.value?.clearChat();
}

function onConversationCreated(id: number) {
  activeConversationId.value = id;
  fetchConversations();
}

function focusInput() {
  chatPanel.value?.focusInput();
}

function toggleDropdown() {
  showDropdown.value = !showDropdown.value;
}

onMounted(() => {
  loadHistory();
  document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});

defineExpose({ loadHistory, focusInput });
</script>

<template>
  <div class="co-pilot-slideout-body">
    <div class="co-pilot-slideout-actions">
      <div ref="dropdownWrap" class="co-pilot-slideout-dropdown-wrap">
        <button
          type="button"
          class="btn co-pilot-slideout-dropdown-toggle"
          @click="toggleDropdown"
        >
          <span class="co-pilot-slideout-dropdown-label">
            {{
              activeConversationId
                ? conversations.find((c) => c.id === activeConversationId)
                    ?.title || 'Chat'
                : 'New Chat'
            }}
          </span>
          <span class="co-pilot-slideout-dropdown-caret">
            <svg height="16" width="16" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path d="M297.4 470.6C309.9 483.1 330.2 483.1 342.7 470.6L534.7 278.6C547.2 266.1 547.2 245.8 534.7 233.3C522.2 220.8 501.9 220.8 489.4 233.3L320 402.7L150.6 233.4C138.1 220.9 117.8 220.9 105.3 233.4C92.8 245.9 92.8 266.2 105.3 278.7L297.3 470.7z"/></svg>
          </span>
        </button>
        <div v-if="showDropdown" class="co-pilot-slideout-dropdown">
          <button
            v-for="conv in conversations"
            :key="conv.id"
            type="button"
            class="co-pilot-slideout-dropdown__item"
            :class="{
              'co-pilot-slideout-dropdown__item--active':
                conv.id === activeConversationId,
            }"
            @click="selectConversation(conv.id)"
          >
            {{ conv.title }}
          </button>
          <div
            v-if="conversations.length === 0"
            class="co-pilot-slideout-dropdown__empty"
          >
            No conversations yet
          </div>
        </div>
      </div>
      <button
        v-if="activeConversationId"
        type="button"
        class="btn"
        :disabled="isExporting"
        title="Export debug log"
        @click="exportDebug(activeConversationId!)"
      >
        {{ isExporting ? '...' : 'Export Debug' }}
      </button>
      <button type="button" class="btn submit" @click="newChat">New Chat +</button>
    </div>
    <ChatPanel
      ref="chatPanel"
      context-type="entry"
      :context-id="contextId"
      :site-handle="siteHandle"
      :execution-mode="activeExecutionMode"
      @conversation-created="onConversationCreated"
      @update:execution-mode="activeExecutionMode = $event"
      @command="handleCommand"
    />
  </div>
</template>
