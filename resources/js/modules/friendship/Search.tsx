import type { JSX } from "react";
import { useId, useState } from "react";

import { Head, InfiniteScroll } from "@inertiajs/react";

import { useVirtualizer } from "@tanstack/react-virtual";
import { Loader2Icon, SearchIcon, X } from "lucide-react";

import { HomeLayout } from "@/modules/home";

import {
  InputGroup,
  InputGroupAddon,
  InputGroupButton,
  InputGroupInput,
} from "@/shared/components/shadcn/ui/input-group";

import { useTranslation } from "@/shared/hooks";

import FriendshipSearchResultRow from "./components/FriendshipSearchResultRow";
import { useFriendshipSearch } from "./hooks/useFriendshipSearch";
import type { FriendSearchFilters, FriendSearchResultCollection } from "./types/friendshipSearch";

const ESTIMATED_ROW_HEIGHT = 88;

interface SearchProps {
  filters: FriendSearchFilters;
  searchResults: FriendSearchResultCollection;
  searchTermMinLength: number;
}

/**
 * The people search page: the term lives in the URL, the results load by cursor as the page
 * scrolls and only the rows near the viewport are rendered.
 */
function Search({ filters, searchResults, searchTermMinLength }: SearchProps): JSX.Element {
  const { t } = useTranslation();
  const searchInputId = useId();
  const [scrollElement, setScrollElement] = useState<HTMLDivElement | null>(null);
  const { committedTerm, isSearching, term, updateTerm } = useFriendshipSearch(filters.q);

  const hasSearchableTerm = committedTerm.length >= searchTermMinLength;

  return (
    <HomeLayout contentRef={setScrollElement}>
      <Head title={t("friendship/search.page.title")} />

      <main className="mx-auto flex w-full max-w-3xl flex-col gap-6 px-2 py-4 sm:px-4">
        <header className="space-y-1">
          <h1 className="text-2xl font-semibold tracking-tight">
            {t("friendship/search.page.title")}
          </h1>
          <p className="text-sm text-muted-foreground">{t("friendship/search.page.description")}</p>
        </header>

        <InputGroup>
          <label className="sr-only" htmlFor={searchInputId}>
            {t("friendship/search.form.label")}
          </label>

          <InputGroupAddon>
            {isSearching ? (
              <Loader2Icon aria-hidden="true" className="size-4 animate-spin" />
            ) : (
              <SearchIcon aria-hidden="true" className="size-4" />
            )}
          </InputGroupAddon>

          <InputGroupInput
            autoComplete="off"
            id={searchInputId}
            onChange={(event) => {
              updateTerm(event.target.value);
            }}
            placeholder={t("friendship/search.form.placeholder")}
            type="search"
            value={term}
          />

          {term !== "" && (
            <InputGroupAddon align="inline-end">
              <InputGroupButton
                aria-label={t("friendship/search.form.clear")}
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

        {isSearching && (
          <p className="sr-only" role="status">
            {t("friendship/search.states.loading")}
          </p>
        )}

        {!hasSearchableTerm ? (
          <SearchStateMessage
            description={t("friendship/search.states.prompt_description", {
              min: searchTermMinLength,
            })}
            title={t("friendship/search.states.prompt_title")}
          />
        ) : searchResults.data.length === 0 ? (
          <SearchStateMessage
            description={t("friendship/search.states.empty_description")}
            title={t("friendship/search.states.empty_title", { term: committedTerm })}
          />
        ) : (
          <VirtualSearchResults scrollElement={scrollElement} searchResults={searchResults} />
        )}
      </main>
    </HomeLayout>
  );
}

interface VirtualSearchResultsProps {
  scrollElement: HTMLDivElement | null;
  searchResults: FriendSearchResultCollection;
}

/**
 * The results list, loaded page by page with `InfiniteScroll` and virtualized like the Home feed.
 */
function VirtualSearchResults({
  scrollElement,
  searchResults,
}: VirtualSearchResultsProps): JSX.Element {
  const { t } = useTranslation();

  const rowVirtualizer = useVirtualizer<HTMLDivElement, Element>({
    count: searchResults.data.length,
    getScrollElement: () => scrollElement,
    estimateSize: () => ESTIMATED_ROW_HEIGHT,
    overscan: 5,
    getItemKey: (index) => searchResults.data[index]?.id ?? index,
  });

  return (
    <InfiniteScroll buffer={200} data="searchResults" onlyNext preserveUrl>
      {({ loadingNext }) => (
        <>
          <div className="relative w-full" style={{ height: `${rowVirtualizer.getTotalSize()}px` }}>
            {rowVirtualizer.getVirtualItems().map((virtualRow) => {
              const searchResult = searchResults.data[virtualRow.index];

              if (!searchResult) {
                return null;
              }

              return (
                <div
                  className="absolute top-0 left-0 w-full pb-3"
                  data-index={virtualRow.index}
                  key={virtualRow.key}
                  ref={rowVirtualizer.measureElement}
                  style={{ transform: `translateY(${virtualRow.start}px)` }}
                >
                  <FriendshipSearchResultRow searchResult={searchResult} />
                </div>
              );
            })}
          </div>

          {loadingNext && (
            <p
              aria-live="polite"
              className="py-4 text-center text-sm text-muted-foreground"
              role="status"
            >
              {t("friendship/search.states.loading_more")}
            </p>
          )}
        </>
      )}
    </InfiniteScroll>
  );
}

interface SearchStateMessageProps {
  description: string;
  title: string;
}

/**
 * The message shown instead of results: before searching or when nobody matches.
 */
function SearchStateMessage({ description, title }: SearchStateMessageProps): JSX.Element {
  return (
    <div className="rounded-xl border border-dashed px-6 py-12 text-center">
      <h2 className="text-lg font-medium">{title}</h2>
      <p className="mx-auto mt-2 max-w-md text-sm text-muted-foreground">{description}</p>
    </div>
  );
}

export default Search;
