import type { JSX } from "react";

import { HomeLayout } from "../home";
import PostCard from "./components/PostCard";
import type { Post } from "./types/post";

interface ShowProps {
  post: Post;
}

function Show({ post }: ShowProps): JSX.Element {
  return (
    <HomeLayout>
      <PostCard post={post} />
    </HomeLayout>
  );
}

export default Show;
