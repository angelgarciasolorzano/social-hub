import type { JSX, Key, ReactNode } from "react";
import { useLayoutEffect, useRef, useState } from "react";

import { InfiniteScroll } from "@inertiajs/react";

import { useVirtualizer } from "@tanstack/react-virtual";

interface VirtualInfiniteListProps<Item> {
  data: string;
  estimateSize: number;
  getItemKey: (item: Item) => Key;
  items: Item[];
  loadingMoreLabel: string;
  renderItem: (item: Item) => ReactNode;
  scrollElement: HTMLDivElement | null;
}

function VirtualInfiniteList<Item>(props: VirtualInfiniteListProps<Item>): JSX.Element {
  const { data, estimateSize, getItemKey, items, loadingMoreLabel, renderItem, scrollElement } =
    props;

  const listRef = useRef<HTMLDivElement | null>(null);

  const [scrollMargin, setScrollMargin] = useState<number>(0);

  useLayoutEffect(() => {
    if (scrollElement === null || listRef.current === null) return;

    setScrollMargin(
      listRef.current.getBoundingClientRect().top -
        scrollElement.getBoundingClientRect().top +
        scrollElement.scrollTop,
    );
  }, [scrollElement]);

  const rowVirtualizer = useVirtualizer<HTMLDivElement, Element>({
    count: items.length,
    getScrollElement: () => scrollElement,
    estimateSize: () => estimateSize,
    overscan: 5,
    scrollMargin,
    getItemKey: (index) => {
      const item = items[index];

      return item === undefined ? index : getItemKey(item);
    },
  });

  return (
    <InfiniteScroll buffer={200} data={data} onlyNext preserveUrl>
      {({ loadingNext }) => (
        <>
          <div
            className="relative w-full"
            ref={listRef}
            style={{ height: `${rowVirtualizer.getTotalSize()}px` }}
          >
            {rowVirtualizer.getVirtualItems().map((virtualRow) => {
              const item = items[virtualRow.index];

              if (item === undefined) {
                return null;
              }

              return (
                <div
                  className="absolute top-0 left-0 w-full pb-3"
                  data-index={virtualRow.index}
                  key={virtualRow.key}
                  ref={rowVirtualizer.measureElement}
                  style={{ transform: `translateY(${virtualRow.start - scrollMargin}px)` }}
                >
                  {renderItem(item)}
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
              {loadingMoreLabel}
            </p>
          )}
        </>
      )}
    </InfiniteScroll>
  );
}

export default VirtualInfiniteList;
