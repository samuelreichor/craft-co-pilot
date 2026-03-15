<script setup lang="ts">
defineProps<{
  compact?: boolean;
}>();

const emit = defineEmits<{
  suggest: [text: string];
}>();

const suggestions = [
  'Summarize the content of my latest blog posts',
  'What sections and entry types are available',
  'Translate this entry to German',
  'Write an SEO-optimized description',
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
