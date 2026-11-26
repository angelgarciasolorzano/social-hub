import type { JSX } from "react";

import { Link } from "@inertiajs/react";

import type { LucideIcon } from "lucide-react";
import { SearchIcon, SearchXIcon, SendIcon, UserPlusIcon, UsersIcon } from "lucide-react";

import { search } from "@/shared/wayfinder/routes/friendships";

import { Button } from "@/shared/components/shadcn/ui/button";

import { useTranslation } from "@/shared/hooks";

import type { IconColorVariant } from "@/shared/lib/styling";
import { iconColorVariants } from "@/shared/lib/styling";
import { cn } from "@/shared/lib/utils";

import type { FriendshipListFilters, FriendshipListTab } from "../../types/friendshipList";

interface TabEmptyStateVisual {
  color: IconColorVariant;
  icon: LucideIcon;
}

const TAB_EMPTY_STATE_VISUALS: Record<FriendshipListTab, TabEmptyStateVisual> = {
  friends: { color: "blue", icon: UsersIcon },
  received: { color: "orange", icon: UserPlusIcon },
  sent: { color: "gray", icon: SendIcon },
};

interface FriendsEmptyStateProps {
  committedFilters: FriendshipListFilters;
}

function FriendsEmptyState(props: FriendsEmptyStateProps): JSX.Element {
  const { committedFilters } = props;

  const { t } = useTranslation();

  const { color, icon } = TAB_EMPTY_STATE_VISUALS[committedFilters.tab];

  const isFiltered = committedFilters.q !== "";

  const colors = iconColorVariants[color];

  const Icon = isFiltered ? SearchXIcon : icon;

  return (
    <div className="flex flex-col items-center rounded-xl border border-dashed bg-muted/30 px-6 py-12 text-center dark:bg-muted/20">
      <div
        aria-hidden="true"
        className={cn(
          "mb-4 flex size-12 items-center justify-center rounded-full",
          colors.iconBgClass,
        )}
      >
        <Icon className={cn("size-6", colors.iconFgClass)} />
      </div>

      <h2 className="text-lg font-medium">
        {isFiltered
          ? t("friendship/friends.states.filtered_empty_title", { term: committedFilters.q })
          : t(`friendship/friends.states.${committedFilters.tab}_empty_title`)}
      </h2>

      <p className="mx-auto mt-2 max-w-md text-sm text-muted-foreground">
        {isFiltered
          ? t("friendship/friends.states.filtered_empty_description")
          : t(`friendship/friends.states.${committedFilters.tab}_empty_description`)}
      </p>

      {!isFiltered && committedFilters.tab !== "received" && (
        <Button asChild className="mt-4" size="sm" variant="secondary">
          <Link href={search()}>
            <SearchIcon aria-hidden="true" className="size-4" />
            {t("friendship/friends.states.find_people")}
          </Link>
        </Button>
      )}
    </div>
  );
}

export default FriendsEmptyState;
