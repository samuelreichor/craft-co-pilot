import { ref, onMounted } from 'vue';
import { apiPost } from './useCraftApi';
import type { ModelsResponse } from '../types';

export function useModels() {
  const models = ref<string[]>([]);
  const currentModel = ref('');
  const providerName = ref('');

  onMounted(async () => {
    try {
      const data = await apiPost<ModelsResponse>('co-pilot/chat/get-models');
      models.value = data.models || [];
      currentModel.value =
        data.currentModel || (data.models && data.models[0]) || '';
      providerName.value = data.providerName || '';
    } catch (err) {
      console.error('Failed to load models:', err);
    }
  });

  return { models, currentModel, providerName };
}
