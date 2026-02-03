import { PaginatedResponse } from "./pagination";

export interface Comment {
  content: string;
  created_at: string;
  id: number;
  replies?: Comment[];
  user: User;
}

export type PaginatedComments = PaginatedResponse<Comment>;
