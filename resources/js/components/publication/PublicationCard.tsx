import React from 'react'
import { Avatar, AvatarFallback, AvatarImage } from '../ui/avatar';
import { RiTimeZoneLine } from "react-icons/ri";
import { Button } from '../ui/button';
import { Separator } from '../ui/separator';
import { FaRegHeart } from "react-icons/fa6";
import { FaRegComment } from "react-icons/fa";
import CommentModal from '../comment/CommentModal';
import { Post, SharedData } from '@/types';

interface PublicacionCardProps {
  post: Post;
  user: SharedData["user"];
};

function PublicationCard({ post, user }: PublicacionCardProps) {
  const { comments, ...postDetail } = post;

  return (
    <article className='grid gap-2 border rounded-lg shadow-md p-3 dark:border-gray-700'>
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
            <h4 className='font-bold text-lg dark:text-white'>
              {user.name}
            </h4>

            <span className='text-sm font-medium text-gray-600 dark:text-gray-400'>
              agregó una publicación
            </span>
          </div>

          <time className='flex items-center gap-1 text-gray-500 text-xs dark:text-gray-400'>
            <RiTimeZoneLine className='w-4 h-4 text-gray-600 dark:text-gray-500' />
            12 de agosto de 2022
          </time>
        </div>
      </header>

      <section>
        <p className='text-gray-700 text-[15px] dark:text-gray-200'>{post.content}</p>

        {post.image && (
           <img
            src={post.image}
            alt="Imagen de la publicación"
            className='w-full rounded-md object-cover max-h-[400px] my-2'
          />
        )}
      </section>

      <div className='flex items-center justify-between text-gray-500 text-sm dark:text-gray-400'>
        <span className='flex items-center gap-1'>
          <FaRegHeart className='w-4 h-4' />
          122 me gustas
        </span>

        <span className='flex items-center gap-1'>
          <FaRegComment className='w-4 h-4' />
          6000 comentarios
        </span>
      </div>

      <Separator className='my-1 dark:bg-gray-700' />

      <footer className='flex items-center justify-between'>
        <Button variant="outline" className='cursor-pointer'>
          Me gusta
        </Button>
        
        <CommentModal postDetail={postDetail} comments={comments} user={user} />
      </footer>
    </article>
  )
}

export default PublicationCard;