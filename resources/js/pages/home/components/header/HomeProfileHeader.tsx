import { Link, usePage } from "@inertiajs/react";

import profilePlaceholder from "@/assets/profile-placeholder.png";
import Settings from "@/features/settings/Settings";

import { Avatar, AvatarImage } from "@/components/ui/avatar";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

import { useModal } from "@/hooks/useModal";

import { validImage } from "@/utils";

import { SharedData } from "@/types";

import { menuItems } from "../../data/homeProfileItems";

function ProfileHeader() {
  const { open, setOpen } = useModal();
  const { auth } = usePage<SharedData>().props;

  const handleAction = (action: string) => {
    if (action === "openModal") {
      setOpen(true);
    }
  };

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Avatar>
          <AvatarImage src={validImage(auth.user.profilePicture, profilePlaceholder)} />
        </Avatar>
      </DropdownMenuTrigger>

      <DropdownMenuContent>
        <DropdownMenuLabel>Mi Perfil</DropdownMenuLabel>

        <DropdownMenuGroup>
          {menuItems.map((item, index) => (
            <DropdownMenuItem
              onSelect={() => {
                if (item.action) handleAction(item.action);
              }}
              asChild={!item.action}
              key={index}
            >
              {item.url ? (
                <Link className="w-full" as="button" href={item.url} method={item.method}>
                  <item.icon className="h-4 w-4 text-gray-600" />

                  {item.text}
                </Link>
              ) : (
                <>
                  <item.icon className="h-4 w-4 text-gray-600" />
                  {item.text}
                </>
              )}
            </DropdownMenuItem>
          ))}
        </DropdownMenuGroup>
      </DropdownMenuContent>

      <Settings open={open} setOpen={setOpen} />
    </DropdownMenu>
  );
}

export default ProfileHeader;
