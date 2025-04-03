package com.tanzu.genai.flighttracker.api.model;

import lombok.Data;
import com.fasterxml.jackson.annotation.JsonProperty;

/**
 * Model class for Aircraft information.
 */
@Data
public class Aircraft {
    private String registration;
    private String iata;
    private String icao;

    @JsonProperty("iata_code")
    private String iataCode;

    @JsonProperty("icao_code")
    private String icaoCode;

    @JsonProperty("icao24")
    private String icao24;
}
