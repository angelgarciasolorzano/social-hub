import { Form, Link, router, usePage } from '@inertiajs/react';

import { IoIosNotificationsOutline } from "react-icons/io";
import { CiCirclePlus } from "react-icons/ci";
import { FaRegUser } from "react-icons/fa6";
import { MdOutlineLogout } from "react-icons/md";
import { FiHome } from "react-icons/fi";
import { LuUserRoundPlus } from "react-icons/lu";
import { LuCircleUserRound } from "react-icons/lu";
import { PiNutBold } from "react-icons/pi";
import { IoIosSearch } from "react-icons/io";

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
import { Badge } from '@/components/ui/badge';
import FriendshipController from '@/actions/App/Http/Controllers/FriendshipController';
import { SharedData } from '@/types';

import { useEchoNotification } from "@laravel/echo-react";
import { toast } from 'sonner';


function Header() {
  const { auth } = usePage<SharedData>().props;

  useEchoNotification(
      `App.Models.User.${auth.user.id}`,
      (notification) => {
          console.log(notification);
          //alert(notification.type);
          toast.info("Te llego una solicitud de amistad");
      },
  );


  return (
    <div className='flex items-center justify-between border-b border-gray-200 px-3 py-3.5'>
      <div>
        <h1 className='text-blue-500 font-bold text-2xl'>SOCIAL HUB</h1>
      </div>

      <HeaderAction />

      {/* <div className="relative">
      <IoIosNotificationsOutline className="w-8 h-8 cursor-pointer text-gray-600" />
      <div className="absolute top-10 right-0 w-64 bg-white shadow-md rounded-md max-h-80 overflow-y-auto">
        {notifications.length > 0 ? (
          notifications.map((n, i) => (
            <div key={i} className="p-2 border-b text-sm">
              <strong>{n.requester_name}</strong> te envió una solicitud
            </div>
          ))
        ) : (
          <p className="p-2 text-sm text-gray-500">Sin notificaciones</p>
        )}
      </div>
    </div> */}
    </div>
  )
}

function HeaderAction() {
  const [isOpenModal, setIsOpenModal] = useState<boolean>(false);

  return (
    <div className='flex items-center gap-4'>
      <HeaderActionSearch />
      <HeaderActionPublication isOpenModal={isOpenModal} setIsOpenModal={setIsOpenModal} />
      <HeaderActionNotifications />
      <HeaderActionProfile />
    </div>
  )
};

function HeaderActionSearch() {
  const { search_results } = usePage<SharedData>().props;

  return (
    <div>
      <Dialog>
        <Tooltip>
          <DialogTrigger asChild>
            <TooltipTrigger asChild>
              <IoIosSearch className='w-8 h-8 cursor-pointer text-gray-600' />
            </TooltipTrigger>
          </DialogTrigger>

          <TooltipContent>Buscar amigos</TooltipContent>
        </Tooltip>

        <DialogContent className='max-w-[90%] min-w-[70%] min-h-[80%] max-h-[90%] flex flex-col'>
          <DialogHeader>
            <DialogTitle>Buscar amigos</DialogTitle>

            <DialogDescription>
              Encuentra a tus amigos y comparte tus pensamientos
            </DialogDescription>
            
            <Form
              {...FriendshipController.search.form()}
              className='flex gap-2 items-center mt-1.5'
              onSuccess={(page) => console.log(page.props)}
              options={{
                preserveState: true,
              }}
            >
              {({ processing }) => (
                <>
                  <Input
                    type="text"
                    name="search"
                    placeholder="Buscar amigos"
                  />

                  <Button
                    type="submit"
                    disabled={processing}
                  >
                    {processing ? (
                      <>
                        <Loader2Icon className='h-4 w-4 animate-spin' />
                        Buscando...
                      </>
                    ) : (
                      'Buscar'
                    )}
                  </Button>
                </>
              )}
            </Form>
          </DialogHeader>

          <div className='flex flex-col gap-4 flex-1 overflow-y-auto h-[90%] pr-2'>
            {search_results && search_results.map((user) => (
              <div className='flex justify-between items-center py-2 px-2' key={user.id}>
                <div className='flex gap-2 items-center'>
                  <img 
                    src='https://avatars.dicebear.com/api/initials/1.svg' 
                    alt='Foto de perfil' 
                    className='w-12 h-12 rounded-full' 
                  />

                  <div className='space-y-1.5'>
                    <h4 className='text-sm font-semibold'>{user.name}</h4>
                    <p className='text-xs text-gray-600 font-medium'>{user.created_at}</p>
                  </div>
                </div>

                <div className='flex gap-2'>
                  <Badge 
                    variant="secondary" 
                    className='text-sm dark:text-white/80 cursor-pointer' 
                    onClick={() => router.post(FriendshipController.sendRequest.url({ user: user.id }))}
                  >
                    <LuUserRoundPlus className='w-4 h-4' />
                    Agregar amigo
                  </Badge>

                  <Badge variant="outline" className='cursor-pointer text-sm' asChild>
                    <Link href={profile.show(user.id)}>
                      <LuCircleUserRound className='w-4 h-4' />
                      Ver perfil
                    </Link>
                  </Badge>
                </div>
              </div>
            ))}
          </div>
        </DialogContent>
      </Dialog>
    </div>
  )
}

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
                href={profile.index()}
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
