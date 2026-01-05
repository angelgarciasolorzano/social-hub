import React, { useState } from "react";

import { Comment } from "@/types";

import CommentItem from "./CommentItem";

interface CommentListProps {
  comments: Comment[];
}

function CommentList({ comments }: CommentListProps) {
  const [replyTo, setReplyTo] = useState<number | null>(null);
  const [showReplies, setShowReplies] = useState<number | null>(null);

  if (comments.length === 0) {
    return (
      <div className="flex flex-1 items-center justify-center">
        <p className="text-center text-sm text-gray-600 dark:text-white/90">
          No hay comentarios en esta publicación para mostrar
        </p>
      </div>
    );
  }

  return (
    <div className="flex h-full flex-col gap-4 overflow-y-auto pr-2.5">
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
  );
}

export default CommentList;
