import { Link, usePage } from "@inertiajs/react";

import profilePlaceholder from "@/assets/profile-placeholder.png";
import Settings from "@/features/settings/Settings";
import { validImage } from "@/shared/lib/validImage";
import type { SharedData } from "@/types";

import { Avatar, AvatarImage } from "@/shared/components/ui/avatar";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuTrigger,
} from "@/shared/components/ui/dropdown-menu";

import { useModal } from "@/shared/hooks/useModal";

import { menuItems } from "../../data/dashboardProfileItems";

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
