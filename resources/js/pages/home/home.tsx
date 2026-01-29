import { PublicationCard } from "@/features/publication";

import { PostData, User } from "@/types";

import HomeLayout from "./layouts/HomeLayout";

interface HomeProps {
  posts: PostData;
  user: User;
}

function Home({ posts, user }: HomeProps) {
  return (
    <HomeLayout>
      {posts.data.map((post) => (
        <PublicationCard key={post.id} post={post} user={user} />
      ))}
    </HomeLayout>
  );
}

export default Home;
