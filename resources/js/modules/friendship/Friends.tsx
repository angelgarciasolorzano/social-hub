import type { JSX } from "react";
import { useId, useState } from "react";

import { Head, Link, router } from "@inertiajs/react";

import { Loader2Icon, SearchIcon, X } from "lucide-react";

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

import FriendshipListRow from "./components/FriendshipListRow";
import VirtualInfiniteList from "./components/VirtualInfiniteList";
import { useFriendsFilters } from "./hooks/useFriendsFilters";
import type {
  FriendshipListFilters,
  FriendshipListItemCollection,
  FriendshipListTab,
  FriendshipTabCounts,
} from "./types/friendshipList";

const ESTIMATED_ROW_HEIGHT = 88;

const FRIENDSHIP_LIST_TABS: FriendshipListTab[] = ["friends", "received", "sent"];

interface FriendsProps {
  filters: FriendshipListFilters;
  friendships: FriendshipListItemCollection;
  tabCounts: FriendshipTabCounts;
}

function Friends(props: FriendsProps): JSX.Element {
  const { filters, friendships, tabCounts } = props;

  const { t, tChoice } = useTranslation();

  const filterInputId = useId();

  const [scrollElement, setScrollElement] = useState<HTMLDivElement | null>(null);

  const { changeTab, committedFilters, isLoading, pendingTab, term, updateTerm } =
    useFriendsFilters(filters);

  const reloadCounters = (): void => {
    router.reload({ only: ["tabCounts", "friendRequests"] });
  };

  return (
    <HomeLayout contentRef={setScrollElement}>
      <Head title={t("friendship/friends.page.title")} />

      <main className="mx-auto flex w-full max-w-3xl flex-col gap-6 px-2 py-4 sm:px-4">
        <header className="space-y-1">
          <h1 className="text-2xl font-semibold tracking-tight">
            {t("friendship/friends.page.title")}
          </h1>

          <p className="text-sm text-muted-foreground">
            {t("friendship/friends.page.description")}
          </p>
        </header>

        <Tabs
          activationMode="manual"
          onValueChange={(value) => {
            changeTab(value as FriendshipListTab);
          }}
          value={committedFilters.tab}
        >
          <TabsList aria-label={t("friendship/friends.tabs.label")} className="w-full">
            {FRIENDSHIP_LIST_TABS.map((friendshipListTab) => (
              <TabsTrigger key={friendshipListTab} value={friendshipListTab}>
                {pendingTab === friendshipListTab && (
                  <Loader2Icon aria-hidden="true" className="size-4 animate-spin" />
                )}

                {t(`friendship/friends.tabs.${friendshipListTab}`)}

                <span
                  aria-hidden="true"
                  className="rounded-full bg-muted px-1.5 text-xs text-muted-foreground tabular-nums"
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

          <InputGroup className="mt-4">
            <label className="sr-only" htmlFor={filterInputId}>
              {t("friendship/friends.filter.label")}
            </label>

            <InputGroupAddon>
              {isLoading && pendingTab === null ? (
                <Loader2Icon aria-hidden="true" className="size-4 animate-spin" />
              ) : (
                <SearchIcon aria-hidden="true" className="size-4" />
              )}
            </InputGroupAddon>

            <InputGroupInput
              autoComplete="off"
              id={filterInputId}
              name="q"
              onChange={(event) => {
                updateTerm(event.target.value);
              }}
              placeholder={t("friendship/friends.filter.placeholder")}
              type="text"
              value={term}
            />

            {term !== "" && (
              <InputGroupAddon align="inline-end">
                <InputGroupButton
                  aria-label={t("friendship/friends.filter.clear")}
                  onClick={() => {
                    updateTerm("");
                  }}
                  size="icon-xs"
                >
                  <X />
                </InputGroupButton>
              </InputGroupAddon>
            )}
          </InputGroup>

          {isLoading && (
            <p className="sr-only" role="status">
              {t("friendship/friends.states.loading")}
            </p>
          )}

          {FRIENDSHIP_LIST_TABS.map((friendshipListTab) => (
            <TabsContent className="mt-4" key={friendshipListTab} value={friendshipListTab}>
              {friendships.data.length === 0 ? (
                <FriendsEmptyState committedFilters={committedFilters} />
              ) : (
                <VirtualInfiniteList
                  data="friendships"
                  estimateSize={ESTIMATED_ROW_HEIGHT}
                  getItemKey={(friendshipListItem) => friendshipListItem.friendshipId}
                  items={friendships.data}
                  loadingMoreLabel={t("friendship/friends.states.loading_more")}
                  renderItem={(friendshipListItem) => (
                    <FriendshipListRow
                      friendshipListItem={friendshipListItem}
                      onFriendshipChange={reloadCounters}
                    />
                  )}
                  scrollElement={scrollElement}
                />
              )}
            </TabsContent>
          ))}
        </Tabs>
      </main>
    </HomeLayout>
  );
}

interface FriendsEmptyStateProps {
  committedFilters: FriendshipListFilters;
}

function FriendsEmptyState(props: FriendsEmptyStateProps): JSX.Element {
  const { committedFilters } = props;

  const { t } = useTranslation();

  if (committedFilters.q !== "") {
    return (
      <FriendsStateMessage
        description={t("friendship/friends.states.filtered_empty_description")}
        title={t("friendship/friends.states.filtered_empty_title", { term: committedFilters.q })}
      />
    );
  }

  return (
    <FriendsStateMessage
      description={t(`friendship/friends.states.${committedFilters.tab}_empty_description`)}
      title={t(`friendship/friends.states.${committedFilters.tab}_empty_title`)}
    >
      {committedFilters.tab !== "received" && (
        <Button asChild className="mt-4" size="sm" variant="secondary">
          <Link href={search()}>
            <SearchIcon aria-hidden="true" className="size-4" />
            {t("friendship/friends.states.find_people")}
          </Link>
        </Button>
      )}
    </FriendsStateMessage>
  );
}

interface FriendsStateMessageProps {
  children?: React.ReactNode;
  description: string;
  title: string;
}

function FriendsStateMessage(props: FriendsStateMessageProps): JSX.Element {
  const { children, description, title } = props;

  return (
    <div className="rounded-xl border border-dashed px-6 py-12 text-center">
      <h2 className="text-lg font-medium">{title}</h2>

      <p className="mx-auto mt-2 max-w-md text-sm text-muted-foreground">{description}</p>

      {children}
    </div>
  );
}

export default Friends;
