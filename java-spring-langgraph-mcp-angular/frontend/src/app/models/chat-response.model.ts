import { ChatMessage } from './chat-message.model';
import { CityInfo } from './city-info.model';
import { EventInfo } from './event-info.model';

export interface ChatResponse {
  sessionId: string;
  message: ChatMessage;
  recommendedEvents?: EventInfo[];
  cityInfo?: CityInfo;
}
