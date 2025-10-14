import { usePage } from '@inertiajs/react';

import { PublicationCard } from '@/components/publication';

import { SharedData } from '@/types';

import { HomeLayout } from '@/layouts';

function Home() {
  const { posts, user } = usePage<SharedData>().props;
  
  return (
    <HomeLayout>
      {posts.data.map((post) => (
        <PublicationCard key={post.id} post={post} user={user} />
      ))}
    </HomeLayout>
  )
}

export default Home
