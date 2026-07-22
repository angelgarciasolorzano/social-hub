import type { Dispatch, SetStateAction } from "react";
import { useCallback, useState } from "react";

interface UseDialogBooleanReturn {
  open: boolean;
  setOpen: Dispatch<SetStateAction<boolean>>;
}

interface UseDialogTypedReturn<T> {
  state: T | null;
  setState: Dispatch<SetStateAction<T | null>>;
  show: (value: T) => void;
  hide: () => void;
}

export function useDialog(): UseDialogBooleanReturn;
export function useDialog<T>(initialState: T): UseDialogTypedReturn<T>;

export function useDialog<T>(
  initialState?: T | null,
): UseDialogBooleanReturn | UseDialogTypedReturn<T> {
  const [state, setState] = useState<boolean | T | null>(initialState ?? null);

  const show = useCallback((value: T) => {
    setState(value);
  }, []);

  const hide = useCallback(() => {
    setState(null);
  }, []);

  if (initialState === undefined || typeof initialState === "boolean") {
    return {
      open: state === true,
      setOpen: setState as Dispatch<SetStateAction<boolean>>,
    };
  }

  return {
    state: state as T | null,
    setState: setState as Dispatch<SetStateAction<T | null>>,
    show,
    hide,
  };
}
