import { SharedData } from '@/types';
import { usePage } from '@inertiajs/react'
import { MdOutlineEditNote } from "react-icons/md";
import { GoPlus } from "react-icons/go";

import HomeLayout from '@/layouts/home/home-layout'
import Publication from '@/components/home/Publication';
import { Button } from '@/components/ui/button';

function Profile() {
  const { user, posts, auth } = usePage<SharedData>().props;

  return (
    <HomeLayout>
      <div className='relative py-3 px-4'>
        <div className='w-full h-[300px] bg-amber-300 rounded-md'></div>

        <div className='absolute left-10 -bottom-17 z-50'>
          <div className='rounded-full w-24 h-24 bg-red-400'></div>

          <h4 className='font-bold text-lg mt-1'>{user.name}</h4>
          <span className='text-sm text-gray-600'>344 amigos</span>
        </div>
      </div>

      <div className='flex items-center gap-4 pt-16 px-4'>
        {auth.user.id === user.id && (
          <>
            <Button className='bg-blue-700 cursor-pointer hover:bg-blue-800'>
              <GoPlus className='w-4 h-4' />
              Agregar publicación
            </Button>

            <Button variant="secondary" className='cursor-pointer'>
              <MdOutlineEditNote className='w-4 h-4' />
              Editar perfil
            </Button>
          </>
        )}
      </div>

      <div className='pb-6'>
        {posts.data.map((post) => (
          <Publication {...post} key={post.id} />
        ))}
      </div>
    </HomeLayout>
  )
}

export default Profile
