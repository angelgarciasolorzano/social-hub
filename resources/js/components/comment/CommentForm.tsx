import { Dispatch, SetStateAction } from "react";

import { Form } from "@inertiajs/react";

import { cn } from "@/lib/utils";

//import { Loader2Icon } from "lucide-react";

import CommentController from "@/actions/App/Comment/Controllers/CommentController";

import { CommentableType } from "@/enums";

import InputError from "../form/InputError";
//import { Button } from "../ui/button";
import { Input } from "../ui/input";
import { Textarea } from "../ui/textarea";

interface CommentFormProps {
  commentableId: number;
  commentableType: CommentableType;
  onCommentPosted?: () => void;
  setOpen: Dispatch<SetStateAction<boolean>>;
  setProcessing: Dispatch<SetStateAction<boolean>>;
  setReplyTo?: Dispatch<SetStateAction<number | null>>;
}

function CommentForm(props: CommentFormProps) {
  const { commentableId, commentableType, onCommentPosted, setReplyTo, setProcessing, setOpen } =
    props;

  return (
    <Form
      {...CommentController.store.form()}
      id="comment-form"
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

          {/* <Input name="content" className="w-full" placeholder="Escribe tu comentario" /> */}

          <Textarea name="content" className="w-full" placeholder="Escribe tu comentario" />

          {/* <Button>
            {processing ? (
              <>
                <Loader2Icon className="h-4 w-4 animate-spin" />
                Publicando...
              </>
            ) : (
              "Publicar"
            )}
          </Button> */}

          <InputError className="mt-1" message={errors.content} />
        </div>
      )}
    </Form>
  );
}

export default CommentForm;
