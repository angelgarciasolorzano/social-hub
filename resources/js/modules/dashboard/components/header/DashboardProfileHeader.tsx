import { Link, usePage } from "@inertiajs/react";

import Settings from "@/modules/account/setting/Setting";

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

import { validImage } from "@/shared/lib/validImage";

import type { SharedData } from "@/shared/types";

import { profilePicturePlaceholder } from "@/shared/assets";

import type { ActionItemType } from "../../data/dashboardProfileItems";
import { ActionItem, menuItems } from "../../data/dashboardProfileItems";

function ProfileHeader() {
  const { open, setOpen } = useModal();
  const { auth } = usePage<SharedData>().props;

  const handleAction = (action: ActionItemType) => {
    if (action === ActionItem.OpenConfigModal) {
      setOpen(true);
    }
  };

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Avatar>
          <AvatarImage src={validImage(auth.user.profilePicture, profilePicturePlaceholder)} />
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
                <Link
                  className="w-full"
                  as="button"
                  href={item.url}
                  {...(item.method ? { method: item.method } : {})}
                >
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
