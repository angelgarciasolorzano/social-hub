import type { JSX } from "react";

import { LuEllipsis, LuPencil, LuTrash2 } from "react-icons/lu";

import { Button } from "@/shared/components/shadcn/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/shared/components/shadcn/ui/dropdown-menu";

import { useDialog } from "@/shared/hooks/useDialog";

import type { Comment } from "../types/comment";
import CommentDeleteDialog from "./dialog/CommentDeleteDialog";
import CommentEditDialog from "./dialog/CommentEditDialog";

interface CommentActionsMenuProps {
  comment: Comment;
  onDeleted: () => void;
  onUpdated: () => void;
}

function CommentActionsMenu({
  comment,
  onDeleted,
  onUpdated,
}: CommentActionsMenuProps): JSX.Element | null {
  const { open: openEditDialog, setOpen: setOpenEditDialog } = useDialog();
  const { open: openDeleteDialog, setOpen: setOpenDeleteDialog } = useDialog();

  if (!comment.isMine || comment.isDeleted || comment.content === null) return null;

  return (
    <>
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button
            aria-label="Acciones del comentario"
            className="ml-auto cursor-pointer text-muted-foreground"
            size="icon-xs"
            type="button"
            variant="ghost"
          >
            <LuEllipsis aria-hidden="true" />
          </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end">
          <DropdownMenuItem
            className="cursor-pointer"
            onSelect={() => {
              setOpenEditDialog(true);
            }}
          >
            <LuPencil aria-hidden="true" />
            Editar
          </DropdownMenuItem>

          <DropdownMenuItem
            className="cursor-pointer"
            onSelect={() => {
              setOpenDeleteDialog(true);
            }}
            variant="destructive"
          >
            <LuTrash2 aria-hidden="true" />
            Eliminar
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <CommentEditDialog
        commentId={comment.id}
        initialContent={comment.content}
        onOpenChange={setOpenEditDialog}
        onUpdated={onUpdated}
        open={openEditDialog}
      />

      <CommentDeleteDialog
        commentId={comment.id}
        onDeleted={onDeleted}
        onOpenChange={setOpenDeleteDialog}
        open={openDeleteDialog}
      />
    </>
  );
}

export default CommentActionsMenu;
