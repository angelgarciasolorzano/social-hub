import { Dispatch, SetStateAction } from "react";

import { Form, router } from "@inertiajs/react";

import { CommentableType } from "@/enums";
import { Loader2Icon } from "lucide-react";

import CommentController from "@/actions/App/Comment/Controllers/CommentController";

import InputError from "../form/InputError";
import { Button } from "../ui/button";
import { Input } from "../ui/input";

interface CommentFormProps {
  commentableId: number;
  commentableType: CommentableType;
  setReplyTo?: Dispatch<SetStateAction<number | null>>;
}

function CommentForm(props: CommentFormProps) {
  const { commentableId, commentableType, setReplyTo } = props;

  return (
    <Form
      {...CommentController.store.form()}
      onSuccess={() => {
        if (setReplyTo) setReplyTo(null);
        router.reload({
          only: ["posts"],
        });
      }}
      resetOnSuccess
    >
      {({ errors, processing }) => (
        <div className="flex items-center justify-center gap-2">
          <Input name="commentable_type" type="hidden" value={commentableType} />

          <Input name="commentable_id" type="hidden" value={commentableId} />

          <Input name="content" className="w-full" placeholder="Escribe tu comentario" />

          <Button>
            {processing ? (
              <>
                <Loader2Icon className="h-4 w-4 animate-spin" />
                Publicando...
              </>
            ) : (
              "Publicar"
            )}
          </Button>

          <InputError className="mt-1" message={errors.content} />
        </div>
      )}
    </Form>
  );
}

export default CommentForm;
