package com.example.tanzu.genai.eventrecommendation.model;

import lombok.AllArgsConstructor;
import lombok.Builder;
import lombok.Data;
import lombok.NoArgsConstructor;

import java.time.LocalDateTime;

@Data
@Builder
@NoArgsConstructor
@AllArgsConstructor
public class EventInfo {
    private String id;
    private String name;
    private String type;
    private String url;
    private String image;
    private LocalDateTime startDateTime;
    private String venue;
    private String city;
    private String country;
    private String priceRange;
    private String genre;
    private String subGenre;
    private Boolean familyFriendly;
}
