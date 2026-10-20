import type { LikesInfo } from "@/shared/types/likes";

export interface PostCollection {
  data: Post[];
}

export interface PostAuthor {
  id: number;
  name: string;
  profilePicture: string;
}

export interface Post {
  commentsCount: number;
  content: string;
  createdAt: string;
  id: number;
  image?: string;
  likesInfo: LikesInfo;
  user: null | PostAuthor;
}

export type PostFormData = Pick<Post, "content"> & {
  image_file?: File;
};
