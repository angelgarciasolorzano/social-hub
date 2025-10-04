import React, { useState } from 'react'
import CommentItem from './CommentItem';
import { Comment } from '@/types';

interface CommentListProps {
  comments: Comment[];
};

function CommentList({ comments }: CommentListProps) {
  const [replyTo, setReplyTo] = useState<number | null>(null);
  const [showReplies, setShowReplies] = useState<number | null>(null);
      
  if (comments.length === 0) {
    return (
      <div className='flex-1 flex items-center justify-center'>
        <p className='text-sm text-gray-600 dark:text-white/90 text-center'>
          No hay comentarios en esta publicación para mostrar
        </p>
      </div>
    )
  };

  return (
    <div className='h-full overflow-y-auto flex flex-col gap-4 pr-2.5'>
      {comments.map((comment) => (
        <CommentItem 
          key={comment.id} 
          comment={comment} 
          replyTo={replyTo} 
          showReplies={showReplies} 
          setReplyTo={setReplyTo} 
          setShowReplies={setShowReplies}
        />
      ))}
    </div>
  )
}

export default CommentList;
