/**
 * Validates an image source and provides a fallback if the source is invalid.
 *
 * @param src The image source to validate
 * @param fallback The fallback image source
 * @returns The valid image source
 */
export function validImage(src: string | undefined, fallback: string): string {
  return src && src.trim() !== "" ? src : fallback;
}
