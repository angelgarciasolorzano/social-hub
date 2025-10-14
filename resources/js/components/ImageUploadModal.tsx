import React from 'react'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from './ui/dialog';
import { Button } from './ui/button';
import { cn } from '@/lib/utils';

interface ImageUploadModalProps {
  preview: string;
  type: "profile" | "cover";
  onClose: () => void;
  onConfirm: () => void;
};

function ImageUploadModal({ preview, type, onClose, onConfirm }: ImageUploadModalProps) {
  return (
    <Dialog open onOpenChange={onClose}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Vista previa</DialogTitle>

          <DialogDescription>
            Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nullam
            egestas, urna in tincidunt venenatis, nisi risus tincidunt nisi,
          </DialogDescription>
        </DialogHeader>

        <div className='flex justify-center'>
          <img 
            src={preview} 
            alt="Preview"
            className={cn(
              'object-cover',
              type === 'profile' ? 'rounded-full w-72 h-72' : 'rounded-md max-h-[400px]'
            )}
            //className='rounded-md object-cover max-h-[400px]'
          />
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={onClose}>Cancelar</Button>
          <Button onClick={onConfirm}>Confirmar</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

export default ImageUploadModal;
