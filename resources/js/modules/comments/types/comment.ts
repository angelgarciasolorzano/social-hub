import type { User } from "@/shared/types";

import type { CommentableTypeValues } from "../enums/commentableType";

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

export type CommentFormData = Pick<Comment, "content"> & {
  commentable_id: number;
  commentable_type: CommentableTypeValues;
};

export interface PaginatedComments {
  data: Comment[];
  next_cursor: string | null;
  next_page_url: string | null;
  path: string;
  per_page: number;
  prev_cursor: string | null;
  prev_page_url: string | null;
}

export type CommentsLoadFailure = "more" | "refresh";

export interface CommentContextPreview {
  authorName: string;
  authorProfilePicture: string | null | undefined;
  content: string;
  createdAt: string;
  kind: CommentableTypeValues;
}
