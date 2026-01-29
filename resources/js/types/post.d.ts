export interface PostCollection {
  data: Post[];
}

export interface Post {
  comments: Comment[];
  content: string;
  createdAt: string;
  id: number;
  image?: string;
}
