import type { JSX } from "react";
import { useState } from "react";

import { FaRegComment } from "react-icons/fa";
import { FaRegHeart } from "react-icons/fa6";
import { RiTimeZoneLine } from "react-icons/ri";

import { CommentDialog } from "@/modules/comment";

import { togglePost } from "@/shared/wayfinder/actions/App/Modules/Like/Controllers/LikeController";

import { Avatar, AvatarFallback, AvatarImage } from "@/shared/components/shadcn/ui/avatar";
import { Separator } from "@/shared/components/shadcn/ui/separator";

import { useLikeToggle } from "@/shared/hooks";

import { validImage } from "@/shared/lib";
import { formatFullDate, fromNow } from "@/shared/lib/dateTime";

import { profilePicturePlaceholder } from "@/shared/assets";

import postPlaceholder from "../assets/post-placeholder.png";
import type { Post } from "../types/post";
import PostLikeButton from "./PostLikeButton";

interface PostCardProps {
  post: Post;
}

function PostCard({ post }: PostCardProps): JSX.Element {
  const [commentsCount, setCommentsCount] = useState<number>(post.commentsCount);

  const { error, isPending, likesInfo, toggleLike } = useLikeToggle(
    togglePost(post.id).url,
    post.likesInfo,
  );

  return (
    <article className="grid gap-2 rounded-xl border p-3 shadow-sm dark:border-[#343434] dark:bg-[#1D1D1D]/30">
      <header className="flex items-center gap-3 px-3">
        <Avatar>
          <AvatarImage
            alt="Foto de perfil"
            src={validImage(post.user?.profilePicture, profilePicturePlaceholder)}
          />

          <AvatarFallback>Foto de perfil</AvatarFallback>
        </Avatar>

        <div className="flex flex-col gap-0.5">
          <div className="flex items-baseline gap-1">
            <h4 className="text-lg font-bold dark:text-white">
              {post.user?.name ?? "Usuario eliminado"}
            </h4>

            <span className="text-sm font-medium text-muted-foreground">
              agregó una publicación
            </span>
          </div>

          <time className="flex items-center gap-1 text-xs text-muted-foreground">
            <RiTimeZoneLine className="h-4 w-4 text-gray-600 dark:text-gray-500" />
            {fromNow(post.createdAt)} - {formatFullDate(post.createdAt)}
          </time>
        </div>
      </header>

      <section className="mb-2">
        <p className="text-[15px] text-gray-700 dark:text-gray-200">{post.content}</p>

        {post.image && (
          <img
            className="my-2 max-h-100 w-full rounded-md object-cover"
            onError={(e) => (e.currentTarget.src = postPlaceholder)}
            alt="Imagen de la publicación"
            src={post.image}
          />
        )}
      </section>

      <div className="flex items-center justify-between text-sm text-muted-foreground">
        <span className="flex items-center gap-1">
          <FaRegHeart className="h-4 w-4" />
          {likesInfo.count} {likesInfo.count === 1 ? "me gusta" : "me gustas"}
        </span>

        <span className="flex items-center gap-1">
          <FaRegComment className="h-4 w-4" />
          {commentsCount} {commentsCount === 1 ? "comentario" : "comentarios"}
        </span>
      </div>

      <Separator className="my-1" />

      <footer className="flex items-center justify-between">
        <PostLikeButton
          error={error}
          isPending={isPending}
          likedByMe={likesInfo.likedByMe}
          onToggle={toggleLike}
        />

        <CommentDialog
          onCommentsCountChange={(delta) => {
            setCommentsCount((currentCount) => Math.max(0, currentCount + delta));
          }}
          postDetail={post}
        />
      </footer>
    </article>
  );
}

export default PostCard;
