import Publication from '@/components/home/Publication';
import HomeLayout from '@/layouts/home/home-layout';
import { PostData } from '@/types';
import { usePage } from '@inertiajs/react';

function Dashboard() {
  const { posts } = usePage<{ posts: PostData }>().props;

  return (
    <HomeLayout>
      {posts.data.map((post) => (
        <Publication {...post} key={post.id} />
      ))}
    </HomeLayout>
  )
}

export default Dashboard;