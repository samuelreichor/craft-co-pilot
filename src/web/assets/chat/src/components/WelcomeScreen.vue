<script setup lang="ts">
defineProps<{
  compact?: boolean;
}>();

const emit = defineEmits<{
  suggest: [text: string];
}>();

const suggestions = [
  'Search for recent entries',
  'List all available sections',
  'Show me draft entries',
  'Help me create a new entry',
  'Find entries by a specific author',
  'What content types exist?',
];
</script>

<template>
  <div class="co-pilot-welcome">
    <h2>How can I help you today?</h2>
    <div v-if="!compact" class="co-pilot-welcome__suggestions">
      <button
        v-for="suggestion in suggestions"
        :key="suggestion"
        class="co-pilot-welcome__chip"
        type="button"
        @click="emit('suggest', suggestion)"
      >
        {{ suggestion }}
      </button>
    </div>
  </div>
</template>
