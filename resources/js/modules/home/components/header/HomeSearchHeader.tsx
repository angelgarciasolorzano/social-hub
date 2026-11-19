import type { JSX } from "react";

import { Link } from "@inertiajs/react";

import { IoIosSearch } from "react-icons/io";

import FriendshipSearchController from "@/shared/wayfinder/actions/App/Modules/Friendship/Controllers/FriendshipSearchController";

import { Tooltip, TooltipContent, TooltipTrigger } from "@/shared/components/shadcn/ui/tooltip";

function HomeSearchHeader(): JSX.Element {
  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <Link aria-label="Buscar amigos" href={FriendshipSearchController()}>
          <IoIosSearch aria-hidden="true" className="h-8 w-8 text-muted-foreground" />
        </Link>
      </TooltipTrigger>

      <TooltipContent>Buscar amigos</TooltipContent>
    </Tooltip>
  );
}

export default HomeSearchHeader;
