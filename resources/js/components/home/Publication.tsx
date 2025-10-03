import { FaRegHeart } from 'react-icons/fa';
import { RiTimeZoneLine } from "react-icons/ri";
import { usePage } from '@inertiajs/react';

import { Post, SharedData } from '@/types';
import { cn } from '@/lib/utils';

import { Button } from '../ui/button';
import { Separator } from '../ui/separator';

import Comments from './Comments';

type PublicationProps = Post;

function Publication({ id, content, image, created_at, comments }: PublicationProps) {
  const { user } = usePage<SharedData>().props;
  
  return (
    <div className='px-4 py-3'>
      <div className='border rounded-lg shadow-md'>
        <div className='flex items-center pt-4'>
          <div className='px-4'>
            <img 
              src={image} 
              alt='Foto de perfil' 
              className='w-17 h-17 object-cover rounded-full border' 
            />
          </div>

          <div className='grid gap-2'>
            <h4 className='font-bold text-lg'>
              {user.name}
              <span className='ml-2 text-gray-600 text-sm font-medium'>
                agregó una publicación
              </span>
            </h4>

            <p className='text-gray-700 text-xs flex'>
              <RiTimeZoneLine className='w-4 h-4 text-gray-600 mr-1.5' />
              {created_at}
            </p>
          </div>
        </div>

        <p className='pb-4 pt-3 px-4'>{content}</p>

        {image && (
          <div className='w-full px-4'>
            <img 
              src={image} 
              alt='Publicación' 
              className='w-full max-h-[400px] object-cover rounded-lg' 
            />
          </div>
        )}

        <div className={cn(
          'flex justify-between items-center px-4 pb-4',
          image ? 'pt-4' : 'pt-0.5'
        )}>
          <span className='text-gray-600 text-sm'>1200 me gustas</span>
          <span className='text-gray-600 text-sm'>100 comentarios</span>
        </div>

        <div className='px-4'>
          <Separator className=''/>
        </div>

        <div className='flex justify-between items-center px-4 py-4'>
          <Button variant="outline" className='cursor-pointer'>
            <FaRegHeart className='w-4 h-4' />
            Me gusta
          </Button>

          <Comments
            post={{ id, content, image, created_at, comments}}
            auth={user}
          />
        </div>
      </div>
    </div>
  )
}

export default Publication;
