import type { JSX } from "react";

import type { LucideIcon } from "lucide-react";

interface EmptyStateProps {
  icon?: LucideIcon;
  title: string;
  description?: string;
}

function EmptyState({ icon: Icon, title, description }: EmptyStateProps): JSX.Element {
  return (
    <div className="flex flex-col items-center justify-center gap-2 py-8 text-center text-sm text-muted-foreground">
      {Icon !== undefined && <Icon aria-hidden="true" className="h-10 w-10 opacity-50" />}
      <p className="font-medium text-foreground">{title}</p>
      {description !== undefined && <p className="max-w-sm text-xs">{description}</p>}
    </div>
  );
}

export default EmptyState;
