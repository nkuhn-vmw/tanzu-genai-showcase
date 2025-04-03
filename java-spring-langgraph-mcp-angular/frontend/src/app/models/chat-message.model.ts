export enum MessageType {
  TEXT = 'TEXT',
  EVENT_RECOMMENDATION = 'EVENT_RECOMMENDATION',
  CITY_INFO = 'CITY_INFO'
}

export interface ChatMessage {
  id: string;
  role: 'user' | 'assistant';
  content: string;
  timestamp: string;
  type: MessageType;
}
