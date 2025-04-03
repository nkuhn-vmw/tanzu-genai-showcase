package com.tanzu.genai.flighttracker.server;

import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;
import org.springframework.context.annotation.ComponentScan;

/**
 * Main application class for Flight Tracker MCP Server.
 */
@SpringBootApplication
@ComponentScan(basePackages = {"com.tanzu.genai.flighttracker"})
public class FlightTrackerMcpServerApplication {

    public static void main(String[] args) {
        SpringApplication.run(FlightTrackerMcpServerApplication.class, args);
    }
}
