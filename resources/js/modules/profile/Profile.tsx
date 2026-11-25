import type { JSX } from "react";

import { InfiniteScroll, Link, router, usePage } from "@inertiajs/react";

import { GoPlus } from "react-icons/go";
import { MdOutlineEditNote } from "react-icons/md";

import { index as friendshipsIndex } from "@/shared/wayfinder/routes/friendships";

import { Button } from "@/shared/components/shadcn/ui/button";

import { useTranslation } from "@/shared/hooks";
import { useDialog } from "@/shared/hooks/useDialog";

import { validImage } from "@/shared/lib";

import type { SharedData, User } from "@/shared/types";

import { profileCoverPlaceholder, profilePicturePlaceholder } from "@/shared/assets";

import FriendshipButton from "../friendship/components/button/FriendshipButton";
import { HomeLayout } from "../home";
import { PostCard, type PostCollection, PostDialog } from "../post";
import ProfileCover from "./components/ProfileCover";
import ProfilePicture from "./components/ProfilePicture";
import type { ProfileFriendship } from "./types/profileFriendship";

interface ProfileProps {
  posts: PostCollection;
  profileFriendship: ProfileFriendship;
  user: User;
}

function Profile({ posts, profileFriendship, user }: ProfileProps): JSX.Element {
  const { open, setOpen } = useDialog();
  const { auth } = usePage<SharedData>().props;
  const { tChoice } = useTranslation();

  const friendsCountText = tChoice(
    "friendship/button.profile.friends_count",
    profileFriendship.friendsCount,
    { count: profileFriendship.friendsCount },
  );

  return (
    <HomeLayout>
      <div className="flex flex-col gap-4 px-4 py-3">
        <div className="relative">
          <ProfileCover coverImage={validImage(user.coverImage, profileCoverPlaceholder)} />

          <ProfilePicture
            profilePicture={validImage(user.profilePicture, profilePicturePlaceholder)}
          />
        </div>

        <div className="px-6 pt-8">
          <h4 className="text-lg font-bold dark:text-white">{user.name}</h4>

          <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm font-medium text-gray-600 dark:text-gray-400">
            {profileFriendship.isOwnProfile ? (
              <Link className="hover:underline" href={friendshipsIndex()}>
                {friendsCountText}
              </Link>
            ) : (
              <span>{friendsCountText}</span>
            )}

            {!profileFriendship.isOwnProfile && profileFriendship.mutualFriendsCount > 0 && (
              <span>
                {tChoice(
                  "friendship/button.profile.mutual_friends",
                  profileFriendship.mutualFriendsCount,
                  { count: profileFriendship.mutualFriendsCount },
                )}
              </span>
            )}
          </div>
        </div>

        {!profileFriendship.isOwnProfile && profileFriendship.friendshipState !== null && (
          <div className="flex px-6">
            <FriendshipButton
              friendshipId={profileFriendship.friendshipId}
              friendshipState={profileFriendship.friendshipState}
              key={user.id}
              onChange={() => {
                router.reload({ only: ["profileFriendship", "friendRequests"] });
              }}
              userId={user.id}
              userName={user.name}
            />
          </div>
        )}

        <div className="flex items-center gap-4">
          {auth.user.id === user.id && (
            <>
              <Button
                className="cursor-pointer bg-blue-700 hover:bg-blue-800 dark:bg-blue-600 dark:text-white dark:hover:bg-blue-700"
                onClick={() => {
                  setOpen(true);
                }}
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
                <PostCard key={post.id} post={post} />
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
    </HomeLayout>
  );
}

export default Profile;
