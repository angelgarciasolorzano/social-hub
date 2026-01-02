import FriendshipController from '@/actions/App/Http/Controllers/FriendshipController'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import { SharedData } from '@/types'
import { Form, Link, router, usePage } from '@inertiajs/react'
import { Loader2Icon } from 'lucide-react'

import { LuUserRoundPlus } from "react-icons/lu";
import { LuCircleUserRound } from "react-icons/lu";
import { IoIosSearch } from "react-icons/io";
import profile from '@/routes/profile'

function SearchHeader() {
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

export default SearchHeader
