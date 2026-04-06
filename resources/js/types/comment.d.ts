import { PaginatedResponse } from "../shared/types/pagination";

interface CommentReplies {
  hasReplies: boolean;
  repliesCount: number;
}

export interface Comment {
  content: string;
  createdAt: string;
  id: number;
  repliesInfo: CommentReplies;
  user: User;
}

export type PaginatedComments = PaginatedResponse<Comment>;
