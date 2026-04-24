import { InfiniteScroll } from "@inertiajs/react";

import type { User } from "@/shared/types";

import { PostCard, type PostCollection } from "../post";
import DashboardLayout from "./layouts/DashboadLayout";

interface HomeProps {
  posts: PostCollection;
  user: User;
}

function Dashboard({ posts, user }: HomeProps) {
  return (
    <DashboardLayout>
      <InfiniteScroll buffer={200} data="posts" preserveUrl>
        <div className="flex flex-col gap-6">
          {posts.data.map((post) => (
            <PostCard key={post.id} post={post} user={user} />
          ))}
        </div>
      </InfiniteScroll>
    </DashboardLayout>
  );
}

export default Dashboard;
