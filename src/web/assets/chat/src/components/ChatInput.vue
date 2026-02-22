<script setup lang="ts">
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import type { Attachment } from '../types';
import {
  ALLOWED_FILE_EXTENSIONS,
  isFileAllowed,
  getMaxFileSize,
} from '../utils/attachments';
import AttachmentPill from './AttachmentPill.vue';

defineProps<{
  isLoading: boolean;
  isStreaming?: boolean;
  attachments: Attachment[];
  compact?: boolean;
}>();

const emit = defineEmits<{
  send: [text: string];
  cancel: [];
  'add-attachment': [attachment: Attachment];
  'remove-attachment': [index: number];
}>();

const text = ref('');
const showMenu = ref(false);
const textarea = ref<HTMLTextAreaElement | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const addWrap = ref<HTMLElement | null>(null);

function handleKeydown(e: KeyboardEvent) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    submit();
  }
}

function submit() {
  const msg = text.value.trim();
  if (!msg) return;
  emit('send', msg);
  text.value = '';
  nextTick(() => {
    if (textarea.value) autoGrow({ target: textarea.value } as unknown as Event);
  });
}

function autoGrow(e: Event) {
  const el = e.target as HTMLTextAreaElement;
  el.style.height = 'auto';
  el.style.height = Math.min(el.scrollHeight, 200) + 'px';
}

function toggleMenu() {
  showMenu.value = !showMenu.value;
}

function selectEntry() {
  showMenu.value = false;
  Craft.createElementSelectorModal('craft\\elements\\Entry', {
    multiSelect: false,
    onSelect: (elements) => {
      if (elements.length) {
        emit('add-attachment', {
          type: 'entry',
          id: elements[0].id,
          label: elements[0].label,
        });
      }
    },
  });
}

function selectAsset() {
  showMenu.value = false;
  Craft.createElementSelectorModal('craft\\elements\\Asset', {
    multiSelect: false,
    onSelect: (elements) => {
      if (elements.length) {
        emit('add-attachment', {
          type: 'asset',
          id: elements[0].id,
          label: elements[0].label,
        });
      }
    },
  });
}

function uploadFile() {
  showMenu.value = false;
  if (fileInput.value) {
    fileInput.value.value = '';
    fileInput.value.click();
  }
}

function handleFileSelect(e: Event) {
  const input = e.target as HTMLInputElement;
  const file = input.files?.[0];
  if (!file) return;

  if (!isFileAllowed(file.name)) {
    alert(`Unsupported file type. Allowed: ${ALLOWED_FILE_EXTENSIONS}`);
    return;
  }

  if (file.size > getMaxFileSize()) {
    alert('File is too large. Maximum size is 100 KB.');
    return;
  }

  emit('add-attachment', {
    type: 'file',
    file,
    label: file.name,
  });
}

function handleClickOutside(e: MouseEvent) {
  if (showMenu.value && addWrap.value && !addWrap.value.contains(e.target as Node)) {
    showMenu.value = false;
  }
}

function focus() {
  textarea.value?.focus();
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});

defineExpose({ focus });
</script>

<template>
  <div class="co-pilot-input">
    <div v-if="attachments.length > 0" class="co-pilot-input__attachments">
      <AttachmentPill
        v-for="(att, i) in attachments"
        :key="i"
        :attachment="att"
        @remove="$emit('remove-attachment', i)"
      />
    </div>
    <div class="co-pilot-input__row">
      <div v-if="!compact" ref="addWrap" class="co-pilot-input__add-wrap">
        <button
          type="button"
          class="co-pilot-input__add-btn btn"
          title="Add attachment"
          @click="toggleMenu"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="24"
            height="24"
            viewBox="0 0 24 24"
          >
            <path
              fill="none"
              stroke="currentColor"
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-miterlimit="10"
              stroke-width="1.5"
              d="m19.605 10.48l.137-.136a4.31 4.31 0 0 0 0-6.086a4.307 4.307 0 0 0-6.086 0l-9.398 9.398a4.307 4.307 0 0 0 0 6.086a4.31 4.31 0 0 0 6.086 0l6.351-6.356a2.35 2.35 0 0 0-1.66-4.008a2.35 2.35 0 0 0-1.66.688l-6.657 6.656"
            />
          </svg>
        </button>
        <div v-if="showMenu" class="co-pilot-input__menu">
          <button
            type="button"
            class="co-pilot-input__menu-item"
            @click="selectEntry"
          >
            Select Entry
          </button>
          <button
            type="button"
            class="co-pilot-input__menu-item"
            @click="selectAsset"
          >
            Select Asset
          </button>
          <button
            type="button"
            class="co-pilot-input__menu-item"
            @click="uploadFile"
          >
            Upload File
          </button>
        </div>
      </div>
      <textarea
        ref="textarea"
        class="co-pilot-input__textarea"
        v-model="text"
        :placeholder="compact ? 'Ask about this entry...' : 'Ask CoPilot...'"
        rows="1"
        :disabled="isLoading"
        @keydown="handleKeydown"
        @input="autoGrow"
      />
      <button
        v-if="isStreaming"
        type="button"
        class="co-pilot-input__cancel-btn btn"
        title="Cancel"
        @click="$emit('cancel')"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="20"
          height="20"
          viewBox="0 0 24 24"
        >
          <rect
            x="6"
            y="6"
            width="12"
            height="12"
            rx="2"
            fill="currentColor"
          />
        </svg>
      </button>
      <button
        v-else
        type="button"
        class="co-pilot-input__send-btn btn submit"
        :class="isLoading || !text.trim() ? 'disabled' : ''"
        title="Send message"
        @click="submit"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="24"
          height="24"
          viewBox="0 0 24 24"
        >
          <path
            fill="none"
            stroke="currentColor"
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M14.536 21.686a.5.5 0 0 0 .937-.024l6.5-19a.496.496 0 0 0-.635-.635l-19 6.5a.5.5 0 0 0-.024.937l7.93 3.18a2 2 0 0 1 1.112 1.11zm7.318-19.539l-10.94 10.939"
          />
        </svg>
      </button>
    </div>
    <input
      ref="fileInput"
      type="file"
      :accept="ALLOWED_FILE_EXTENSIONS"
      style="display: none"
      @change="handleFileSelect"
    />
  </div>
</template>
