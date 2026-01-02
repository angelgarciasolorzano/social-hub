import { useModal } from '@/hooks/useModal';
import { PostData, SharedData, User } from '@/types';
import { usePage } from '@inertiajs/react'
import { MdOutlineEditNote } from "react-icons/md";
import { GoPlus } from "react-icons/go";
import PublicationForm from '@/components/publication/PublicationForm';

import HomeLayout from '@/pages/home/layouts/HomeLayout'
import { Button } from '@/components/ui/button';
import PublicationCard from '@/components/publication/PublicationCard';
import ProfilePicture from './components/ProfilePicture';
import ProfileCover from '@/pages/profile/components/ProfileCover';

interface ProfileProps {
  user: User;
  posts: PostData;
};

function Profile({ user, posts }: ProfileProps) {
  const { open, setOpen } = useModal();
  const { auth } = usePage<SharedData>().props;

  return (
    <HomeLayout>
      <div className='py-3 px-4 flex flex-col gap-4'>
        <div className='relative'>
          <ProfileCover profilePicture={user.cover_image} />
          <ProfilePicture profilePicture={user.avatar} />
        </div>

        <div className='pt-8 px-6'>
          <h4 className='font-bold text-lg dark:text-white'>{user.name}</h4>
          <span className='text-sm text-gray-600 dark:text-gray-400 font-medium'>344 amigos</span>
        </div>

        <div className='flex items-center gap-4'>
          {auth.user.id === user.id && (
            <>
              <Button 
                className='bg-blue-700 cursor-pointer hover:bg-blue-800 dark:bg-blue-600 dark:text-white dark:hover:bg-blue-700'
                onClick={() => setOpen(true)}
              >
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

        <div className='flex flex-col gap-8'>
          {posts.data.map((post) => (
            <PublicationCard key={post.id} post={post} user={user} />
          ))}
        </div>
      </div>

      <PublicationForm open={open} setOpen={setOpen} />
    </HomeLayout>
  )
}

export default Profile;
