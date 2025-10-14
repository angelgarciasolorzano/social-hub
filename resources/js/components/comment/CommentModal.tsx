import React from 'react'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '../ui/dialog';
import { Button } from '../ui/button';
import PublicationDetail from '../publication/PublicationDetail';
import { Separator } from '../ui/separator';
import CommentForm from './CommentForm';
import CommentList from './CommentList';
import { RiWechatLine } from "react-icons/ri";
import { RiArticleLine } from "react-icons/ri";
import { Comment, Post, SharedData } from '@/types';

interface CommentModalProps {
  user: SharedData["user"];
  postDetail: Omit<Post, 'comments'>;
  comments: Comment[];
};

function CommentModal({ postDetail, comments, user }: CommentModalProps) {
  return (
    <Dialog>
      <DialogTrigger asChild>
        <Button variant="outline" className='cursor-pointer'>
          Comentar
        </Button>
      </DialogTrigger>

      <DialogContent className='flex flex-col mx-w-[85%] min-w-[80%] h-[90%] overflow-hidden'>
        <DialogHeader>
          <DialogTitle className='flex items-center gap-2'>
            <RiArticleLine className='w-5 h-5 text-gray-600 dark:text-gray-400' />
            Publicacion de {user.name}
          </DialogTitle>

          <DialogDescription className='dark:text-gray-400'>
            Echa un vistazo a su publicación, dale like o deja tu comentario
          </DialogDescription>
        </DialogHeader>

        <Separator className='dark:bg-gray-700' />

        <div className='flex flex-1 overflow-hidden gap-4'>
          <CommentModalPublication user={user} postDetail={postDetail} />
          <CommentModalCommentsContent idPost={postDetail.id} comments={comments} />
        </div>
      </DialogContent>
    </Dialog>
  )
};

type CommentModalPublicationProps = Omit<CommentModalProps, 'comments'>;

function CommentModalPublication({ postDetail, user }: CommentModalPublicationProps) {
  return (
    <div className='w-1/2 h-full overflow-y-auto pr-3 border-r dark:border-r-gray-700'>
      <PublicationDetail user={user} post={postDetail} />
    </div>
  )
};

type CommentModalCommentsContentProps = Pick<CommentModalProps, 'comments'> & {
  idPost: Post['id'];
};

function CommentModalCommentsContent({ idPost, comments }: CommentModalCommentsContentProps) {
  return (
    <div className='w-1/2 flex flex-col gap-2 mb-1'>
      <CommentModalCommentsHeader />

      <Separator className='dark:bg-gray-700' />

      <CommentList comments={comments} />

      <Separator className='dark:bg-gray-700' />

      <CommentForm commentableId={idPost} commentableType='post' />
    </div>
  )
};

function CommentModalCommentsHeader() {
  return (
    <div className='flex items-center gap-2 text-[15px] font-bold dark:text-gray-200'>
      <RiWechatLine className='w-5 h-5 text-gray-600 dark:text-gray-400' />
      Comentarios de la publicacion
    </div>
  )
};

export default CommentModal;