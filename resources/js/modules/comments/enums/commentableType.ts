export const CommentableType = {
  COMMENT: "comment",
  POST: "post",
} as const;

export type CommentableTypeValues = (typeof CommentableType)[keyof typeof CommentableType];
