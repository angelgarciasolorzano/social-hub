import { Dispatch, SetStateAction } from "react";

import { Form } from "@inertiajs/react";

import { cn } from "@/lib/utils";

import CommentController from "@/actions/App/Comment/Controllers/CommentController";

import { CommentableType } from "@/enums";

import InputError from "../form/InputError";
import { Input } from "../ui/input";
import { Textarea } from "../ui/textarea";

interface CommentFormProps {
  commentableId: number;
  commentableType: CommentableType;
  formId: string;
  onCommentPosted?: () => void;
  setOpen: Dispatch<SetStateAction<boolean>>;
  setProcessing: Dispatch<SetStateAction<boolean>>;
  setReplyTo?: Dispatch<SetStateAction<number | null>>;
}

function CommentForm(props: CommentFormProps) {
  const {
    commentableId,
    commentableType,
    formId,
    onCommentPosted,
    setReplyTo,
    setProcessing,
    setOpen,
  } = props;

  return (
    <Form
      {...CommentController.store.form()}
      id={formId}
      className={cn(setReplyTo && "mt-4")}
      onFinish={() => setProcessing(false)}
      onStart={() => setProcessing(true)}
      onSuccess={() => {
        if (setReplyTo) setReplyTo(null);
        if (onCommentPosted) onCommentPosted();

        setOpen(false);
      }}
      resetOnSuccess
    >
      {({ errors }) => (
        <div className="flex items-center justify-center gap-2">
          <Input name="commentable_type" type="hidden" value={commentableType} />

          <Input name="commentable_id" type="hidden" value={commentableId} />

          <Textarea name="content" className="w-full" placeholder="Escribe tu comentario" />

          <InputError className="mt-1" message={errors.content} />
        </div>
      )}
    </Form>
  );
}

export default CommentForm;
