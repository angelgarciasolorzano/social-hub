import type { JSX } from "react";
import { useEffect, useId, useRef, useState } from "react";

import { Head, Link, router } from "@inertiajs/react";

import { KeyboardIcon, RefreshCwIcon, SearchIcon, UsersRoundIcon, X } from "lucide-react";

import { HomeLayout } from "@/modules/home";

import { search } from "@/shared/wayfinder/routes/friendships";

import { Button } from "@/shared/components/shadcn/ui/button";
import {
  InputGroup,
  InputGroupAddon,
  InputGroupButton,
  InputGroupInput,
} from "@/shared/components/shadcn/ui/input-group";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/shared/components/shadcn/ui/tabs";

import { useTranslation } from "@/shared/hooks";

import { iconColorVariants } from "@/shared/lib/styling";
import { cn } from "@/shared/lib/utils";

import FriendRequestsHighlight from "./components/FriendRequestsHighlight";
import FriendsEmptyState from "./components/FriendsEmptyState";
import FriendshipCardGrid from "./components/FriendshipCardGrid";
import FriendsKeyboardShortcutsDialog from "./components/FriendsKeyboardShortcutsDialog";
import FriendsSortPopover from "./components/FriendsSortPopover";
import { useFriendsFilters } from "./hooks/useFriendsFilters";
import { useFriendsShortcuts } from "./hooks/useFriendsShortcuts";
import type {
  FriendshipListFilters,
  FriendshipListItem,
  FriendshipListItemCollection,
  FriendshipListTab,
  FriendshipTabCounts,
} from "./types/friendshipList";
import type { FriendshipState } from "./types/friendshipSearch";

const FRIENDSHIP_LIST_TABS: FriendshipListTab[] = ["friends", "received", "sent"];

const FRIENDSHIP_STATE_OF_TAB: Record<FriendshipListTab, FriendshipState> = {
  friends: "friends",
  received: "request_received",
  sent: "request_sent",
};

const TAB_COUNT_CLASS_NAMES: Record<FriendshipListTab, string> = {
  friends: "bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-100",
  received: "bg-orange-100 text-orange-800 dark:bg-orange-900/60 dark:text-orange-100",
  sent: "bg-slate-200 text-slate-800 dark:bg-slate-700 dark:text-slate-100",
};

interface HiddenFriendships {
  friendshipIds: number[];
  listKey: string;
}

interface FriendsProps {
  filters: FriendshipListFilters;
  friendships: FriendshipListItemCollection;
  tabCounts: FriendshipTabCounts;
}

function Friends(props: FriendsProps): JSX.Element {
  const { filters, friendships, tabCounts } = props;

  const { t, tChoice } = useTranslation();

  const filterInputId = useId();

  const [filterInput, setFilterInput] = useState<HTMLInputElement | null>(null);

  const [isShortcutsDialogOpen, setIsShortcutsDialogOpen] = useState<boolean>(false);

  const [isReloading, setIsReloading] = useState<boolean>(false);

  const tabChangedByShortcutRef = useRef<boolean>(false);

  const focusFilter = (): void => {
    filterInput?.focus();
  };

  const focusFilterAfterTabLoad = (): void => {
    if (tabChangedByShortcutRef.current) {
      tabChangedByShortcutRef.current = false;

      return;
    }

    focusFilter();
  };

  const { changeSort, changeTab, committedFilters, isLoading, loadingTab, sort, term, updateTerm } =
    useFriendsFilters(filters, { onTabLoaded: focusFilterAfterTabLoad });

  useEffect(() => {
    filterInput?.focus();
  }, [filterInput]);

  useFriendsShortcuts({
    enabled: !isShortcutsDialogOpen,
    filterInput,
    onChangeTab: (tab) => {
      tabChangedByShortcutRef.current = tab !== (loadingTab ?? committedFilters.tab);

      changeTab(tab);
    },
    onFocusFilter: focusFilter,
    onOpenHelp: () => {
      setIsShortcutsDialogOpen(true);
    },
    onToggleSort: () => {
      changeSort(sort === "recent" ? "name" : "recent");
    },
  });

  const listKey = `${committedFilters.tab}:${committedFilters.q}:${committedFilters.sort}`;

  const [hiddenFriendships, setHiddenFriendships] = useState<HiddenFriendships>({
    friendshipIds: [],
    listKey,
  });

  const hiddenFriendshipIds =
    hiddenFriendships.listKey === listKey ? hiddenFriendships.friendshipIds : [];

  const visibleFriendships = friendships.data.filter(
    (friendshipListItem) => !hiddenFriendshipIds.includes(friendshipListItem.friendshipId),
  );

  const reloadedEmptyListRef = useRef<FriendshipListItem[] | null>(null);

  const hidesEveryLoadedRow = friendships.data.length > 0 && visibleFriendships.length === 0;

  useEffect(() => {
    if (!hidesEveryLoadedRow || reloadedEmptyListRef.current === friendships.data) return;

    reloadedEmptyListRef.current = friendships.data;

    router.reload({ only: ["friendships"], reset: ["friendships"] });
  }, [friendships.data, hidesEveryLoadedRow]);

  const handleFriendshipChange = (
    friendshipListItem: FriendshipListItem,
    friendshipState: FriendshipState,
  ): void => {
    if (friendshipState !== FRIENDSHIP_STATE_OF_TAB[committedFilters.tab]) {
      setHiddenFriendships((currentHiddenFriendships) => ({
        friendshipIds: [
          ...(currentHiddenFriendships.listKey === listKey
            ? currentHiddenFriendships.friendshipIds
            : []),
          friendshipListItem.friendshipId,
        ],
        listKey,
      }));
    }

    router.reload({ async: true, only: ["tabCounts", "friendRequests"] });
  };

  const selectedTab = loadingTab ?? committedFilters.tab;

  const reloadFriendships = (): void => {
    router.reload({
      only: ["friendships", "tabCounts", "friendRequests"],
      onFinish: () => {
        setIsReloading(false);
      },
      onStart: () => {
        setIsReloading(true);
      },
      reset: ["friendships"],
    });
  };

  return (
    <HomeLayout>
      <Head title={t("friendship/friends.page.title")} />

      <main className="@container/friends mx-auto flex w-full max-w-6xl flex-col gap-5 pb-4">
        <header className="flex flex-wrap items-center gap-4">
          <div
            aria-hidden="true"
            className={cn(
              "flex size-12 shrink-0 items-center justify-center rounded-xl ring-1 ring-blue-600/10 dark:ring-blue-400/15",
              iconColorVariants.blue.iconBgClass,
            )}
          >
            <UsersRoundIcon className={cn("size-6", iconColorVariants.blue.iconFgClass)} />
          </div>

          <div className="min-w-0 flex-1 space-y-1">
            <h1 className="text-2xl font-bold tracking-tight">
              {t("friendship/friends.page.title")}
            </h1>

            <p className="text-sm text-muted-foreground">
              {t("friendship/friends.page.description")}
            </p>
          </div>

          <div className="flex w-full gap-2 @xl/friends:w-auto">
            <Button asChild className="flex-1 @xl/friends:flex-none" variant="outline">
              <Link href={search()}>
                <SearchIcon aria-hidden="true" />

                {t("friendship/friends.states.find_people")}
              </Link>
            </Button>

            <Button
              aria-label={t("friendship/friends.shortcuts.open")}
              className="hidden shrink-0 md:inline-flex"
              onClick={() => {
                setIsShortcutsDialogOpen(true);
              }}
              size="icon"
              title={t("friendship/friends.shortcuts.open")}
              type="button"
              variant="outline"
            >
              <KeyboardIcon aria-hidden="true" />
            </Button>
          </div>
        </header>

        <FriendRequestsHighlight
          onViewAll={() => {
            changeTab("received");
          }}
        />

        <Tabs
          activationMode="manual"
          className="gap-0"
          onValueChange={(value) => {
            changeTab(value as FriendshipListTab);
          }}
          value={selectedTab}
        >
          <div className="sticky -top-4 z-10 -mx-1 -mt-4 flex flex-col gap-3 bg-background px-1 pt-5 pb-3 @4xl/friends:flex-row @4xl/friends:items-end @4xl/friends:justify-between">
            <TabsList
              aria-label={t("friendship/friends.tabs.label")}
              className="h-auto w-full justify-start gap-5 border-b p-0 @4xl/friends:w-fit"
              variant="line"
            >
              {FRIENDSHIP_LIST_TABS.map((friendshipListTab) => (
                <TabsTrigger
                  className="flex-none px-0.5 pb-2.5 after:bg-blue-600 group-data-[orientation=horizontal]/tabs:after:bottom-[-1.5px] data-[state=active]:text-blue-700 dark:data-[state=active]:text-blue-300"
                  key={friendshipListTab}
                  value={friendshipListTab}
                >
                  {t(`friendship/friends.tabs.${friendshipListTab}`)}

                  <span
                    aria-hidden="true"
                    className={cn(
                      "min-w-5 rounded-full px-1.5 text-xs font-semibold tabular-nums",
                      TAB_COUNT_CLASS_NAMES[friendshipListTab],
                    )}
                  >
                    {tabCounts[friendshipListTab]}
                  </span>

                  <span className="sr-only">
                    {tChoice(
                      `friendship/friends.tabs.${friendshipListTab}_count`,
                      tabCounts[friendshipListTab],
                      { count: tabCounts[friendshipListTab] },
                    )}
                  </span>
                </TabsTrigger>
              ))}
            </TabsList>

            <div className="flex w-full flex-wrap gap-2 @4xl/friends:w-auto @4xl/friends:flex-nowrap">
              <InputGroup className="w-full @md/friends:w-auto @md/friends:flex-1 @4xl/friends:w-64 @4xl/friends:flex-none">
                <label className="sr-only" htmlFor={filterInputId}>
                  {t("friendship/friends.filter.label")}
                </label>

                <InputGroupAddon>
                  <SearchIcon aria-hidden="true" className="size-4" />
                </InputGroupAddon>

                <InputGroupInput
                  autoComplete="off"
                  id={filterInputId}
                  name="q"
                  onChange={(event) => {
                    updateTerm(event.target.value);
                  }}
                  placeholder={t("friendship/friends.filter.placeholder")}
                  ref={setFilterInput}
                  type="text"
                  value={term}
                />

                {term !== "" && (
                  <InputGroupAddon align="inline-end">
                    <InputGroupButton
                      aria-label={t("friendship/friends.filter.clear")}
                      onClick={() => {
                        updateTerm("");
                        focusFilter();
                      }}
                      size="icon-xs"
                    >
                      <X />
                    </InputGroupButton>
                  </InputGroupAddon>
                )}
              </InputGroup>

              <FriendsSortPopover
                className="flex-1 @md/friends:flex-none"
                onSortChange={changeSort}
                sort={sort}
              />

              <Button
                aria-label={t("friendship/friends.toolbar.reload")}
                className="shrink-0"
                disabled={isReloading}
                onClick={reloadFriendships}
                size="icon"
                title={t("friendship/friends.toolbar.reload")}
                type="button"
                variant="outline"
              >
                <RefreshCwIcon aria-hidden="true" className={cn(isReloading && "animate-spin")} />
              </Button>
            </div>
          </div>

          {FRIENDSHIP_LIST_TABS.map((friendshipListTab) => (
            <TabsContent className="mt-3" key={friendshipListTab} value={friendshipListTab}>
              {!isLoading && visibleFriendships.length === 0 ? (
                <FriendsEmptyState committedFilters={committedFilters} />
              ) : (
                <FriendshipCardGrid
                  friendshipListItems={visibleFriendships}
                  isLoadingTab={isLoading}
                  onFriendshipChange={handleFriendshipChange}
                />
              )}
            </TabsContent>
          ))}
        </Tabs>
      </main>

      <FriendsKeyboardShortcutsDialog
        onOpenChange={setIsShortcutsDialogOpen}
        open={isShortcutsDialogOpen}
      />
    </HomeLayout>
  );
}

export default Friends;
