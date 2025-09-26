import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Skeleton } from '@/components/ui/skeleton';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Link } from '@inertiajs/react';
import {CiCirclePlus} from "react-icons/ci";
import { IoIosNotificationsOutline } from "react-icons/io";

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
                                Aquí va el formulario para crear una publicación
                            </DialogDescription>
                        </DialogHeader>

                       Hola

                        <DialogFooter>
                            <DialogClose asChild>
                                <Button type='button' variant="outline">Cancelar</Button>
                            </DialogClose>

                            <Button type='button'>Crear</Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>

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
                                    href="/home"
                                    method="get"
                                    as="button"
                                    className='w-full'
                                >
                                    Inicio
                                </Link>
                            </DropdownMenuItem>

                            <DropdownMenuItem asChild>
                                <Link
                                    href="/home/profile"
                                    method="get"
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
        </div>
    )
}

export default Header;
