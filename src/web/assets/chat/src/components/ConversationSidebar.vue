<script setup lang="ts">
import type { ConversationSummary } from '../types';

defineProps<{
  conversations: ConversationSummary[];
  activeId: number | null;
}>();

defineEmits<{
  select: [id: number];
  delete: [id: number];
}>();
</script>

<template>
  <nav aria-label="Conversations">
    <ul>
      <li v-if="conversations.length === 0">
        <span class="co-pilot-sidebar-empty">No conversations yet</span>
      </li>
      <li
        v-for="conv in conversations"
        :key="conv.id"
        class="co-pilot-sidebar-item"
      >
        <a
          :class="{ sel: conv.id === activeId }"
          href="#"
          @click.prevent="$emit('select', conv.id)"
        >
          <span class="label">{{ conv.title }}</span>
          <span
            class="co-pilot-sidebar-delete"
            role="button"
            title="Delete conversation"
            @click.stop.prevent="$emit('delete', conv.id)"
            >&times;</span
          >
        </a>
      </li>
    </ul>
  </nav>
</template>
