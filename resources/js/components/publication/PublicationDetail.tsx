import { Post, SharedData } from '@/types';
import { Avatar, AvatarFallback, AvatarImage } from '../ui/avatar';
import { RiTimeZoneLine } from "react-icons/ri";

interface PublicationDetailProps {
  user: SharedData["user"];
  post: Omit<Post, 'comments'>;
};

function PublicationDetail({ user, post }: PublicationDetailProps) {
  return (
    <article className='grid gap-2'>
      <header className='flex items-center gap-3'>
        <Avatar>
          <AvatarImage 
            src='https://picsum.photos/200'
            alt='Foto de perfil'
          />

          <AvatarFallback>Foto de perfil</AvatarFallback>
        </Avatar>

        <div className='flex flex-col gap-0.5'>
          <div className='flex items-baseline gap-1'>
            <h4 className='font-bold text-l dark:text-white'>
              {user.name}
            </h4>
            <span className='text-sm font-medium text-gray-600 dark:text-gray-400'>agregó una publicación</span>
          </div>

          <time className='flex items-center gap-1 text-gray-500 text-xs dark:text-gray-400'>
            <RiTimeZoneLine className='w-4 h-4 text-gray-600 dark:text-gray-500' />
            {post.createdAt}
          </time>
        </div>
      </header>

      <section>
        <p className='text-gray-700 text-sm dark:text-gray-200'>{post.content}</p>

        {post.image && (
          <img
            src={post.image}
            alt="Imagen de la publicación"
            className='w-full rounded-md object-cover my-2 max-h-[400px]'
          />
        )}
      </section>
    </article>
  )
}

export default PublicationDetail;