import { InfiniteScroll } from "@inertiajs/react";

import { PublicationCard } from "@/features/publication";

import type { PostCollection, User } from "@/shared/types";

import DashboardLayout from "./layouts/DashboadLayout";

interface HomeProps {
  posts: PostCollection;
  user: User;
}

function Dashboard({ posts, user }: HomeProps) {
  return (
    <DashboardLayout>
      <InfiniteScroll buffer={200} data="posts" preserveUrl>
        {posts.data.map((post) => (
          <PublicationCard key={post.id} post={post} user={user} />
        ))}
      </InfiniteScroll>
    </DashboardLayout>
  );
}

export default Dashboard;
