import { FaRegComment } from "react-icons/fa";
import { FaRegHeart } from "react-icons/fa6";
import { RiTimeZoneLine } from "react-icons/ri";

import dayjs from "dayjs";
import "dayjs/locale/es";
import relativeTime from "dayjs/plugin/relativeTime";

import CommentModal from "@/components/comment/CommentModal";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import { Separator } from "@/components/ui/separator";

import { Post, User } from "@/types";

dayjs.extend(relativeTime);
dayjs.locale("es");

interface PublicationCardProps {
  post: Post;
  user: User;
}

function PublicationCard({ post, user }: PublicationCardProps) {
  const { comments, ...postDetail } = post;
  const createdAt = dayjs(post.createdAt);

  return (
    <article className="grid gap-2 rounded-lg border p-3 shadow-md dark:border-gray-700">
      <header className="flex items-center gap-3">
        <Avatar>
          <AvatarImage
            src={user.avatar ?? "https://avatars.dicebear.com/api/initials/1.svg"}
            alt="Foto de perfil"
          />

          <AvatarFallback>Foto de perfil</AvatarFallback>
        </Avatar>

        <div className="flex flex-col gap-0.5">
          <div className="flex items-baseline gap-1">
            <h4 className="text-lg font-bold dark:text-white">{user.name}</h4>

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
        <p className="text-[15px] text-gray-700 dark:text-gray-200">{post.content}</p>

        {post.image && (
          <img
            src={post.image}
            alt="Imagen de la publicación"
            className="my-2 max-h-[400px] w-full rounded-md object-cover"
          />
        )}
      </section>

      <div className="flex items-center justify-between text-sm text-gray-500 dark:text-gray-400">
        <span className="flex items-center gap-1">
          <FaRegHeart className="h-4 w-4" />
          122 me gustas
        </span>

        <span className="flex items-center gap-1">
          <FaRegComment className="h-4 w-4" />
          6000 comentarios
        </span>
      </div>

      <Separator className="my-1 dark:bg-gray-700" />

      <footer className="flex items-center justify-between">
        <Button variant="outline" className="cursor-pointer">
          Me gusta
        </Button>

        <CommentModal postDetail={postDetail} comments={comments} user={user} />
      </footer>
    </article>
  );
}

export default PublicationCard;
