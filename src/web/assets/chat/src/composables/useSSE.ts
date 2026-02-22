import type { StreamEvent, StreamEventType } from '../types';

interface UseSSEOptions {
  onEvent: (event: StreamEvent) => void;
  onError?: (error: Error) => void;
  onComplete?: () => void;
}

interface SSEConnection {
  abort: () => void;
}

/**
 * POST-based SSE using fetch + ReadableStream.
 * Sends a POST request with CSRF header and reads the SSE stream incrementally.
 */
export function useSSE() {
  function connect(
    action: string,
    data: Record<string, unknown>,
    options: UseSSEOptions,
  ): SSEConnection {
    const controller = new AbortController();

    // Use Craft's Axios instance to get the correct base URL,
    // then build a proper action URL
    const actionUrl = buildActionUrl(action);

    // Include CSRF token both in header and body for compatibility
    const bodyData = {
      ...data,
      [Craft.csrfTokenName]: Craft.csrfTokenValue,
    };

    fetch(actionUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'text/event-stream',
        'X-CSRF-Token': Craft.csrfTokenValue,
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify(bodyData),
      signal: controller.signal,
    })
      .then(async (response) => {
        if (!response.ok) {
          const text = await response.text().catch(() => '');
          throw new Error(
            `SSE request failed: ${response.status} ${text.slice(0, 200)}`,
          );
        }

        const reader = response.body?.getReader();
        if (!reader) {
          throw new Error('ReadableStream not supported');
        }

        const decoder = new TextDecoder();
        let buffer = '';
        let currentEventType: StreamEventType | null = null;

        while (true) {
          const { done, value } = await reader.read();
          if (done) break;

          buffer += decoder.decode(value, { stream: true });
          const lines = buffer.split('\n');
          // Keep the last incomplete line in buffer
          buffer = lines.pop() || '';

          for (const line of lines) {
            if (line.startsWith('event: ')) {
              currentEventType = line.slice(7).trim() as StreamEventType;
            } else if (line.startsWith('data: ') && currentEventType) {
              try {
                const eventData = JSON.parse(line.slice(6));
                options.onEvent({
                  type: currentEventType,
                  data: eventData,
                });
              } catch {
                // Skip malformed JSON
              }
              currentEventType = null;
            } else if (line === '') {
              currentEventType = null;
            }
          }
        }

        options.onComplete?.();
      })
      .catch((err: Error) => {
        if (err.name === 'AbortError') return;
        options.onError?.(err);
      });

    return {
      abort: () => controller.abort(),
    };
  }

  return { connect };
}

/**
 * Build an action URL compatible with Craft's URL format.
 *
 * Craft.actionUrl can include query params like &site=en:
 *   "https://site.test/index.php?p=admin/actions/&site=en"
 *
 * We need to insert the action into the path portion (?p=...action)
 * before any additional query params (&site=...).
 */
function buildActionUrl(action: string): string {
  const base = Craft.actionUrl || '';

  // Parse the URL to properly insert the action
  try {
    const url = new URL(base, window.location.origin);
    const pParam = url.searchParams.get('p');

    if (pParam !== null) {
      // Query-string based: ?p=admin/actions/ → ?p=admin/actions/co-pilot/...
      const separator = pParam.endsWith('/') ? '' : '/';
      url.searchParams.set('p', pParam + separator + action);
      return url.toString();
    }

    // Clean URL: just append the action to the path
    const separator = url.pathname.endsWith('/') ? '' : '/';
    url.pathname = url.pathname + separator + action;
    return url.toString();
  } catch {
    // Fallback: simple concatenation
    const separator = base.endsWith('/') ? '' : '/';
    return base + separator + action;
  }
}
