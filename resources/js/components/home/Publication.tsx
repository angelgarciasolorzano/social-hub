import React, { useState } from 'react'
import { Button } from '../ui/button';
import { FaRegComment, FaRegHeart } from "react-icons/fa";
import { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { Separator } from '../ui/separator';
import { RiTimeZoneLine } from "react-icons/ri";
import { IoSend } from "react-icons/io5";
import { cn } from '@/lib/utils';
import { Dialog, DialogContent, DialogTrigger } from '../ui/dialog';
import { Input } from '../ui/input';

interface PublicationProps {
  id: number;
  content: string;
  image: string;
  created_at: string;
};

function Publication({ content, image, created_at }: PublicationProps) {
  const { auth } = usePage<SharedData>().props;
  const [open, setOpen] = useState<boolean>(false);
  
  return (
    <div className='px-4 py-3'>
      <div className='border rounded-lg shadow-md'>
        <div className='flex items-center pt-4'>
          <div className='px-4'>
            <img src={image} alt='Foto de perfil' className='w-17 h-17 object-cover rounded-full border' />
          </div>

          <div className='grid gap-2'>
            <h4 className='font-bold text-lg'>
              {auth.user.name}
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
            <img src={image} alt='Publicación' className='w-full max-h-[400px] object-cover rounded-lg' />
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

          <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
              <Button variant="outline" className='cursor-pointer'>
                <FaRegComment className='w-4 h-4' />
                Comentar
              </Button>
            </DialogTrigger>

            <DialogContent className='flex max-w-[85%] min-w-[80%] h-[90vh] overflow-hidden'>
              <div className='w-1/2 h-full overflow-y-auto space-y-4 border-r pr-4'>
                <div className='flex items-center'>
                  <div>
                    <img
                      src={image}
                      alt="Foto de perfil"
                      className="w-14 h-14 object-cover rounded-full border"
                    />
                  </div>

                  <div className="grid gap-2 ml-2">
                    <h4 className="font-bold text-lg">
                      {auth.user.name}
                      <span className="ml-2 text-gray-600 text-sm font-medium">
                        agregó una publicación
                      </span>
                    </h4>

                    <p className="text-gray-700 text-xs flex items-center">
                      <RiTimeZoneLine className="w-4 h-4 text-gray-600 mr-1.5" />
                      {created_at}
                    </p>
                  </div>
                </div>

                <p>{content}</p>

                {image && (
                  <div className="w-full">
                    <img
                      src={image}
                      alt="Publicación"
                      className="w-full object-cover rounded-lg"
                    />
                  </div>
                )}
              </div>

              <div className='w-1/2 h-full flex flex-col'>
                <h3 className='text-lg font-semibold mb-2 flex items-center'>
                  Comentarios
                  <FaRegComment className='w-4 h-4 ml-2' />
                </h3>

                <Separator />

                <div className='flex-1 overflow-y-auto space-y-4 my-4 text-sm'>
                  {[...Array(30)].map((_, i) => (
                    <div key={i} className="p-3 border rounded-lg">
                      Comentario {i + 1}
                    </div>
                  ))}
                </div>

                <div className='flex items-center justify-center gap-2'>
                  <Input placeholder='Escribe tu comentario' className='w-full' />
                  <IoSend className='w-7 h-7 text-gray-600' />
                </div>
              </div>
            </DialogContent>
          </Dialog>
        </div>
      </div>
    </div>
  )
}

export default Publication;
