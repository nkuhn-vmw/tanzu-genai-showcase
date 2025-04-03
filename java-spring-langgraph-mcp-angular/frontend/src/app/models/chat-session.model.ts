import { ChatMessage } from './chat-message.model';
import { CityInfo } from './city-info.model';
import { EventInfo } from './event-info.model';

export interface ChatSession {
  id: string;
  createdAt: string;
  lastUpdatedAt: string;
  messages: ChatMessage[];
  currentCity?: CityInfo;
  recommendedEvents: EventInfo[];
}
