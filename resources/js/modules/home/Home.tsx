import type { JSX } from "react";
import { useState } from "react";

import { Link } from "@inertiajs/react";

import { SearchIcon } from "lucide-react";

import { search } from "@/shared/wayfinder/routes/friendships";

import { Button } from "@/shared/components/shadcn/ui/button";
import VirtualInfiniteList from "@/shared/components/VirtualInfiniteList";

import { useTranslation } from "@/shared/hooks";

import { PostCard, type PostCollection } from "../post";
import HomeLayout from "./layouts/HomeLayout";

const ESTIMATED_POST_HEIGHT = 240;

interface HomeProps {
  posts: PostCollection;
}

function Home(props: HomeProps): JSX.Element {
  const { posts } = props;

  const { t } = useTranslation();

  const [scrollElement, setScrollElement] = useState<HTMLDivElement | null>(null);

  return (
    <HomeLayout contentRef={setScrollElement}>
      {posts.data.length === 0 ? (
        <HomeFeedEmptyState />
      ) : (
        <VirtualInfiniteList
          data="posts"
          endLabel={t("home/feed.states.end")}
          estimateSize={ESTIMATED_POST_HEIGHT}
          getItemKey={(post) => post.id}
          items={posts.data}
          loadingMoreLabel={t("home/feed.states.loading_more")}
          renderItem={(post) => <PostCard post={post} />}
          scrollElement={scrollElement}
        />
      )}
    </HomeLayout>
  );
}

function HomeFeedEmptyState(): JSX.Element {
  const { t } = useTranslation();

  return (
    <div className="rounded-xl border border-dashed px-6 py-12 text-center">
      <h2 className="text-lg font-medium">{t("home/feed.states.empty_title")}</h2>

      <p className="mx-auto mt-2 max-w-md text-sm text-muted-foreground">
        {t("home/feed.states.empty_description")}
      </p>

      <Button asChild className="mt-4" size="sm" variant="secondary">
        <Link href={search()}>
          <SearchIcon aria-hidden="true" className="size-4" />
          {t("home/feed.states.find_people")}
        </Link>
      </Button>
    </div>
  );
}

export default Home;
