import type { ComponentProps } from "react";

import { cn } from "@/shared/lib";

import { Label } from "../../../components/ui/label";

interface LabelFormProps extends ComponentProps<typeof Label> {
  error?: string;
}

function LabelForm({ className, error, ...props }: LabelFormProps) {
  return (
    <Label
      className={cn(className, error && "text-destructive dark:text-destructive-foreground")}
      {...props}
    />
  );
}

export default LabelForm;
