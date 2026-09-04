import type { JSX } from "react";

import type { LucideIcon } from "lucide-react";

interface EmptyStateProps {
  icon?: LucideIcon;
  title: string;
  description?: string;
}

/**
 * Renders the icon + title + description stack of an empty state.
 * The wrapping container (sizing, borders, background, alignment) is the
 * caller's responsibility so this component stays agnostic of where it lives.
 */
function EmptyState({ icon: Icon, title, description }: EmptyStateProps): JSX.Element {
  return (
    <>
      <div className="flex h-14 w-14 shrink-0 rounded-full border">
        {Icon !== undefined && <Icon aria-hidden="true" className="m-auto h-8 w-8" />}
      </div>

      <p className="font-medium text-foreground">{title}</p>

      {description !== undefined && (
        <p className="max-w-sm text-xs text-muted-foreground">{description}</p>
      )}
    </>
  );
}

export default EmptyState;
