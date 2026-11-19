import type { JSX } from "react";

import { Deferred, Link, usePage } from "@inertiajs/react";

import { useNotificationStore } from "@/modules/notification/stores/notificationStore";

import { Skeleton } from "@/shared/components/shadcn/ui/skeleton";

import { useTranslation } from "@/shared/hooks";

import { cn } from "@/shared/lib/utils";

import type { SharedData } from "@/shared/types";

import type { HomeSidebarBadge } from "../../data/homeSidebarNavigation";
import { homeSidebarNavigationItems } from "../../data/homeSidebarNavigation";

function HomeSidebarLeft(): JSX.Element {
  const { t } = useTranslation();

  const { url } = usePage<SharedData>();

  const currentPath = url.split("?")[0];

  return (
    <nav
      aria-label={t("home/sidebar.navigation.label")}
      className="w-64 shrink-0 overflow-y-auto border-r px-3 py-4"
    >
      <ul className="space-y-1">
        {homeSidebarNavigationItems.map((navigationItem) => {
          const isCurrentPage = navigationItem.href === currentPath;

          const NavigationIcon = navigationItem.icon;

          return (
            <li key={navigationItem.href}>
              <Link
                aria-current={isCurrentPage ? "page" : undefined}
                className={cn(
                  "flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-foreground",
                  isCurrentPage && "bg-accent text-foreground",
                )}
                href={navigationItem.href}
              >
                <NavigationIcon aria-hidden="true" className="size-5 shrink-0" />

                <span className="flex-1 truncate">{t(navigationItem.labelKey)}</span>

                {navigationItem.badge !== undefined && (
                  <NavigationBadge badge={navigationItem.badge} />
                )}
              </Link>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}

interface NavigationBadgeProps {
  badge: HomeSidebarBadge;
}

function NavigationBadge(props: NavigationBadgeProps): JSX.Element {
  const { badge } = props;

  const { t } = useTranslation();

  const deferredProp =
    badge === "pendingFriendRequests" ? "friendRequests" : "unreadNotificationsCount";

  return (
    <Deferred
      data={deferredProp}
      fallback={
        <Skeleton
          aria-label={t("home/sidebar.navigation.loading_count")}
          className="h-5 w-6 rounded-full"
          role="status"
        />
      }
    >
      {badge === "pendingFriendRequests" ? (
        <PendingFriendRequestsBadge />
      ) : (
        <UnreadNotificationsBadge />
      )}
    </Deferred>
  );
}

function PendingFriendRequestsBadge(): JSX.Element | null {
  const { friendRequests } = usePage<SharedData>().props;

  return (
    <CountBadge
      count={friendRequests?.pendingCount ?? 0}
      labelKey="home/sidebar.navigation.pending_requests_count"
    />
  );
}

function UnreadNotificationsBadge(): JSX.Element | null {
  const { auth, unreadNotificationsCount } = usePage<SharedData>().props;

  const storeUnreadCount = useNotificationStore((state) =>
    state.authenticatedUserId === auth.user.id ? state.unreadCount : null,
  );

  return (
    <CountBadge
      count={storeUnreadCount ?? unreadNotificationsCount ?? 0}
      labelKey="home/sidebar.navigation.unread_notifications_count"
    />
  );
}

interface CountBadgeProps {
  count: number;
  labelKey: string;
}

function CountBadge(props: CountBadgeProps): JSX.Element | null {
  const { count, labelKey } = props;

  const { tChoice } = useTranslation();

  if (count <= 0) return null;

  return (
    <>
      <span
        aria-hidden="true"
        className="min-w-5 rounded-full bg-primary px-1.5 text-center text-xs leading-5 font-semibold text-primary-foreground tabular-nums"
      >
        {count > 99 ? "99+" : count}
      </span>

      <span className="sr-only">{tChoice(labelKey, count, { count })}</span>
    </>
  );
}

export default HomeSidebarLeft;
