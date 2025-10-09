import React, { Dispatch, SetStateAction } from 'react'
import { Avatar, AvatarFallback, AvatarImage } from '../ui/avatar';
import { IoMdTime } from "react-icons/io";
import { Comment } from '@/types';
import { cn } from '@/lib/utils';
import CommentForm from './CommentForm';

interface CommentItemProps {
  comment: Comment;
  isReply?: boolean;
  replyTo: number | null;
  showReplies: number | null;
  setReplyTo: Dispatch<SetStateAction<number | null>>;
  setShowReplies: Dispatch<SetStateAction<number | null>>;
};

function CommentItem(props: CommentItemProps) {
  const { comment, isReply = false, replyTo, showReplies, setReplyTo, setShowReplies } = props;

  return (
    <article className={cn(
      "space-y-1",
      isReply && "ml-2"
    )}>
      <div className={cn(
        !isReply && "border rounded-md bg-gray-50/50 dark:bg-gray-700/20 dark:border-gray-500/40",
        "p-2 space-y-1",
      )}>
        <header className='flex items-center gap-2'>
          <Avatar>
            <AvatarImage
              src='https://picsum.photos/200'
              alt='Foto de perfil'
            />

            <AvatarFallback>Foto de perfil</AvatarFallback>
          </Avatar>

          <div className='block'>
            <p className='font-bold text-sm dark:text-white'>
              {comment.user.name}
            </p>

            <time className='text-xs text-gray-500 flex items-center gap-1 dark:text-gray-400'>
              <IoMdTime className='w-3 h-3 text-gray-600 dark:text-gray-500' />
              {comment.created_at}
            </time>
          </div>
        </header>

        <section>
          <p className='text-sm text-gray-700 dark:text-gray-300'>{comment.content}</p>
        </section>
      </div>

      <footer className='flex items-center gap-4 text-xs text-gray-600 font-medium dark:text-gray-400'>
        <button className='hover:text-red-600 dark:hover:text-red-500 cursor-pointer'>
          Me gusta
        </button>

        <button
          type='button'
          className='cursor-pointer hover:text-black/80 dark:hover:text-white/80 transition-colors'
          onClick={() => setReplyTo(replyTo === comment.id ? null : comment.id)}
        >
          Responder
        </button>

        {comment.replies && comment.replies.length > 0 && (
          <button
            type='button'
            className='cursor-pointer hover:text-blue-600 dark:hover:text-blue-500transition-colors'
            onClick={() => setShowReplies(showReplies === comment.id ? null : comment.id)}
          >
            {showReplies === comment.id ? 'Ocultar respuestas' : `Mostrar ${comment.replies.length} respuestas`}
          </button>
        )}
      </footer>

      {replyTo === comment.id && (
        <CommentForm
          commentableType='comment'
          commentableId={comment.id}
          setReplyTo={setReplyTo}
        />
      )}

      {showReplies === comment.id && (
        <>
          {comment.replies && comment.replies.map((replie) => (
            <div className='mt-4' key={replie.id} >
              <CommentItem 
                comment={replie} 
                isReply
                replyTo={replyTo} 
                showReplies={showReplies} 
                setReplyTo={setReplyTo} 
                setShowReplies={setShowReplies}
              />
            </div>
          ))}
        </>
      )}
    </article>
  )
}

export default CommentItem;
