import type { Dispatch, SetStateAction } from "react";
import { useState } from "react";

interface UseModalReturn {
  open: boolean;
  setOpen: Dispatch<SetStateAction<boolean>>;
}

export function useModal(initialState: boolean = false): UseModalReturn {
  const [open, setOpen] = useState(initialState);

  return { open, setOpen };
}
