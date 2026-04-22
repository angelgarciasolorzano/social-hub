import { InfiniteScroll, usePage } from "@inertiajs/react";

import { GoPlus } from "react-icons/go";
import { MdOutlineEditNote } from "react-icons/md";

import { Button } from "@/shared/components/ui/button";

import { useModal } from "@/shared/hooks/useModal";

import { validImage } from "@/shared/lib";

import type { SharedData, User } from "@/shared/types";

import { profileCoverPlaceholder, profilePicturePlaceholder } from "@/shared/assets";

import { DashboardLayout } from "../dashboard";
import { PostCard, type PostCollection, PostDialog } from "../post";
import ProfileCover from "./components/ProfileCover";
import ProfilePicture from "./components/ProfilePicture";

interface ProfileProps {
  posts: PostCollection;
  user: User;
}

function Profile({ posts, user }: ProfileProps) {
  const { open, setOpen } = useModal();
  const { auth } = usePage<SharedData>().props;

  return (
    <DashboardLayout>
      <div className="flex flex-col gap-4 px-4 py-3">
        <div className="relative">
          <ProfileCover coverImage={validImage(user.coverImage, profileCoverPlaceholder)} />
          <ProfilePicture
            profilePicture={validImage(user.profilePicture, profilePicturePlaceholder)}
          />
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

        <InfiniteScroll buffer={200} data="posts" preserveUrl>
          {({ loadingNext }) => (
            <div className="flex flex-col gap-8">
              {posts.data.map((post) => (
                <PostCard key={post.id} post={post} user={user} />
              ))}

              {loadingNext && (
                <div className="my-4">
                  <div className="h-24 animate-pulse rounded bg-gray-200" />
                </div>
              )}
            </div>
          )}
        </InfiniteScroll>
      </div>

      <PostDialog open={open} setOpen={setOpen} />
    </DashboardLayout>
  );
}

export default Profile;
