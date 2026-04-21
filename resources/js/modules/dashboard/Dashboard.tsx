import { InfiniteScroll } from "@inertiajs/react";

import type { User } from "@/shared/types";

import type { PostCollection } from "../post";
import { PostCard } from "../post";
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
          <PostCard key={post.id} post={post} user={user} />
        ))}
      </InfiniteScroll>
    </DashboardLayout>
  );
}

export default Dashboard;
