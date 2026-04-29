import { FaRegComment } from "react-icons/fa";
import { FaRegHeart } from "react-icons/fa6";
import { RiTimeZoneLine } from "react-icons/ri";

import dayjs from "dayjs";

import { CommentDialog } from "@/modules/comments";

import { Avatar, AvatarFallback, AvatarImage } from "@/shared/components/ui/avatar";
import { Button } from "@/shared/components/ui/button";
import { Separator } from "@/shared/components/ui/separator";

import { validImage } from "@/shared/lib";

import type { User } from "@/shared/types";

import { profilePicturePlaceholder } from "@/shared/assets";

import postPlaceholder from "../assets/post-placeholder.png";
import type { Post } from "../types/post";

interface PostCardProps {
  post: Post;
  user: User;
}

function PostCard({ post, user }: PostCardProps) {
  const createdAt = dayjs(post.createdAt);

  return (
    <article className="grid gap-2 rounded-xl border p-3 shadow-sm dark:border-[#343434] dark:bg-[#1D1D1D]/30">
      <header className="flex items-center gap-3 px-3">
        <Avatar>
          <AvatarImage
            alt="Foto de perfil"
            src={validImage(user.profilePicture, profilePicturePlaceholder)}
          />

          <AvatarFallback>Foto de perfil</AvatarFallback>
        </Avatar>

        <div className="flex flex-col gap-0.5">
          <div className="flex items-baseline gap-1">
            <h4 className="text-lg font-bold dark:text-white">{user.name}</h4>

            <span className="text-sm font-medium text-muted-foreground">
              agregó una publicación
            </span>
          </div>

          <time className="flex items-center gap-1 text-xs text-muted-foreground">
            <RiTimeZoneLine className="h-4 w-4 text-gray-600 dark:text-gray-500" />
            {createdAt.fromNow()} - {createdAt.format("D [de] MMMM [del] YYYY")}
          </time>
        </div>
      </header>

      <section className="mb-2">
        <p className="text-[15px] text-gray-700 dark:text-gray-200">{post.content}</p>

        {post.image && (
          <img
            className="my-2 max-h-[400px] w-full rounded-md object-cover"
            onError={(e) => (e.currentTarget.src = postPlaceholder)}
            alt="Imagen de la publicación"
            src={post.image}
          />
        )}
      </section>

      <div className="flex items-center justify-between text-sm text-muted-foreground">
        <span className="flex items-center gap-1">
          <FaRegHeart className="h-4 w-4" />
          122 me gustas
        </span>

        <span className="flex items-center gap-1">
          <FaRegComment className="h-4 w-4" />
          6000 comentarios
        </span>
      </div>

      <Separator className="my-1" />

      <footer className="flex items-center justify-between">
        <Button className="cursor-pointer" variant="outline">
          Me gusta
        </Button>

        <CommentDialog postDetail={post} user={user} />
      </footer>
    </article>
  );
}

export default PostCard;
