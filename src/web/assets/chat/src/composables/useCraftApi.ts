/**
 * Wrapper around Craft.sendActionRequest for type-safe API calls.
 */
export async function apiPost<T = unknown>(
  action: string,
  data: Record<string, unknown> = {},
): Promise<T> {
  const response = await Craft.sendActionRequest('POST', action, { data });
  return response.data as T;
}
