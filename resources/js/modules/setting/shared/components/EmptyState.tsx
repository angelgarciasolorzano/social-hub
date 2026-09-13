import type { JSX } from "react";

import type { LucideIcon } from "lucide-react";

interface EmptyStateProps {
  icon?: LucideIcon;
  title: string;
  description?: string;
}

function EmptyState({ icon: Icon, title, description }: EmptyStateProps): JSX.Element {
  return (
    <div className="flex flex-col items-center gap-2">
      <div className="flex h-14 w-14 shrink-0 rounded-full border bg-muted dark:bg-muted/40">
        {Icon !== undefined && <Icon aria-hidden="true" className="m-auto h-8 w-8" />}
      </div>

      <div className="text-center">
        <p className="font-medium text-foreground">{title}</p>

        {description !== undefined && (
          <p className="max-w-sm text-sm text-muted-foreground">{description}</p>
        )}
      </div>
    </div>
  );
}

export default EmptyState;
