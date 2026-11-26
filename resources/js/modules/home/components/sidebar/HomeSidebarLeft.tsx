import type { JSX } from "react";

import { Deferred, Link, usePage } from "@inertiajs/react";

import { useNotificationStore } from "@/modules/notification/stores/notificationStore";

import { Skeleton } from "@/shared/components/shadcn/ui/skeleton";

import { useTranslation } from "@/shared/hooks";

import { cn } from "@/shared/lib/utils";

import type { SharedData } from "@/shared/types";

import type { HomeSidebarBadge } from "../../data/homeSidebarNavigation";
import { homeSidebarNavigationItems } from "../../data/homeSidebarNavigation";

const BADGE_CLASS_NAMES: Record<HomeSidebarBadge, string> = {
  pendingFriendRequests: "bg-blue-600 text-white dark:bg-blue-500 dark:text-blue-950",
  unreadNotifications: "bg-orange-700 text-white dark:bg-orange-500 dark:text-orange-950",
};

function HomeSidebarLeft(): JSX.Element {
  const { t } = useTranslation();

  return (
    <nav
      aria-label={t("home/sidebar.navigation.label")}
      className="hidden w-64 shrink-0 overflow-y-auto border-r px-3 py-4 lg:block"
    >
      <HomeNavigationList />
    </nav>
  );
}

interface HomeNavigationListProps {
  hidesSectionLabel?: boolean;
  onNavigate?: () => void;
}

export function HomeNavigationList(props: HomeNavigationListProps): JSX.Element {
  const { hidesSectionLabel = false, onNavigate } = props;

  const { t } = useTranslation();

  const { url } = usePage<SharedData>();

  const currentPath = url.split("?")[0];

  return (
    <div className="space-y-2">
      {!hidesSectionLabel && (
        <p className="px-3 text-xs font-medium text-muted-foreground">
          {t("home/sidebar.navigation.section")}
        </p>
      )}

      <ul className="space-y-0.5">
        {homeSidebarNavigationItems.map((navigationItem) => {
          const isCurrentPage = navigationItem.href === currentPath;

          const NavigationIcon = navigationItem.icon;

          return (
            <li key={navigationItem.href}>
              <Link
                aria-current={isCurrentPage ? "page" : undefined}
                className={cn(
                  "flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-foreground",
                  isCurrentPage &&
                    "bg-blue-50 text-blue-700 hover:bg-blue-100 hover:text-blue-800 dark:bg-blue-950/50 dark:text-blue-300 dark:hover:bg-blue-950 dark:hover:text-blue-200",
                )}
                href={navigationItem.href}
                onClick={onNavigate}
              >
                <NavigationIcon aria-hidden="true" className="size-4.5 shrink-0" />

                <span className="flex-1 truncate">{t(navigationItem.labelKey)}</span>

                {navigationItem.badge !== undefined && (
                  <NavigationBadge badge={navigationItem.badge} />
                )}
              </Link>
            </li>
          );
        })}
      </ul>
    </div>
  );
}

interface NavigationBadgeProps {
  badge: HomeSidebarBadge;
}

function NavigationBadge(props: NavigationBadgeProps): JSX.Element {
  const { badge } = props;

  const { t } = useTranslation();

  const { auth } = usePage<SharedData>().props;

  const hasStoredUnreadCount = useNotificationStore(
    (state) => state.authenticatedUserId === auth.user.id && state.unreadCount !== null,
  );

  if (badge === "unreadNotifications" && hasStoredUnreadCount) {
    return <UnreadNotificationsBadge />;
  }

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
      badge="pendingFriendRequests"
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
      badge="unreadNotifications"
      count={storeUnreadCount ?? unreadNotificationsCount ?? 0}
      labelKey="home/sidebar.navigation.unread_notifications_count"
    />
  );
}

interface CountBadgeProps {
  badge: HomeSidebarBadge;
  count: number;
  labelKey: string;
}

function CountBadge(props: CountBadgeProps): JSX.Element | null {
  const { badge, count, labelKey } = props;

  const { tChoice } = useTranslation();

  if (count <= 0) return null;

  return (
    <>
      <span
        aria-hidden="true"
        className={cn(
          "min-w-5 rounded-full px-1.5 text-center text-xs leading-5 font-semibold tabular-nums",
          BADGE_CLASS_NAMES[badge],
        )}
      >
        {count > 99 ? "99+" : count}
      </span>

      <span className="sr-only">{tChoice(labelKey, count, { count })}</span>
    </>
  );
}

export default HomeSidebarLeft;
