import { Dispatch, SetStateAction, useState } from 'react'
import { Form, router } from '@inertiajs/react';

import { Loader2Icon } from 'lucide-react';
import { RiTimeZoneLine } from 'react-icons/ri';
import { FaRegComment } from "react-icons/fa";
//import { IoHeart } from "react-icons/io5";

import { Comment, Post, SharedData } from '@/types';
import { cn } from '@/lib/utils';

import CommentController from '@/actions/App/Http/Controllers/CommentController';

import { Dialog, DialogContent, DialogTrigger } from '../ui/dialog';
import { Button } from '../ui/button';
import { Input } from '../ui/input';
import { Separator } from '../ui/separator';

import InputError from '../input-error';

interface CommentsProps {
  auth: SharedData["user"];
  post: Post;
};

function Comments(props: CommentsProps) {
  const [replyTo, setReplyTo] = useState<number | null>(null);
  const [showReplies, setShowReplies] = useState<number | null>(null);

  const { post, auth } = props;

  return (
    <div>
      <Dialog>
        <DialogTrigger asChild>
          <Button variant="outline" className='cursor-pointer'>
            <FaRegComment className='w-4 h-4' />
            Comentar
          </Button>
        </DialogTrigger>

        <DialogContent className='flex mx-w-[85%] min-w-[80%] h-[90vh] overflow-hidden'>
          <Publicación 
            content={post.content} 
            image={post.image} 
            created_at={post.created_at} 
            auth={auth} 
          />

          <div className='w-1/2 h-full flex flex-col'>
            <h3 className='text-lg font-semibold mb-2 flex items-center'>
              Comentarios
              <FaRegComment className='w-4 h-4 ml-2' />
            </h3>

            <Separator />

            <CommentsUsers 
              comments={post.comments} 
              replyTo={replyTo} 
              showReplies={showReplies} 
              setShowReplies={setShowReplies} 
              setReplyTo={setReplyTo}
            />

            <Separator className='mb-4' />

            <CommentForm 
              id={post.id} 
              commentableType='post'
              setReplyTo={setReplyTo}
            />
          </div>
        </DialogContent>
      </Dialog>
    </div>
  )
};

type PublicaciónProps = Omit<Post, 'comments' | 'id'> & { auth: SharedData['user'] };

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
            {auth.name}

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

interface CommentsUsersProps {
  comments: Comment[];
  replyTo: number | null;
  showReplies: number | null;
  setShowReplies: Dispatch<SetStateAction<number | null>>;
  setReplyTo: Dispatch<SetStateAction<number | null>>;
};

function CommentsUsers({ comments, replyTo, showReplies, setShowReplies, setReplyTo }: CommentsUsersProps) {
  if (comments.length === 0) {
    return (
      <div className='flex-1 flex items-center justify-center'>
        <p className='text-sm text-gray-600'>
          No hay comentarios en esta publicacion para mostrar
        </p>
      </div>
    )
  };

  return (
    <div className='flex-1 overflow-y-auto space-y-4 my-4 text-sm px-2.5'>
      {comments.map((comment) => (
        <div key={comment.id}>
          <div className='border p-2 rounded-md bg-gray-50/50 space-y-1'>
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
            <button 
              className='text-xs text-gray-600 font-medium hover:text-red-600 transition-colors cursor-pointer'
            >
              Me gusta
            </button>

            <button
              type='button'
              className='cursor-pointer text-xs text-gray-600 font-medium hover:text-black/80 transition-colors'
              onClick={() => setReplyTo(replyTo === comment.id ? null : comment.id)}
            >
              Responder
            </button>

            {comment.replies && comment.replies.length > 0 && (
              <button
                type='button'
                className='cursor-pointer text-xs text-gray-600 font-medium hover:text-blue-600 transition-colors'
                onClick={() => setShowReplies(showReplies === comment.id ? null : comment.id)}
              >
                {showReplies === comment.id ? 'Ocultar respuestas' : `Mostrar ${comment.replies.length} respuestas`}
              </button>
            )}
          </div>

          {replyTo === comment.id && (
            <CommentForm 
              commentableType='comment'
              id={comment.id} className='mt-2.5' 
              setReplyTo={setReplyTo} 
            />
          )}

          {showReplies === comment.id && (
            <>
              {comment.replies && comment.replies.map((reply) => (
                <div key={reply.id} className='space-y-1.5 my-1.5 px-4 pt-2.5'>
                  <div className='flex items-center gap-2'>
                    <img 
                      src="https://avatars.dicebear.com/api/initials/1.svg" 
                      alt="Foto de perfil" 
                      className="w-6 h-6 rounded-full" 
                    />

                    <div>
                      <h5 className='font-medium text-sm'>{reply.user.name}</h5>
                      <span className='text-gray-500 text-xs'>{reply.created_at}</span>
                    </div>
                  </div>

                  <p className='text-gray-600 text-sm'>{reply.content}</p>

                  {/* <div className='inline-flex items-center gap-1 group cursor-pointer mt-1'>
                    <IoHeart className='w-4 h-4 text-gray-600 group-hover:text-red-500' />
                    <span className='text-gray-600 text-xs group-hover:text-red-500'>Me gusta</span>
                  </div> */}
                </div>
              ))}
            </>
          )}
        </div>
      ))}
    </div>
  )
};

type CommentFormProps = Pick<CommentsUsersProps, 'setReplyTo'> & {
  id: number;
  commentableType: string
  className?: string
};;

function CommentForm({ id, commentableType, className, setReplyTo }: CommentFormProps) {
  return (
    <Form
      {...CommentController.store.form()}
      className={cn(
        className
      )}
      resetOnSuccess
      onSuccess={() => {
        setReplyTo(null);
        router.reload({
          only: ['posts'],
        })
      }}
    >
      {({ processing, errors}) => (
        <>
          <div className='flex items-center justify-center gap-2'>
            <Input type='hidden' name='commentable_type' value={commentableType} />
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