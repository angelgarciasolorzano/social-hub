export interface PostCollection {
  data: Post[];
}

export interface Post {
  content: string;
  createdAt: string;
  id: number;
  image?: string;
}
