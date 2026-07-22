import { useCallback, useEffect, useRef, useState } from "react";

type CopiedValue = string | null;

type CopyFn = (text: string) => Promise<boolean>;

interface useClipboardOptions {
  resetTimeout?: number;
}

export function useClipboard({ resetTimeout }: useClipboardOptions = {}): [CopiedValue, CopyFn] {
  const [copiedText, setCopiedText] = useState<CopiedValue>(null);
  const timeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const clearTimer = (): void => {
    if (timeoutRef.current) {
      clearTimeout(timeoutRef.current);
      timeoutRef.current = null;
    }
  };

  const copy: CopyFn = useCallback(
    async (text) => {
      if (!navigator?.clipboard) {
        return false;
      }

      try {
        await navigator.clipboard.writeText(text);
        setCopiedText(text);

        clearTimer();

        if (resetTimeout && resetTimeout > 0) {
          timeoutRef.current = setTimeout(() => {
            setCopiedText(null);
            timeoutRef.current = null;
          }, resetTimeout);
        }

        return true;
      } catch {
        setCopiedText(null);

        return false;
      }
    },
    [resetTimeout],
  );

  useEffect(() => {
    clearTimer();
  }, []);

  return [copiedText, copy];
}
