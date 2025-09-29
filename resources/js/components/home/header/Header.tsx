import { Form, Link } from '@inertiajs/react';

import { IoIosNotificationsOutline } from "react-icons/io";
import { CiCirclePlus } from "react-icons/ci";
import { FaRegUser } from "react-icons/fa6";
import { MdOutlineLogout } from "react-icons/md";
import { FiHome } from "react-icons/fi";
import { PiNutBold } from "react-icons/pi";

import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';

import { 
  Dialog, 
  DialogClose, 
  DialogContent, 
  DialogDescription, 
  DialogFooter, 
  DialogHeader, 
  DialogTitle, 
  DialogTrigger 
} from '@/components/ui/dialog';

import { 
  DropdownMenu, 
  DropdownMenuContent, 
  DropdownMenuGroup, 
  DropdownMenuItem, 
  DropdownMenuLabel, 
  DropdownMenuTrigger 
} from '@/components/ui/dropdown-menu';

import { home, logout } from '@/routes';

import profile from '@/routes/profile';
import PostController from '@/actions/App/Http/Controllers/PostController';
import InputError from '@/components/input-error';
import { Loader2Icon } from 'lucide-react';
import { Dispatch, SetStateAction, useState } from 'react';

function Header() {
  return (
    <div className='flex items-center justify-between border-b border-gray-200 px-3 py-3.5'>
      <div>
        <h1 className='text-blue-500 font-bold text-2xl'>SOCIAL HUB</h1>
      </div>

      <HeaderAction />
    </div>
  )
}

function HeaderAction() {
  const [isOpenModal, setIsOpenModal] = useState<boolean>(false);

  return (
    <div className='flex items-center gap-4'>
      <HeaderActionPublication isOpenModal={isOpenModal} setIsOpenModal={setIsOpenModal} />
      <HeaderActionNotifications />
      <HeaderActionProfile />
    </div>
  )
};

interface HeaderActionPublicationProps {
  isOpenModal: boolean;
  setIsOpenModal: Dispatch<SetStateAction<boolean>>;
};

function HeaderActionPublication({ isOpenModal, setIsOpenModal }: HeaderActionPublicationProps) {
  return (
    <div>
      <Dialog open={isOpenModal} onOpenChange={setIsOpenModal}>
        <Tooltip>
          <DialogTrigger asChild>
            <TooltipTrigger asChild>
              <CiCirclePlus className='w-8 h-8 cursor-pointer text-gray-600' />
            </TooltipTrigger>
          </DialogTrigger>

          <TooltipContent>Crear publicación</TooltipContent>
        </Tooltip>

        <DialogContent className='pb-2.5'>
          <DialogHeader>
            <DialogTitle>Crear publicación</DialogTitle>

            <DialogDescription>
              Comparte tus pensamientos con el mundo
            </DialogDescription>
          </DialogHeader>

          <HeaderActionPublicationForm setIsOpenModal={setIsOpenModal} />
        </DialogContent>
      </Dialog>
    </div>
  )
};

type HeaderActionPublicationFormProps = Pick<HeaderActionPublicationProps, 'setIsOpenModal'>;

function HeaderActionPublicationForm({ setIsOpenModal }: HeaderActionPublicationFormProps) {
  return (
    <Form
      {...PostController.store.form()}
      id='post-form'
      onSuccess={() => setIsOpenModal(false)}
      className='my-2.5'
    >
      {({ processing, errors }) => (
        <>
          <div className='grid gap-4'>
            <div className='grid gap-1.5'>
              <Label htmlFor="content">Contenido</Label>

              <Textarea
                id="content"
                name="content"
                required
                minLength={10}
                placeholder='Escribe tu publicación'
              />

              <InputError message={errors.content} />
            </div>

            <div>
              <Label htmlFor="image_file">Imagen</Label>

              <Input
                id="image_file"
                type="file"
                name="image_file"
                accept='image/*'
              />

              <InputError message={errors.image_file} />
            </div>
          </div>

          <DialogFooter className='mt-6'>
            <DialogClose asChild>
              <Button type='button' variant="outline">Cancelar</Button>
            </DialogClose>

            <Button 
              type='submit'
              form='post-form'
              disabled={processing}
            >
              {processing ? (
                <>
                  <Loader2Icon className='h-4 w-4 animate-spin' />
                  Publicando...
                </>
              ) : (
                'Publicar'
              )}
            </Button>
          </DialogFooter>
        </>
      )}
    </Form>
  )
}

function HeaderActionNotifications() {
  return (
    <div>
      <Tooltip>
        <TooltipTrigger asChild>
          <IoIosNotificationsOutline className='w-8 h-8 cursor-pointer text-gray-600' />
        </TooltipTrigger>

        <TooltipContent>
          Notificaciones
        </TooltipContent>
      </Tooltip>
    </div>
  )
};

function HeaderActionProfile() {
  return (
    <div>
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Skeleton className='w-10 h-10 rounded-full cursor-pointer' />
        </DropdownMenuTrigger>

        <DropdownMenuContent>
          <DropdownMenuLabel>Mi Perfil</DropdownMenuLabel>

          <DropdownMenuGroup>
            <DropdownMenuItem asChild>
              <Link
                href={home.url()}
                as="button"
                className='w-full'
              >
                <FiHome className='w-4 h-4 text-gray-600' />
                Inicio
              </Link>
            </DropdownMenuItem>

            <DropdownMenuItem asChild>
              <Link
                href={profile.show.url()}
                as="button"
                className='w-full'
              >
                <FaRegUser className='w-4 h-4 text-gray-600' />
                Perfil
              </Link>
            </DropdownMenuItem>

            <DropdownMenuItem>
              <PiNutBold className='w-4 h-4 text-gray-600' />
              Configuración
            </DropdownMenuItem>

            <DropdownMenuItem asChild>
              <Link
                href={logout()}
                method="post"
                as="button"
              >
                <MdOutlineLogout className='w-4 h-4 text-gray-600' />
                Cerrar sesión
              </Link>
            </DropdownMenuItem>
          </DropdownMenuGroup>
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  )
}

export default Header;
