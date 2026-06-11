import { RiTimeZoneLine } from "react-icons/ri";

import dayjs from "dayjs";
import "dayjs/locale/es";
import relativeTime from "dayjs/plugin/relativeTime";

import { Avatar, AvatarFallback, AvatarImage } from "@/shared/components/ui/avatar";

import { validImage } from "@/shared/lib";

import type { User } from "@/shared/types";

import { profilePicturePlaceholder } from "@/shared/assets";

import postPlaceholder from "../assets/post-placeholder.png";
import type { Post } from "../types/post";

dayjs.extend(relativeTime);
dayjs.locale("es");

interface PostDetailProps {
  post: Omit<Post, "comments">;
  user: User;
}

function PostDetail({ post, user }: PostDetailProps) {
  const createdAt = dayjs(post.createdAt);

  return (
    <article className="grid gap-2">
      <header className="flex items-center gap-3">
        <Avatar>
          <AvatarImage
            alt="Foto de perfil"
            src={validImage(user.profilePicture, profilePicturePlaceholder)}
          />

          <AvatarFallback>Foto de perfil</AvatarFallback>
        </Avatar>

        <div className="flex flex-col gap-0.5">
          <div className="flex items-baseline gap-1">
            <h4 className="text-l font-bold dark:text-white">{user.name}</h4>
            <span className="text-sm font-medium text-gray-600 dark:text-gray-400">
              agregó una publicación
            </span>
          </div>

          <time className="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
            <RiTimeZoneLine className="h-4 w-4 text-gray-600 dark:text-gray-500" />
            {createdAt.fromNow()} - {createdAt.format("D [de] MMMM [del] YYYY")}
          </time>
        </div>
      </header>

      <section>
        <p className="text-sm text-gray-700 dark:text-gray-200">{post.content}</p>

        {post.image && (
          <img
            className="my-2 max-h-100 w-full rounded-md object-cover"
            onError={(e) => (e.currentTarget.src = postPlaceholder)}
            alt="Imagen de la publicación"
            src={post.image}
          />
        )}
      </section>
    </article>
  );
}

export default PostDetail;
