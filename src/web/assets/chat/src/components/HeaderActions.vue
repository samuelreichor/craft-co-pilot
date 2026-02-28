<script setup lang="ts">
defineProps<{
  models: string[];
  currentModel: string;
  conversationId: number | null;
  isExporting: boolean;
}>();

defineEmits<{
  'new-chat': [];
  'update:currentModel': [value: string];
  'export-debug': [];
}>();
</script>

<template>
  <div class="flex" style="gap: 8px; align-items: center">
    <button
      v-if="conversationId"
      type="button"
      class="btn"
      :disabled="isExporting"
      title="Export debug log"
      @click="$emit('export-debug')"
    >
      {{ isExporting ? 'Exporting...' : 'Export Debug' }}
    </button>
    <div v-if="models.length > 0" class="select">
      <select
        :value="currentModel"
        @change="
          $emit(
            'update:currentModel',
            ($event.target as HTMLSelectElement).value,
          )
        "
      >
        <option v-for="model in models" :key="model" :value="model">
          {{ model }}
        </option>
      </select>
    </div>
    <button type="button" class="btn submit" @click="$emit('new-chat')">
      New Chat +
    </button>
  </div>
</template>
