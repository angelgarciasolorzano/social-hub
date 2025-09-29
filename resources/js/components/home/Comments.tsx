import { Dispatch, SetStateAction } from 'react'
import { Dialog, DialogContent } from '../ui/dialog';

interface CommentsProps {
  open: boolean;
  setOpen: Dispatch<SetStateAction<boolean>>;
};

function Comments({ open, setOpen }: CommentsProps) {
  return (
    <div>
      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent>
          Hola mundo
        </DialogContent>
      </Dialog>
    </div>
  )
}

export default Comments;