import type { Dispatch, FormEvent, SetStateAction } from "react";

import type { InertiaFormProps } from "@inertiajs/react";

import { store } from "@/shared/wayfinder/actions/App/Comment/Controllers/CommentController";

import { InputError } from "@/shared/components/form";
import { Input } from "@/shared/components/ui/input";
import { Textarea } from "@/shared/components/ui/textarea";

import type { CommentableTypeValues } from "../../enums/commentableType";
import type { CommentFormData } from "../../types/comment";

interface CommentFormProps {
  formComment: InertiaFormProps<CommentFormData>;
  commentableId: number;
  commentableType: CommentableTypeValues;
  formId: string;
  uploadedComments?: () => void;
  setOpenModalComment: Dispatch<SetStateAction<boolean>>;
}

function CommentForm(props: CommentFormProps) {
  const {
    formComment,
    commentableId,
    commentableType,
    formId,
    uploadedComments,
    setOpenModalComment,
  } = props;

  const { setData, errors, submit } = formComment;

  const handleSubmit = (e: FormEvent<HTMLFormElement>): void => {
    e.preventDefault();

    submit(store(), {
      onSuccess: () => {
        if (uploadedComments) uploadedComments();

        setOpenModalComment(false);
      },
      reset: ["content"],
    });
  };

  return (
    <form id={formId} className="mt-2" onSubmit={handleSubmit}>
      <div className="flex flex-col justify-center gap-2">
        <Input name="commentable_type" type="hidden" value={commentableType} />

        <Input name="commentable_id" type="hidden" value={commentableId} />

        <Textarea
          name="content"
          className="min-h-72 resize-none"
          onChange={(e) => setData("content", e.target.value)}
          placeholder="Escribe tu comentario"
        />

        <InputError className="mt-1" message={errors.content} />
      </div>
    </form>
  );
}

export default CommentForm;
