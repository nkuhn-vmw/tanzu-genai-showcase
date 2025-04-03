package com.tanzu.genai.flighttracker.client;

import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;
import org.springframework.context.annotation.ComponentScan;

/**
 * Main application class for Flight Tracker Client.
 */
@SpringBootApplication
@ComponentScan(basePackages = {"com.tanzu.genai.flighttracker"})
public class FlightTrackerClientApplication {

    public static void main(String[] args) {
        SpringApplication.run(FlightTrackerClientApplication.class, args);
    }
}
