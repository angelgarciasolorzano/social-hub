import type { Dispatch, SetStateAction } from "react";
import { useState } from "react";

interface UseDialogReturn {
  open: boolean;
  setOpen: Dispatch<SetStateAction<boolean>>;
}

export function useDialog(initialState: boolean = false): UseDialogReturn {
  const [open, setOpen] = useState<boolean>(initialState);

  return { open, setOpen };
}
