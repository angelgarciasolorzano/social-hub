export interface LikesInfo {
  count: number;
  likedByMe: boolean;
}

export interface LikeToggleResponse {
  liked: boolean;
  likesCount: number;
}
