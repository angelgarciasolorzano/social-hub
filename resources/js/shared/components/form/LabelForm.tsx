import type { ComponentProps } from "react";

import { Label } from "../../../components/ui/label";

interface LabelFormProps extends ComponentProps<typeof Label> {
  error?: string;
}

function LabelForm({ className, error, ...props }: LabelFormProps) {
  return (
    <Label
      className={`${className ?? ""} ${error ? "text-destructive dark:text-destructive-foreground" : ""}`.trim()}
      {...props}
    />
  );
}

export default LabelForm;
