import type { ComponentProps } from "react";
import { useRef } from "react";

import type { DialogContent } from "@/shared/components/shadcn/ui/dialog";

type DialogFocusRestorationHandlers = Pick<
  ComponentProps<typeof DialogContent>,
  "onOpenAutoFocus" | "onCloseAutoFocus"
>;

interface DialogFocusRestorationOptions {
  captureActiveElement?: boolean;
  getFallbackFocusTarget?: () => HTMLElement | null | undefined;
  onOpenAutoFocus?: NonNullable<DialogFocusRestorationHandlers["onOpenAutoFocus"]>;
}

export function useDialogFocusRestoration({
  captureActiveElement = true,
  getFallbackFocusTarget,
  onOpenAutoFocus,
}: DialogFocusRestorationOptions = {}): DialogFocusRestorationHandlers {
  const returnFocusTargetRef = useRef<HTMLElement | null>(null);

  const handleOpenAutoFocus: NonNullable<DialogFocusRestorationHandlers["onOpenAutoFocus"]> = (
    event,
  ) => {
    if (captureActiveElement) {
      const activeElement = document.activeElement;
      const activeElementIsInsideDialog =
        activeElement instanceof HTMLElement &&
        event.currentTarget instanceof HTMLElement &&
        event.currentTarget.contains(activeElement);

      returnFocusTargetRef.current =
        activeElement instanceof HTMLElement &&
        activeElement !== document.body &&
        !activeElementIsInsideDialog
          ? activeElement
          : null;
    } else {
      returnFocusTargetRef.current = null;
    }

    onOpenAutoFocus?.(event);
  };

  const handleCloseAutoFocus: NonNullable<DialogFocusRestorationHandlers["onCloseAutoFocus"]> = (
    event,
  ) => {
    const capturedTarget = returnFocusTargetRef.current;
    const fallbackFocusTarget = getFallbackFocusTarget?.();
    const focusTarget = capturedTarget?.isConnected
      ? capturedTarget
      : fallbackFocusTarget?.isConnected && fallbackFocusTarget !== document.body
        ? fallbackFocusTarget
        : null;

    if (focusTarget?.isConnected) {
      event.preventDefault();
      focusTarget.focus();
    }

    returnFocusTargetRef.current = null;
  };

  return {
    onOpenAutoFocus: handleOpenAutoFocus,
    onCloseAutoFocus: handleCloseAutoFocus,
  };
}
