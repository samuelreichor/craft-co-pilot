<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useModels } from './composables/useModels';
import { useConversations } from './composables/useConversations';
import ConversationSidebar from './components/ConversationSidebar.vue';
import HeaderActions from './components/HeaderActions.vue';
import ChatPanel from './components/ChatPanel.vue';

const init = window.__COPILOT_INIT__ || {};

const { models, currentModel } = useModels();
const {
  conversations,
  activeConversationId,
  loadConversation,
  deleteConversation,
  refreshConversations,
} = useConversations(init.conversations || []);

const chatPanel = ref<InstanceType<typeof ChatPanel> | null>(null);

function updateUrl(conversationId: number | null) {
  const path = conversationId ? `co-pilot/${conversationId}` : 'co-pilot';
  history.replaceState(null, '', Craft.getCpUrl(path));
}

async function selectConversation(id: number) {
  if (id === activeConversationId.value) return;
  try {
    const msgs = await loadConversation(id);
    chatPanel.value?.setMessages(msgs);
    chatPanel.value?.setConversationId(id);
    updateUrl(id);
  } catch (err) {
    console.error('Failed to load conversation:', err);
  }
}

async function handleDeleteConversation(id: number) {
  try {
    const wasActive = activeConversationId.value === id;
    await deleteConversation(id);
    if (wasActive) {
      chatPanel.value?.clearChat();
      updateUrl(null);
    }
  } catch (err) {
    console.error('Failed to delete conversation:', err);
  }
}

function newChat() {
  activeConversationId.value = null;
  chatPanel.value?.clearChat();
  updateUrl(null);
}

function onConversationCreated(id: number) {
  activeConversationId.value = id;
  refreshConversations();
  updateUrl(id);
}

onMounted(() => {
  if (init.activeConversationId) {
    selectConversation(init.activeConversationId);
  }
});
</script>

<template>
  <Teleport to="#co-pilot-action-btns">
    <HeaderActions
      :models="models"
      :current-model="currentModel"
      @update:current-model="currentModel = $event"
      @new-chat="newChat"
    />
  </Teleport>
  <Teleport to="#co-pilot-sidebar-mount">
    <ConversationSidebar
      :conversations="conversations"
      :active-id="activeConversationId"
      @select="selectConversation"
      @delete="handleDeleteConversation"
    />
  </Teleport>
  <ChatPanel
    ref="chatPanel"
    context-type="global"
    :context-id="init.contextId"
    :model="currentModel"
    :site-handle="init.siteHandle"
    @conversation-created="onConversationCreated"
  />
</template>
