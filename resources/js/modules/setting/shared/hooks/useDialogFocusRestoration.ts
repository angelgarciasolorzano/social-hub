import type { ComponentProps } from "react";
import { useRef } from "react";

import type { DialogContent } from "@/shared/components/shadcn/ui/dialog";

type DialogFocusRestorationHandlers = Pick<
  ComponentProps<typeof DialogContent>,
  "onOpenAutoFocus" | "onCloseAutoFocus"
>;

export function useDialogFocusRestoration(): DialogFocusRestorationHandlers {
  const returnFocusTargetRef = useRef<HTMLElement | null>(null);

  const handleOpenAutoFocus: NonNullable<
    DialogFocusRestorationHandlers["onOpenAutoFocus"]
  > = () => {
    const activeElement = document.activeElement;

    returnFocusTargetRef.current =
      activeElement instanceof HTMLElement && activeElement !== document.body
        ? activeElement
        : null;
  };

  const handleCloseAutoFocus: NonNullable<DialogFocusRestorationHandlers["onCloseAutoFocus"]> = (
    event,
  ) => {
    const focusTarget = returnFocusTargetRef.current;

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
