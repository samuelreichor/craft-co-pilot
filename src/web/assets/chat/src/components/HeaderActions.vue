<script setup lang="ts">
defineProps<{
  models: string[];
  currentModel: string;
}>();

defineEmits<{
  'new-chat': [];
  'update:currentModel': [value: string];
}>();
</script>

<template>
  <div class="flex" style="gap: 8px; align-items: center">
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
    <button type="button" class="btn" @click="$emit('new-chat')">
      New Chat +
    </button>
  </div>
</template>
