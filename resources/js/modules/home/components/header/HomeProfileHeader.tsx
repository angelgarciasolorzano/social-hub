import { Link, usePage } from "@inertiajs/react";

import { Avatar, AvatarImage } from "@/shared/components/shadcn/ui/avatar";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuTrigger,
} from "@/shared/components/shadcn/ui/dropdown-menu";

import { validImage } from "@/shared/lib/validImage";

import type { SharedData } from "@/shared/types";

import { profilePicturePlaceholder } from "@/shared/assets";

import { homeHeaderProfileMenuItems } from "../../data/homeProfileItems";

function HomeProfileHeader() {
  const { auth } = usePage<SharedData>().props;

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
          {homeHeaderProfileMenuItems.map((item) => (
            <DropdownMenuItem asChild key={item.key}>
              <Link
                className="cursor-pointer"
                href={item.url}
                viewTransition
                {...(item.method ? { method: item.method } : {})}
              >
                <item.icon className="h-4 w-4 text-gray-600" />
                {item.label}
              </Link>
            </DropdownMenuItem>
          ))}
        </DropdownMenuGroup>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

export default HomeProfileHeader;
