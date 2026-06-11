import type { PaginatedResponse, User } from "@/shared/types";

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

export type PaginatedComments = PaginatedResponse<Comment>;

export interface CommentContextPreview {
  authorName: string;
  authorProfilePicture: string | null | undefined;
  content: string;
  createdAt: string;
  kind: CommentableTypeValues;
}
