import { ref } from 'vue';

export function useDebugExport() {
  const isExporting = ref(false);

  async function exportDebug(conversationId: number) {
    if (isExporting.value) return;
    isExporting.value = true;

    try {
      const response = await Craft.sendActionRequest(
        'POST',
        'co-pilot/chat/export-debug',
        { data: { id: conversationId } },
      );

      // Trigger browser download from the response data
      const json = JSON.stringify(response.data, null, 2);
      const blob = new Blob([json], { type: 'application/json' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `copilot-debug-${conversationId}-${formatDate()}.json`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    } catch (err) {
      console.error('Failed to export debug log:', err);
    } finally {
      isExporting.value = false;
    }
  }

  return { isExporting, exportDebug };
}

function formatDate(): string {
  const d = new Date();
  const pad = (n: number) => String(n).padStart(2, '0');
  return (
    `${d.getFullYear()}${pad(d.getMonth() + 1)}${pad(d.getDate())}-` +
    `${pad(d.getHours())}${pad(d.getMinutes())}${pad(d.getSeconds())}`
  );
}
