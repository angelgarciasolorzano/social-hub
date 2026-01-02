import { Avatar, AvatarImage } from '@/components/ui/avatar'
import { DropdownMenu, DropdownMenuContent, DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import React from 'react'
import { menuItems } from './data/items'
import { Link } from '@inertiajs/react'
import { useModal } from '@/hooks/useModal'
import ProfileModal from './components/ProfileModal'

function Profile() {
  const {open, setOpen} = useModal();

  const handleAction = (action: string) => {
    if (action === 'openModal') {
      setOpen(true);
    }
  };

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Avatar>
          <AvatarImage src='' />
        </Avatar>
      </DropdownMenuTrigger>

      <DropdownMenuContent>
        <DropdownMenuLabel>Mi Perfil</DropdownMenuLabel>

        <DropdownMenuGroup>
          {menuItems.map((item, index) => (
            <DropdownMenuItem
              key={index}
              asChild={!item.action}
              onSelect={() => {
                if (item.action) handleAction(item.action);
              }}
            >
              {item.url ? (
                <Link
                  href={item.url}
                  method={item.method}
                  as="button"
                  className='w-full'
                >
                  <item.icon className='w-4 h-4 text-gray-600' />

                  {item.text}
                </Link>
              ) : (
                <>
                  <item.icon className='w-4 h-4 text-gray-600' />
                  {item.text}
                </>
              )}
            </DropdownMenuItem>
          ))}
        </DropdownMenuGroup>
      </DropdownMenuContent>

      <ProfileModal open={open} setOpen={setOpen} />
    </DropdownMenu>
  )
}

export default Profile;
