import { Dispatch, SetStateAction, useState } from 'react';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '../ui/dialog';
import { Form } from '@inertiajs/react';
import PostController from '@/actions/App/Http/Controllers/PostController';
import { Label } from '../ui/label';
import { Textarea } from '../ui/textarea';
import { Input } from '../ui/input';
import InputError from '../form/InputError';
import { Button } from '../ui/button';
import { Loader2Icon } from 'lucide-react';

interface PublicationFormProps {
  open: boolean;
  setOpen: Dispatch<SetStateAction<boolean>>;
};

function PublicationForm({ open, setOpen }: PublicationFormProps) {
  const [processing, setProcessing] = useState<boolean>(false);

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Crear publicación</DialogTitle>

          <DialogDescription>
            Comparte tus ideas y pensamientos con el mundo.
          </DialogDescription>
        </DialogHeader>

        <PublicationFormContent setOpen={setOpen} setProcessing={setProcessing} />
        
        <DialogFooter>
          <DialogClose asChild>
            <Button type='button' variant="outline">
              Cancelar
            </Button>
          </DialogClose>

          <Button
            type='submit'
            form='post-form'
            disabled={processing}
          >
            {processing ? (
              <Loader2Icon className='h-4 w-4 animate-spin' />
            ) : (
              'Publicar'
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
};

interface PublicationFormContentProps {
  setOpen: Dispatch<SetStateAction<boolean>>;
  setProcessing: Dispatch<SetStateAction<boolean>>;
};

function PublicationFormContent({ setOpen, setProcessing }: PublicationFormContentProps) {
  return (
    <Form
      {...PostController.store.form()}
      id='post-form'
      onStart={() => setProcessing(true)}
      onFinish={() => setProcessing(false)}
      onSuccess={() => setOpen(false)}
      className='flex flex-col gap-5'
    >
      {({ errors }) => (
        <>
          <div className='flex flex-col gap-2'>
            <Label htmlFor='post-content'>Contenido</Label>

            <Textarea
              id='post-content'
              name='content'
              required
              minLength={10}
              className='min-h-32'
              placeholder='Escribe lo que quieres compartir'
            />

            <InputError message={errors.content} />
          </div>

          <div className='flex flex-col gap-2'>
            <Label htmlFor='post-image'>Imagen</Label>

            <Input
              id='post-image'
              type='file'
              name='image_file'
              accept='image/*'
            />

            <InputError message={errors.image_file} />
          </div>
        </>
      )}
    </Form>
  )
}

export default PublicationForm;
