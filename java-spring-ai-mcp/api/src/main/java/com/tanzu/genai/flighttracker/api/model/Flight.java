package com.tanzu.genai.flighttracker.api.model;

import lombok.Data;

/**
 * Model class for Flight information.
 */
@Data
public class Flight {
    private String flightDate;
    private String flightStatus;
    private String flightNumber;
    private Airline airline;
    private Departure departure;
    private Arrival arrival;
    private Aircraft aircraft;
    private Flight live;
}
