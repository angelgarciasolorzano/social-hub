import { Form, Link, router, usePage } from "@inertiajs/react";

import { IoIosSearch } from "react-icons/io";
import { LuCircleUserRound, LuUserRoundPlus } from "react-icons/lu";

import { Loader2Icon } from "lucide-react";

import FriendshipController from "@/actions/App/Http/Controllers/FriendshipController";

import profile from "@/routes/profile";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";

import { SharedData } from "@/types";

function SearchHeader() {
  const { search_results } = usePage<SharedData>().props;

  return (
    <div>
      <Dialog>
        <Tooltip>
          <DialogTrigger asChild>
            <TooltipTrigger asChild>
              <IoIosSearch className="h-8 w-8 cursor-pointer text-gray-600" />
            </TooltipTrigger>
          </DialogTrigger>

          <TooltipContent>Buscar amigos</TooltipContent>
        </Tooltip>

        <DialogContent className="flex max-h-[90%] min-h-[80%] max-w-[90%] min-w-[70%] flex-col">
          <DialogHeader>
            <DialogTitle>Buscar amigos</DialogTitle>

            <DialogDescription>
              Encuentra a tus amigos y comparte tus pensamientos
            </DialogDescription>

            <Form
              {...FriendshipController.search.form()}
              className="mt-1.5 flex items-center gap-2"
              onSuccess={(page) => console.log(page.props)}
              options={{
                preserveState: true,
              }}
            >
              {({ processing }) => (
                <>
                  <Input name="search" type="text" placeholder="Buscar amigos" />

                  <Button type="submit" disabled={processing}>
                    {processing ? (
                      <>
                        <Loader2Icon className="h-4 w-4 animate-spin" />
                        Buscando...
                      </>
                    ) : (
                      "Buscar"
                    )}
                  </Button>
                </>
              )}
            </Form>
          </DialogHeader>

          <div className="flex h-[90%] flex-1 flex-col gap-4 overflow-y-auto pr-2">
            {search_results &&
              search_results.map((user) => (
                <div className="flex items-center justify-between px-2 py-2" key={user.id}>
                  <div className="flex items-center gap-2">
                    <img
                      className="h-12 w-12 rounded-full"
                      alt="Foto de perfil"
                      src="https://avatars.dicebear.com/api/initials/1.svg"
                    />

                    <div className="space-y-1.5">
                      <h4 className="text-sm font-semibold">{user.name}</h4>
                      <p className="text-xs font-medium text-gray-600">{user.created_at}</p>
                    </div>
                  </div>

                  <div className="flex gap-2">
                    <Badge
                      className="cursor-pointer text-sm dark:text-white/80"
                      onClick={() =>
                        router.post(FriendshipController.sendRequest.url({ user: user.id }))
                      }
                      variant="secondary"
                    >
                      <LuUserRoundPlus className="h-4 w-4" />
                      Agregar amigo
                    </Badge>

                    <Badge className="cursor-pointer text-sm" asChild variant="outline">
                      <Link href={profile.show(user.id)}>
                        <LuCircleUserRound className="h-4 w-4" />
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
  );
}

export default SearchHeader;
