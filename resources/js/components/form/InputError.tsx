import { type HTMLAttributes } from "react";

import { cn } from "@/lib/utils";

function InputError({
  className = "",
  message,
  ...props
}: HTMLAttributes<HTMLParagraphElement> & { message?: string }) {
  return message ? (
    <p {...props} className={cn("text-sm text-red-600 dark:text-red-400", className)}>
      {message}
    </p>
  ) : null;
}

export default InputError;
