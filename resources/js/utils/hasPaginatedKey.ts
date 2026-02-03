import { PaginatedResponse } from "@/types/pagination";

export function hasPaginatedKey<T = unknown>(
  data: unknown,
  key: string,
): data is { [K in typeof key]: PaginatedResponse<T> } {
  if (typeof data !== "object" || data === null) return false;

  const objectData = data as Record<string, unknown>;
  const paginatedData = objectData[key];

  if (typeof paginatedData !== "object" || paginatedData === null) return false;

  const paginatedObject = paginatedData as Record<string, unknown>;

  return (
    Array.isArray(paginatedObject.data) &&
    typeof paginatedObject.links === "object" &&
    paginatedObject.links !== null &&
    typeof paginatedObject.meta === "object" &&
    paginatedObject.meta !== null
  );
}
