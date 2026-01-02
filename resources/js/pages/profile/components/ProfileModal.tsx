import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Dispatch, SetStateAction } from 'react'

interface ProfileModalProps {
  open: boolean;
  setOpen: Dispatch<SetStateAction<boolean>>;
}

function ProfileModal({ open, setOpen }: ProfileModalProps) {
  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogContent className='flex flex-col mx-w-[80%] min-w-[65%] h-[80%] overflow-hidden'>
        <DialogHeader>
          <DialogTitle>Configuracion</DialogTitle>
          
          <DialogDescription>
            Aquí puedes configurar tu perfil y preferencias.
          </DialogDescription>
        </DialogHeader>

        <div>
          {/* Contenido de configuración va aquí */}
        </div>
      </DialogContent>
    </Dialog>
  )
}

export default ProfileModal;
