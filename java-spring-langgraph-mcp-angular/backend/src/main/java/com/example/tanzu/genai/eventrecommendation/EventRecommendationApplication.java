package com.example.tanzu.genai.eventrecommendation;

import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;
import org.springframework.web.reactive.config.EnableWebFlux;

@SpringBootApplication
public class EventRecommendationApplication {

    public static void main(String[] args) {
        SpringApplication.run(EventRecommendationApplication.class, args);
    }
}
