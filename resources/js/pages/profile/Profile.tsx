import { usePage } from "@inertiajs/react";

import { GoPlus } from "react-icons/go";
import { MdOutlineEditNote } from "react-icons/md";

import { Publication, PublicationCard } from "@/features/publication";

import { Button } from "@/components/ui/button";

import HomeLayout from "@/pages/home/layouts/HomeLayout";
import ProfileCover from "@/pages/profile/components/ProfileCover";

import { useModal } from "@/hooks/useModal";

import { PostData, SharedData, User } from "@/types";

import ProfilePicture from "./components/ProfilePicture";

interface ProfileProps {
  posts: PostData;
  user: User;
}

function Profile({ posts, user }: ProfileProps) {
  const { open, setOpen } = useModal();
  const { auth } = usePage<SharedData>().props;

  return (
    <HomeLayout>
      <div className="flex flex-col gap-4 px-4 py-3">
        <div className="relative">
          <ProfileCover coverImage={user.coverImage ?? "https://picsum.photos/200"} />
          <ProfilePicture profilePicture={user.profilePicture ?? "https://picsum.photos/200"} />
        </div>

        <div className="px-6 pt-8">
          <h4 className="text-lg font-bold dark:text-white">{user.name}</h4>
          <span className="text-sm font-medium text-gray-600 dark:text-gray-400">344 amigos</span>
        </div>

        <div className="flex items-center gap-4">
          {auth.user.id === user.id && (
            <>
              <Button
                className="cursor-pointer bg-blue-700 hover:bg-blue-800 dark:bg-blue-600 dark:text-white dark:hover:bg-blue-700"
                onClick={() => setOpen(true)}
              >
                <GoPlus className="h-4 w-4" />
                Agregar publicación
              </Button>

              <Button className="cursor-pointer" variant="secondary">
                <MdOutlineEditNote className="h-4 w-4" />
                Editar perfil
              </Button>
            </>
          )}
        </div>

        <div className="flex flex-col gap-8">
          {posts.data.map((post) => (
            <PublicationCard key={post.id} post={post} user={user} />
          ))}
        </div>
      </div>

      <Publication open={open} setOpen={setOpen} />
    </HomeLayout>
  );
}

export default Profile;
