import type { RefCallback } from "react";
import { useCallback, useState } from "react";

interface UseCommentScrollAreaViewportReturn {
  scrollAreaRef: RefCallback<HTMLDivElement>;
  viewport: HTMLElement | null;
}

/**
 * Expose the element that really scrolls inside a shadcn ScrollArea, since its Viewport does not
 * forward a ref. Pass `scrollAreaRef` to the ScrollArea and use `viewport` as an observer root.
 */
export function useCommentScrollAreaViewport(): UseCommentScrollAreaViewportReturn {
  const [viewport, setViewport] = useState<HTMLElement | null>(null);

  const scrollAreaRef = useCallback((scrollArea: HTMLDivElement | null) => {
    setViewport(
      scrollArea?.querySelector<HTMLElement>('[data-slot="scroll-area-viewport"]') ?? null,
    );
  }, []);

  return { scrollAreaRef, viewport };
}
