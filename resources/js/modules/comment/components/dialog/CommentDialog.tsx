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

import { CommentType } from "../../enums/commentType";
import type { CommentContextPreview } from "../../types/comment";
import CommentList from "../CommentList";

const DELETED_AUTHOR_NAME = "Usuario eliminado";

interface CommentDialogProps {
  onCommentsCountChange: (delta: number) => void;
  postDetail: Post;
}

function CommentDialog({ onCommentsCountChange, postDetail }: CommentDialogProps): JSX.Element {
  const authorName = postDetail.user?.name ?? DELETED_AUTHOR_NAME;

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
            Publicacion de {authorName}
          </DialogTitle>

          <DialogDescription>
            Echa un vistazo a su publicación, dale like o deja tu comentario
          </DialogDescription>
        </DialogHeader>

        <Separator />

        <div className="flex flex-1 gap-4 overflow-hidden">
          <CommentDialogPublication postDetail={postDetail} />
          <CommentDialogContent
            onCommentsCountChange={onCommentsCountChange}
            postDetail={postDetail}
          />
        </div>
      </DialogContent>
    </Dialog>
  );
}

type CommentDialogPublicationProps = Pick<CommentDialogProps, "postDetail">;

function CommentDialogPublication({ postDetail }: CommentDialogPublicationProps) {
  return (
    <ScrollArea className="h-full w-1/2 border-r">
      <div className="pr-3">
        <PostDetail post={postDetail} />
      </div>
    </ScrollArea>
  );
}

type CommentDialogContentProps = CommentDialogProps;

function CommentDialogContent({ onCommentsCountChange, postDetail }: CommentDialogContentProps) {
  const authorName = postDetail.user?.name ?? DELETED_AUTHOR_NAME;

  const previewContext: CommentContextPreview = {
    authorName,
    authorProfilePicture: postDetail.user?.profilePicture,
    content: postDetail.content,
    createdAt: fromNow(postDetail.createdAt) + " - " + formatFullDate(postDetail.createdAt),
    kind: CommentType.POST,
  };

  return (
    <div className="mb-1 flex w-1/2 flex-col gap-2">
      <CommentDialogHeader />

      <Separator />

      <CommentList
        onCommentsCountChange={onCommentsCountChange}
        postId={postDetail.id}
        previewContext={previewContext}
      />
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
