import { Form, router } from '@inertiajs/react';
import { Input } from '../ui/input';
import { Button } from '../ui/button';
import { Loader2Icon } from 'lucide-react';
import InputError from '../input-error';
import CommentController from '@/actions/App/Http/Controllers/CommentController';
import { Dispatch, SetStateAction } from 'react';

interface CommentFormProps {
  commentableId: number;
  commentableType: string;
  setReplyTo?: Dispatch<SetStateAction<number | null>>;
};

function CommentForm(props: CommentFormProps) {
  const { commentableId, commentableType, setReplyTo } = props;

  return (
    <Form
      {...CommentController.store.form()}
      resetOnSuccess
      onSuccess={() => {
        if (setReplyTo) setReplyTo(null);
        router.reload({
          only: ['posts'],
        })
      }}
    >
      {({ processing, errors}) => (
        <div className='flex items-center justify-center gap-2'>
          <Input 
            type='hidden'
            name='commentable_type'
            value={commentableType}
          />

          <Input
            type='hidden'
            name='commentable_id'
            value={commentableId}
          />

          <Input
            placeholder='Escribe tu comentario'
            className='w-full'
            name='content'
          />

          <Button>
            {processing ? (
              <>
                <Loader2Icon className='h-4 w-4 animate-spin' />
                Publicando...
              </>
            ) : (
              'Publicar'
            )}
          </Button>

          <InputError message={errors.content} className='mt-1' />
        </div>
      )}
    </Form>
  )
}

export default CommentForm;