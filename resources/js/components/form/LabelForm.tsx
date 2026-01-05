import { ComponentProps } from "react";

import { Label } from "../ui/label";

interface LabelFormProps extends ComponentProps<typeof Label> {
  error?: string;
}

function LabelForm({ error, className, ...props }: LabelFormProps) {
  return (
    <Label
      className={`${className ?? ""} ${error ? "text-destructive dark:text-destructive-foreground" : ""}`.trim()}
      {...props}
    />
  );
}

export default LabelForm;
