import type { JSX } from "react";

import { RiArticleLine, RiWechatLine } from "react-icons/ri";

import type { Post } from "@/modules/post";
import { PostDetail } from "@/modules/post";

import { Button } from "@/shared/components/shadcn/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/shared/components/shadcn/ui/dialog";
import { ScrollArea } from "@/shared/components/shadcn/ui/scroll-area";
import { Separator } from "@/shared/components/shadcn/ui/separator";

import { formatFullDate, fromNow } from "@/shared/lib/dateTime";

import type { User } from "@/shared/types";

import { CommentType } from "../../enums/commentType";
import type { CommentContextPreview } from "../../types/comment";
import CommentList from "../CommentList";

interface CommentDialogProps {
  postDetail: Post;
  user: User;
}

function CommentDialog({ postDetail, user }: CommentDialogProps): JSX.Element {
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
            <RiArticleLine className="h-5 w-5 text-muted-foreground" />
            Publicacion de {user.name}
          </DialogTitle>

          <DialogDescription>
            Echa un vistazo a su publicación, dale like o deja tu comentario
          </DialogDescription>
        </DialogHeader>

        <Separator />

        <div className="flex flex-1 gap-4 overflow-hidden">
          <CommentDialogPublication postDetail={postDetail} user={user} />
          <CommentDialogContent postDetail={postDetail} user={user} />
        </div>
      </DialogContent>
    </Dialog>
  );
}

type CommentDialogPublicationProps = CommentDialogProps;

function CommentDialogPublication({ postDetail, user }: CommentDialogPublicationProps) {
  return (
    <ScrollArea className="h-full w-1/2 border-r">
      <div className="pr-3">
        <PostDetail post={postDetail} user={user} />
      </div>
    </ScrollArea>
  );
}

type CommentDialogContentProps = CommentDialogProps;

function CommentDialogContent({ postDetail, user }: CommentDialogContentProps) {
  const previewContext: CommentContextPreview = {
    authorName: user.name,
    authorProfilePicture: user.profilePicture,
    content: postDetail.content,
    createdAt: fromNow(postDetail.createdAt) + " - " + formatFullDate(postDetail.createdAt),
    kind: CommentType.POST,
  };

  return (
    <div className="mb-1 flex w-1/2 flex-col gap-2">
      <CommentDialogHeader />

      <Separator />

      <CommentList postId={postDetail.id} previewContext={previewContext} />
    </div>
  );
}

function CommentDialogHeader() {
  return (
    <div className="flex items-center gap-2 text-[15px] font-bold">
      <RiWechatLine className="h-5 w-5 text-muted-foreground" />
      Comentarios de la publicacion
    </div>
  );
}

export default CommentDialog;
