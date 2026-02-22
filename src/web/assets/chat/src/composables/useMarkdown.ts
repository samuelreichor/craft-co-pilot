import { renderMarkdown, escapeHtml } from '../utils/markdown';

export function useMarkdown() {
  return { renderMarkdown, escapeHtml };
}
