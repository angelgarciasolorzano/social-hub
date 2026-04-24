export interface PostCollection {
  data: Post[];
}

export interface Post {
  content: string;
  createdAt: string;
  id: number;
  image?: string;
}

export type PostFormData = Pick<Post, "content"> & {
  image_file?: File;
};
