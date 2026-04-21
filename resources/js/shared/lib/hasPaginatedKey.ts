import type { PaginatedResponse } from "../types/pagination";

/**
 * Checks if an object has a paginated response under the given key.
 *
 * @param data The object to check
 * @param key The property name to look for
 * @returns Returns true if the value matches the PaginatedResponse shape.
 */
export function hasPaginatedKey<T = unknown>(
  data: unknown,
  key: string,
): data is { [K in typeof key]: PaginatedResponse<T> } {
  if (typeof data !== "object" || data === null) return false;

  const objectData = data as Record<string, unknown>;
  const paginatedData = objectData[key];

  if (typeof paginatedData !== "object" || paginatedData === null) return false;

  const paginatedObject = paginatedData as PaginatedResponse<T>;

  return (
    Array.isArray(paginatedObject.data) &&
    typeof paginatedObject.links === "object" &&
    paginatedObject.links !== null &&
    typeof paginatedObject.meta === "object" &&
    paginatedObject.meta !== null
  );
}
