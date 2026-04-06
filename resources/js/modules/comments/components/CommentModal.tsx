import { RiArticleLine, RiWechatLine } from "react-icons/ri";

import { PostDetail } from "@/modules/post";

import { Button } from "@/shared/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/shared/components/ui/dialog";
import { Separator } from "@/shared/components/ui/separator";

import type { Post, User } from "@/shared/types";

import CommentList from "../../../components/comment/CommentList";

interface CommentModalProps {
  postDetail: Post;
  user: User;
}

function CommentModal({ postDetail, user }: CommentModalProps) {
  return (
    <Dialog>
      <DialogTrigger asChild>
        <Button className="cursor-pointer" variant="outline">
          Comentar
        </Button>
      </DialogTrigger>

      <DialogContent className="mx-w-[85%] flex h-[90%] min-w-[80%] flex-col overflow-hidden">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <RiArticleLine className="h-5 w-5 text-gray-600 dark:text-gray-400" />
            Publicacion de {user.name}
          </DialogTitle>

          <DialogDescription className="dark:text-gray-400">
            Echa un vistazo a su publicación, dale like o deja tu comentario
          </DialogDescription>
        </DialogHeader>

        <Separator className="dark:bg-gray-700" />

        <div className="flex flex-1 gap-4 overflow-hidden">
          <CommentModalPublication postDetail={postDetail} user={user} />
          <CommentModalContent idPost={postDetail.id} />
        </div>
      </DialogContent>
    </Dialog>
  );
}

type CommentModalPublicationProps = CommentModalProps;

function CommentModalPublication({ postDetail, user }: CommentModalPublicationProps) {
  return (
    <div className="h-full w-1/2 overflow-y-auto border-r pr-3 dark:border-r-gray-700">
      <PostDetail post={postDetail} user={user} />
    </div>
  );
}

interface CommentModalContentProps {
  idPost: Post["id"];
}

function CommentModalContent({ idPost }: CommentModalContentProps) {
  return (
    <div className="mb-1 flex w-1/2 flex-col gap-2">
      <CommentModalHeader />

      <Separator className="dark:bg-gray-700" />

      <CommentList postId={idPost} />
    </div>
  );
}

function CommentModalHeader() {
  return (
    <div className="flex items-center gap-2 text-[15px] font-bold dark:text-gray-200">
      <RiWechatLine className="h-5 w-5 text-gray-600 dark:text-gray-400" />
      Comentarios de la publicacion
    </div>
  );
}

export default CommentModal;
