import { ref, watch, onMounted } from 'vue';
import { apiPost } from './useCraftApi';
import type { ModelsResponse, ProviderInfo } from '../types';

const COOKIE_PROVIDER = 'co_pilot_provider';
const COOKIE_MODEL = 'co_pilot_model';
const COOKIE_MAX_AGE = 60 * 60 * 24 * 365; // 1 year

function getCookie(name: string): string | null {
  const match = document.cookie.match(
    new RegExp('(?:^|; )' + name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '=([^;]*)'),
  );

  return match ? decodeURIComponent(match[1]) : null;
}

function setCookie(name: string, value: string): void {
  document.cookie = `${name}=${encodeURIComponent(value)}; path=/; max-age=${COOKIE_MAX_AGE}; SameSite=Lax`;
}

export function useModels() {
  const models = ref<string[]>([]);
  const currentModel = ref('');
  const providerName = ref('');
  const currentProvider = ref('');
  const providers = ref<ProviderInfo[]>([]);

  onMounted(async () => {
    try {
      const data = await apiPost<ModelsResponse>('co-pilot/chat/get-models');
      providers.value = data.providers || [];

      const savedProvider = getCookie(COOKIE_PROVIDER);
      const savedModel = getCookie(COOKIE_MODEL);

      // Restore saved provider if it exists in the available providers
      const matchedProvider = savedProvider
        ? providers.value.find((p) => p.handle === savedProvider)
        : null;

      if (matchedProvider) {
        currentProvider.value = matchedProvider.handle;
        models.value = matchedProvider.models;
        providerName.value = matchedProvider.name;

        // Restore saved model if it belongs to this provider
        currentModel.value =
          savedModel && matchedProvider.models.includes(savedModel)
            ? savedModel
            : matchedProvider.defaultModel || (matchedProvider.models[0] ?? '');
      } else {
        currentProvider.value = data.provider || '';
        models.value = data.models || [];
        providerName.value = data.providerName || '';
        currentModel.value =
          data.currentModel || (data.models && data.models[0]) || '';
      }
    } catch (err) {
      console.error('Failed to load models:', err);
    }
  });

  watch(currentProvider, (value) => {
    if (value) setCookie(COOKIE_PROVIDER, value);
  });

  watch(currentModel, (value) => {
    if (value) setCookie(COOKIE_MODEL, value);
  });

  function switchProvider(handle: string) {
    const provider = providers.value.find((p) => p.handle === handle);
    if (!provider) return;

    currentProvider.value = handle;
    models.value = provider.models;
    currentModel.value =
      provider.defaultModel || (provider.models[0] ?? '');
    providerName.value = provider.name;
  }

  return {
    models,
    currentModel,
    providerName,
    currentProvider,
    providers,
    switchProvider,
  };
}
