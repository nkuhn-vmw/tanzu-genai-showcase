package com.example.tanzu.genai.eventrecommendation.model;

import lombok.AllArgsConstructor;
import lombok.Builder;
import lombok.Data;
import lombok.NoArgsConstructor;

import java.util.List;

@Data
@Builder
@NoArgsConstructor
@AllArgsConstructor
public class ChatResponse {
    private String sessionId;
    private ChatMessage message;
    private List<EventInfo> recommendedEvents;
    private CityInfo cityInfo;
}
