import { Dispatch, SetStateAction } from 'react'
import { Form, router } from '@inertiajs/react';

import { Loader2Icon } from 'lucide-react';
import { RiTimeZoneLine } from 'react-icons/ri';
import { FaRegComment } from "react-icons/fa";

import { SharedData } from '@/types';

import CommentController from '@/actions/App/Http/Controllers/CommentController';

import { Dialog, DialogContent, DialogTrigger } from '../ui/dialog';
import { Button } from '../ui/button';
import { Input } from '../ui/input';
import { Separator } from '../ui/separator';

import InputError from '../input-error';


interface CommentsProps {
  open: boolean;
  setOpen: Dispatch<SetStateAction<boolean>>;
  auth: SharedData['auth'];
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
  } [];
};

function Comments(props: CommentsProps) {
  const { open, setOpen, id, comments, content, image, created_at, auth } = props;

  return (
    <div>
      <Dialog open={open} onOpenChange={setOpen}>
        <DialogTrigger asChild>
          <Button variant="outline" className='cursor-pointer'>
            <FaRegComment className='w-4 h-4' />
            Comentar
          </Button>
        </DialogTrigger>

        <DialogContent className='flex mx-w-[85%] min-w-[80%] h-[90vh] overflow-hidden'>
          <Publicación 
            content={content} 
            image={image} 
            created_at={created_at} 
            auth={auth} 
          />

          <div className='w-1/2 h-full flex flex-col'>
            <h3 className='text-lg font-semibold mb-2 flex items-center'>
              Comentarios
              <FaRegComment className='w-4 h-4 ml-2' />
            </h3>

            <Separator />
            <CommentsUsers comments={comments} />

            <Separator className='mb-4' />
            <CommentForm id={id} />
          </div>
        </DialogContent>
      </Dialog>
    </div>
  )
};

type PublicaciónProps = {
  content: string;
  image: string;
  created_at: string;
  auth: SharedData['auth'];
};

function Publicación(props: PublicaciónProps) {
  const { content, image, created_at, auth } = props;

  return (
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

      <p className='text-sm text-gray-600'>{content}</p>

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
  )
};

type CommentsUsersProps = {
  comments: {
    id: number;
    content: string;
    created_at: string;
    user: {
      id: number;
      name: string;
    }
  } [];
};

function CommentsUsers(props: CommentsUsersProps) {
  const { comments } = props;

  if (comments.length === 0) {
    return <p>No hay comentarios en esta publicacion para mostrar</p>
  };

  return (
    <div className='flex-1 overflow-y-auto space-y-4 my-4 text-sm'>
      {comments.map((comment) => (
        <div key={comment.id}>
          <div className='border p-2 rounded-md bg-gray-50 space-y-1'>
            <div className='flex items-center gap-2'>
              <img 
                src="https://avatars.dicebear.com/api/initials/1.svg" 
                alt="Foto de perfil" 
                className="w-10 h-10 rounded-full" 
              />

              <div>
                <p className="font-semibold text-sm">
                  {comment.user.name}
                </p>

                <span className="text-xs text-gray-500">
                  {comment.created_at}
                </span>
              </div>
            </div>

            <p className="text-sm text-gray-600 mt-2.5">
              {comment.content}
            </p>
          </div>

          <div className='flex items-center gap-4 mt-1 px-2'>
            <span>Me gusta</span>
            <span>Responder</span>
          </div>
        </div>
      ))}
    </div>
  )
};

type CommentFormProps = {
  id: number;
};

function CommentForm({ id }: CommentFormProps) {
  return (
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

          <InputError message={errors.content} className='mt-2' />
        </>
      )}
    </Form>
  )
}

export default Comments;