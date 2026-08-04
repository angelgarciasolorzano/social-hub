/**
 * Returns the value if it is not null, undefined, or an empty string.
 * Otherwise returns the fallback.
 *
 * @example
 * valueOrFallback("Mac", "Desconocido")        // "Mac"
 * valueOrFallback("", "Desconocido")            // "Desconocido"
 * valueOrFallback(null, "Desconocido")          // "Desconocido"
 * valueOrFallback(undefined, "Desconocido")     // "Desconocido"
 */
export function valueOrFallback(value: string | null | undefined, fallback: string): string {
  if (value === null || value === undefined || value === "") {
    return fallback;
  }
  return value;
}
