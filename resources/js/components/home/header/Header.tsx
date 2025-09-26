import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Skeleton } from '@/components/ui/skeleton';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Form, Link } from '@inertiajs/react';
import {CiCirclePlus} from "react-icons/ci";
import { IoIosNotificationsOutline } from "react-icons/io";

import { home } from '@/routes';

import profile from '@/routes/profile';
import PostController from '@/actions/App/Http/Controllers/PostController';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Input } from '@/components/ui/input';
import { LoaderCircle } from 'lucide-react';

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
    return (
        <div className='flex items-center gap-4'>
            <HeaderActionPublication />
            <HeaderActionNotifications />
            <HeaderActionProfile />
        </div>
    )
};

function HeaderActionPublication() {
    return (
        <div>
            <Dialog>
                <Tooltip>
                    <DialogTrigger asChild>
                        <TooltipTrigger asChild>
                            <CiCirclePlus className='w-8 h-8 cursor-pointer text-gray-600' />
                        </TooltipTrigger>
                    </DialogTrigger>

                    <TooltipContent>
                        Crear publicación
                    </TooltipContent>
                </Tooltip>

                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Crear publicación</DialogTitle>

                        <DialogDescription>
                            Comparte tus pensamientos con el mundo
                        </DialogDescription>
                    </DialogHeader>

                    <Form
                        {...PostController.store.form()}
                        id='post-form'
                        className='my-2.5'
                    >
                        {({ processing}) => (
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
                                    </div>

                                    <div>
                                        <Label htmlFor="image_file">Imagen</Label>

                                        <Input
                                            id="image_file"
                                            type="file"
                                            name="image_file"
                                            accept='image/*'
                                        />
                                    </div>
                                </div>

                                {processing && (
                                    <div className='flex items-center gap-2'>
                                        <LoaderCircle className='h-4 w-4 animate-spin' />
                                        <span>Publicando...</span>
                                    </div>
                                )}
                            </>
                        )}
                    </Form>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type='button' variant="outline">Cancelar</Button>
                        </DialogClose>

                        <Button 
                            type='submit'
                            form='post-form'
                        >
                            Crear
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
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
                                Inicio
                            </Link>
                        </DropdownMenuItem>

                        <DropdownMenuItem asChild>
                            <Link
                                href={profile.show.url()}
                                as="button"
                                className='w-full'
                            >
                                Perfil
                            </Link>
                        </DropdownMenuItem>

                        <DropdownMenuItem>Configuración</DropdownMenuItem>

                        <DropdownMenuItem asChild>
                            <Link
                                href="/logout"
                                method="post"
                                as="button"
                            >
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
