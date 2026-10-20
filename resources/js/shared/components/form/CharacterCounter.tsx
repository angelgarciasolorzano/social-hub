import { type ComponentProps, type JSX } from "react";

import { cn } from "@/shared/lib";

const WARNING_THRESHOLD = 0.9;

interface CharacterCounterProps extends Omit<ComponentProps<"p">, "children"> {
  current: number;
  id: string;
  max: number;
}

function CharacterCounter({
  className,
  current,
  id,
  max,
  ...props
}: CharacterCounterProps): JSX.Element {
  const safeCurrent = Math.max(0, current);
  const safeMax = Math.max(0, max);
  const isOverLimit = safeCurrent > safeMax;
  const isNearLimit = !isOverLimit && safeCurrent >= safeMax * WARNING_THRESHOLD && safeCurrent > 0;
  const announcesState = isOverLimit || isNearLimit;

  return (
    <p
      {...props}
      id={id}
      className={cn(
        "text-xs text-muted-foreground",
        isNearLimit && "text-amber-700 dark:text-amber-500",
        isOverLimit && "text-destructive",
        className,
      )}
      {...(announcesState ? { "aria-live": "polite", role: "status" } : {})}
    >
      {safeCurrent} / {safeMax} caracteres
    </p>
  );
}

export default CharacterCounter;
