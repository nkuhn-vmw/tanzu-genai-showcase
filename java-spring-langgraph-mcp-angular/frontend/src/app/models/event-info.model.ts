export interface EventInfo {
  id: string;
  name: string;
  type: string;
  url: string;
  image?: string;
  startDateTime: string;
  venue: string;
  city: string;
  country: string;
  priceRange?: string;
  genre?: string;
  subGenre?: string;
  familyFriendly?: boolean;
}
