import type { Dispatch, SetStateAction } from "react";

/** Tuned to the Radix `DialogContent` exit animation. */
export const DIALOG_EXIT_ANIMATION_MS = 200;

/** Extend this in dialog state definitions so the `closing` flag stays compatible. */
export interface DialogClosingState {
  closing: boolean;
}

/** Minimum surface from `useDialog<T>` that the close handler needs. */
export interface DialogWithClosing<T> {
  state: T | null;
  setState: Dispatch<SetStateAction<T | null>>;
  hide: () => void;
}

/** Marks the state as `closing: true`, then calls `hide()` after the exit animation. */
export function createDialogCloseHandler<T extends DialogClosingState>(
  dialog: DialogWithClosing<T>,
): () => void {
  return (): void => {
    const current = dialog.state;

    if (current === null || current.closing) {
      return;
    }

    dialog.setState({ ...current, closing: true });

    setTimeout(() => {
      dialog.hide();
    }, DIALOG_EXIT_ANIMATION_MS);
  };
}
