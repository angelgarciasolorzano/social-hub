export function validImage(src: string | undefined, fallback: string): string {
  return src && src.trim() !== "" ? src : fallback;
}
