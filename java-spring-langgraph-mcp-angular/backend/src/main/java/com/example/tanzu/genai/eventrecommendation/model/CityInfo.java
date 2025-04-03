package com.example.tanzu.genai.eventrecommendation.model;

import lombok.AllArgsConstructor;
import lombok.Builder;
import lombok.Data;
import lombok.NoArgsConstructor;

@Data
@Builder
@NoArgsConstructor
@AllArgsConstructor
public class CityInfo {
    private String name;
    private String country;
    private Double latitude;
    private Double longitude;
    private Long population;
    private Boolean isCapital;
}
