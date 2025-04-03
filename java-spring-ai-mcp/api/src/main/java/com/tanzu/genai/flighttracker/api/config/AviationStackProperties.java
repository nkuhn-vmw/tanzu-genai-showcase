package com.tanzu.genai.flighttracker.api.config;

import lombok.Data;
import org.springframework.boot.context.properties.ConfigurationProperties;
import org.springframework.stereotype.Component;

/**
 * Configuration properties for the AviationStack API.
 */
@Data
@Component
@ConfigurationProperties(prefix = "aviation.stack")
public class AviationStackProperties {
    private String baseUrl = "https://api.aviationstack.com/v1";
    private String accessKey;
}
