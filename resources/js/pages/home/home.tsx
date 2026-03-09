import { InfiniteScroll } from "@inertiajs/react";

import { PublicationCard } from "@/features/publication";

import type { PostCollection, User } from "@/types";

import HomeLayout from "./layouts/HomeLayout";

interface HomeProps {
  posts: PostCollection;
  user: User;
}

function Home({ posts, user }: HomeProps) {
  return (
    <HomeLayout>
      <InfiniteScroll buffer={200} data="posts" preserveUrl>
        {posts.data.map((post) => (
          <PublicationCard key={post.id} post={post} user={user} />
        ))}
      </InfiniteScroll>
    </HomeLayout>
  );
}

export default Home;
