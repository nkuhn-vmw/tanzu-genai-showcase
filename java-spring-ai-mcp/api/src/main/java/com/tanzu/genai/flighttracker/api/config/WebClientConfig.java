package com.tanzu.genai.flighttracker.api.config;

import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;
import org.springframework.web.reactive.function.client.WebClient;

/**
 * Configuration for the WebClient used to make API requests.
 */
@Configuration
public class WebClientConfig {

    @Bean
    public WebClient webClient(AviationStackProperties properties) {
        return WebClient.builder()
            .baseUrl(properties.getBaseUrl())
            .build();
    }
}
