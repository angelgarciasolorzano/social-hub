import { useState } from "react";

import { InfiniteScroll } from "@inertiajs/react";

import { useVirtualizer } from "@tanstack/react-virtual";

import type { User } from "@/shared/types";

import { PostCard, type PostCollection } from "../post";
import HomeLayout from "./layouts/HomeLayout";

interface HomeProps {
  posts: PostCollection;
  user: User;
}

function Home({ posts, user }: HomeProps) {
  const [scrollElement, setScrollElement] = useState<HTMLDivElement | null>(null);

  const rowVirtualizer = useVirtualizer({
    count: posts.data.length,
    getScrollElement: () => scrollElement,
    estimateSize: () => 240,
    overscan: 5,
    getItemKey: (index) => posts.data[index]?.id ?? index,
  });

  return (
    <HomeLayout contentRef={setScrollElement}>
      <InfiniteScroll buffer={200} data="posts" preserveUrl>
        <div className="relative w-full" style={{ height: `${rowVirtualizer.getTotalSize()}px` }}>
          {rowVirtualizer.getVirtualItems().map((virtualRow) => {
            const post = posts.data[virtualRow.index];

            if (!post) {
              return null;
            }

            return (
              <div
                className="absolute left-0 top-0 w-full"
                data-index={virtualRow.index}
                key={virtualRow.key}
                ref={rowVirtualizer.measureElement}
                style={{
                  transform: `translateY(${virtualRow.start}px)`,
                }}
              >
                <div className="pb-6">
                  <PostCard post={post} user={user} />
                </div>
              </div>
            );
          })}
        </div>
      </InfiniteScroll>
    </HomeLayout>
  );
}

export default Home;
