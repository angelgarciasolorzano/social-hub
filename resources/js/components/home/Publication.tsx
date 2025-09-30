import React, { useState } from 'react'
import { Button } from '../ui/button';
import { FaRegComment, FaRegHeart } from "react-icons/fa";
import { SharedData } from '@/types';
import { Form, router, usePage } from '@inertiajs/react';
import { Separator } from '../ui/separator';
import { RiTimeZoneLine } from "react-icons/ri";
import { cn } from '@/lib/utils';
import { Dialog, DialogContent, DialogTrigger } from '../ui/dialog';
import { Input } from '../ui/input';
import CommentController from '@/actions/App/Http/Controllers/CommentController';
import InputError from '../input-error';
import { Loader2Icon } from 'lucide-react';

interface PublicationProps {
  id: number;
  content: string;
  image: string;
  created_at: string;
  comments: {
    id: number;
    content: string;
    created_at: string;
    user: {
      id: number;
      name: string;
    }
  }[];
};

function Publication({ id, content, image, created_at, comments }: PublicationProps) {
  const { auth } = usePage<SharedData>().props;
  const [open, setOpen] = useState<boolean>(false);
  console.log(comments);
  
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
                  {comments.length === 0 ? (
                    <p>No hay comentarios</p>
                  ) : (
                    comments.map((comment) => (
                      <div key={comment.id}>
                        <div className='border p-2 rounded-md bg-gray-50 space-y-1'>
                          <div className='flex items-center gap-2'>
                          <img src="https://avatars.dicebear.com/api/initials/1.svg" alt="Foto de perfil" className="w-10 h-10 rounded-full" />

                          <div>
                            <p className="font-semibold text-sm">{comment.user.name}</p>
                            <span className="text-xs text-gray-500">{comment.created_at}</span>
                          </div>
                        </div>

                        <p className="text-sm text-gray-600 mt-2.5">{comment.content}</p>
                        </div>

                        <div className='flex items-center gap-4 mt-1 px-2'>
                          <span>Me gusta</span>
                          <span>Responder</span>
                        </div>
                      </div>
                    ))
                  )}
                </div>

                <Form
                  {...CommentController.store.form()}
                  resetOnSuccess
                  onSuccess={() => {
                    router.reload({
                      only: ['posts'],
                    })
                  }}
                >
                  {({ processing, errors}) => (
                    <>
                      <div className='flex items-center justify-center gap-2'>
                        <Input type='hidden' name='commentable_type' value='App\Models\Post' />
                        <Input type='hidden' name='commentable_id' value={id} />
                        <Input placeholder='Escribe tu comentario' className='w-full' name='content' />

                        <Button>
                          {processing ? (
                            <>
                              <Loader2Icon className='h-4 w-4 animate-spin' />
                              Publicando...
                            </>
                          ) : (
                            'Publicar'
                          )}
                        </Button>
                      </div>

                      <InputError message={errors.content} />
                    </>
                  )}
                </Form>
              </div>
            </DialogContent>
          </Dialog>
        </div>
      </div>
    </div>
  )
}

export default Publication;
