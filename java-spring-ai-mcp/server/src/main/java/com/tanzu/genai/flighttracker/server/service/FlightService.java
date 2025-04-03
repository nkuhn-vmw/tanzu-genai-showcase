package com.tanzu.genai.flighttracker.server.service;

import com.tanzu.genai.flighttracker.api.client.AviationStackClient;
import com.tanzu.genai.flighttracker.api.model.*;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.ai.annotation.Tool;
import org.springframework.ai.annotation.ToolParam;
import org.springframework.stereotype.Service;

import java.util.Arrays;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

/**
 * Service that provides flight-related functionality as MCP tools.
 */
@Slf4j
@Service
@RequiredArgsConstructor
public class FlightService {

    private final AviationStackClient aviationStackClient;

    /**
     * Retrieves real-time flight information.
     *
     * @param flightNumber Optional flight number to filter by
     * @param airline Optional airline to filter by
     * @param departureIata Optional departure airport IATA code to filter by
     * @param arrivalIata Optional arrival airport IATA code to filter by
     * @param flightStatus Optional flight status to filter by (e.g., "active", "scheduled", "landed", "cancelled", "incident", "diverted")
     * @param limit Optional limit for the number of results (default: 10)
     * @return List of flights matching the criteria
     */
    @Tool(name = "get_real_time_flights",
          description = "Get real-time information about flights. You can filter by flight number, airline, departure/arrival airports, or flight status.")
    public List<Flight> getRealTimeFlights(
            @ToolParam(name = "flight_number", description = "Flight number (e.g., 'UA123')") String flightNumber,
            @ToolParam(name = "airline", description = "Airline name or code") String airline,
            @ToolParam(name = "departure_iata", description = "Departure airport IATA code (e.g., 'LAX')") String departureIata,
            @ToolParam(name = "arrival_iata", description = "Arrival airport IATA code (e.g., 'JFK')") String arrivalIata,
            @ToolParam(name = "flight_status", description = "Flight status (active, scheduled, landed, cancelled, incident, diverted)") String flightStatus,
            @ToolParam(name = "limit", description = "Maximum number of results to return (default: 10)") Integer limit) {

        Map<String, Object> params = new HashMap<>();
        if (flightNumber != null) params.put("flight_number", flightNumber);
        if (airline != null) params.put("airline_name", airline);
        if (departureIata != null) params.put("dep_iata", departureIata);
        if (arrivalIata != null) params.put("arr_iata", arrivalIata);
        if (flightStatus != null) params.put("flight_status", flightStatus);
        params.put("limit", limit != null ? limit : 10);

        log.info("Fetching real-time flights with params: {}", params);
        ApiResponse<Flight[]> response = aviationStackClient.getRealTimeFlights(params).block();

        if (response != null && response.getData() != null) {
            return Arrays.asList(response.getData());
        }
        return List.of();
    }

    /**
     * Retrieves historical flight information.
     *
     * @param date Flight date in YYYY-MM-DD format
     * @param flightNumber Optional flight number to filter by
     * @param airline Optional airline to filter by
     * @param departureIata Optional departure airport IATA code to filter by
     * @param arrivalIata Optional arrival airport IATA code to filter by
     * @param limit Optional limit for the number of results (default: 10)
     * @return List of historical flights matching the criteria
     */
    @Tool(name = "get_historical_flights",
          description = "Get historical information about past flights on a specific date. You can filter by flight number, airline, or departure/arrival airports.")
    public List<Flight> getHistoricalFlights(
            @ToolParam(name = "date", description = "Flight date in YYYY-MM-DD format (required)") String date,
            @ToolParam(name = "flight_number", description = "Flight number (e.g., 'UA123')") String flightNumber,
            @ToolParam(name = "airline", description = "Airline name or code") String airline,
            @ToolParam(name = "departure_iata", description = "Departure airport IATA code (e.g., 'LAX')") String departureIata,
            @ToolParam(name = "arrival_iata", description = "Arrival airport IATA code (e.g., 'JFK')") String arrivalIata,
            @ToolParam(name = "limit", description = "Maximum number of results to return (default: 10)") Integer limit) {

        Map<String, Object> params = new HashMap<>();
        params.put("flight_date", date);
        if (flightNumber != null) params.put("flight_number", flightNumber);
        if (airline != null) params.put("airline_name", airline);
        if (departureIata != null) params.put("dep_iata", departureIata);
        if (arrivalIata != null) params.put("arr_iata", arrivalIata);
        params.put("limit", limit != null ? limit : 10);

        log.info("Fetching historical flights with params: {}", params);
        ApiResponse<Flight[]> response = aviationStackClient.getHistoricalFlights(params).block();

        if (response != null && response.getData() != null) {
            return Arrays.asList(response.getData());
        }
        return List.of();
    }

    /**
     * Searches for airports based on search criteria.
     *
     * @param search Search term (airport name, city, IATA/ICAO code)
     * @param country Optional country to filter by
     * @param limit Optional limit for the number of results (default: 10)
     * @return List of airports matching the criteria
     */
    @Tool(name = "search_airports",
          description = "Search for airports by name, city, or IATA/ICAO code.")
    public List<Airport> searchAirports(
            @ToolParam(name = "search", description = "Search term (airport name, city, IATA/ICAO code)") String search,
            @ToolParam(name = "country", description = "Country name") String country,
            @ToolParam(name = "limit", description = "Maximum number of results to return (default: 10)") Integer limit) {

        Map<String, Object> params = new HashMap<>();
        if (search != null) params.put("search", search);
        if (country != null) params.put("country", country);
        params.put("limit", limit != null ? limit : 10);

        log.info("Searching airports with params: {}", params);
        ApiResponse<Airport[]> response = aviationStackClient.getAirports(params).block();

        if (response != null && response.getData() != null) {
            return Arrays.asList(response.getData());
        }
        return List.of();
    }

    /**
     * Searches for airlines based on search criteria.
     *
     * @param search Search term (airline name, IATA/ICAO code)
     * @param country Optional country to filter by
     * @param limit Optional limit for the number of results (default: 10)
     * @return List of airlines matching the criteria
     */
    @Tool(name = "search_airlines",
          description = "Search for airlines by name or IATA/ICAO code.")
    public List<Airline> searchAirlines(
            @ToolParam(name = "search", description = "Search term (airline name, IATA/ICAO code)") String search,
            @ToolParam(name = "country", description = "Country name") String country,
            @ToolParam(name = "limit", description = "Maximum number of results to return (default: 10)") Integer limit) {

        Map<String, Object> params = new HashMap<>();
        if (search != null) params.put("search", search);
        if (country != null) params.put("country", country);
        params.put("limit", limit != null ? limit : 10);

        log.info("Searching airlines with params: {}", params);
        ApiResponse<Airline[]> response = aviationStackClient.getAirlines(params).block();

        if (response != null && response.getData() != null) {
            return Arrays.asList(response.getData());
        }
        return List.of();
    }

    /**
     * Searches for aircraft based on search criteria.
     *
     * @param search Search term (aircraft registration, IATA/ICAO code)
     * @param limit Optional limit for the number of results (default: 10)
     * @return List of aircraft matching the criteria
     */
    @Tool(name = "search_aircraft",
          description = "Search for aircraft by registration or IATA/ICAO code.")
    public List<Aircraft> searchAircraft(
            @ToolParam(name = "search", description = "Search term (aircraft registration, IATA/ICAO code)") String search,
            @ToolParam(name = "limit", description = "Maximum number of results to return (default: 10)") Integer limit) {

        Map<String, Object> params = new HashMap<>();
        if (search != null) params.put("search", search);
        params.put("limit", limit != null ? limit : 10);

        log.info("Searching aircraft with params: {}", params);
        ApiResponse<Aircraft[]> response = aviationStackClient.getAircraft(params).block();

        if (response != null && response.getData() != null) {
            return Arrays.asList(response.getData());
        }
        return List.of();
    }

    /**
     * Searches for flight routes based on criteria.
     *
     * @param airlineIata Optional airline IATA code to filter by
     * @param flightNumber Optional flight number to filter by
     * @param departureIata Optional departure airport IATA code to filter by
     * @param arrivalIata Optional arrival airport IATA code to filter by
     * @param limit Optional limit for the number of results (default: 10)
     * @return List of routes matching the criteria
     */
    @Tool(name = "search_routes",
          description = "Search for flight routes by airline, flight number, or departure/arrival airports.")
    public List<Route> searchRoutes(
            @ToolParam(name = "airline_iata", description = "Airline IATA code (e.g., 'UA')") String airlineIata,
            @ToolParam(name = "flight_number", description = "Flight number (e.g., '123')") String flightNumber,
            @ToolParam(name = "departure_iata", description = "Departure airport IATA code (e.g., 'LAX')") String departureIata,
            @ToolParam(name = "arrival_iata", description = "Arrival airport IATA code (e.g., 'JFK')") String arrivalIata,
            @ToolParam(name = "limit", description = "Maximum number of results to return (default: 10)") Integer limit) {

        Map<String, Object> params = new HashMap<>();
        if (airlineIata != null) params.put("airline_iata", airlineIata);
        if (flightNumber != null) params.put("flight_number", flightNumber);
        if (departureIata != null) params.put("dep_iata", departureIata);
        if (arrivalIata != null) params.put("arr_iata", arrivalIata);
        params.put("limit", limit != null ? limit : 10);

        log.info("Searching routes with params: {}", params);
        ApiResponse<Route[]> response = aviationStackClient.getRoutes(params).block();

        if (response != null && response.getData() != null) {
            return Arrays.asList(response.getData());
        }
        return List.of();
    }

    /**
     * Searches for aircraft types based on search criteria.
     *
     * @param search Search term (aircraft name, IATA/ICAO code)
     * @param limit Optional limit for the number of results (default: 10)
     * @return List of aircraft types matching the criteria
     */
    @Tool(name = "search_aircraft_types",
          description = "Search for aircraft types by name or IATA/ICAO code.")
    public List<AircraftType> searchAircraftTypes(
            @ToolParam(name = "search", description = "Search term (aircraft name, IATA/ICAO code)") String search,
            @ToolParam(name = "limit", description = "Maximum number of results to return (default: 10)") Integer limit) {

        Map<String, Object> params = new HashMap<>();
        if (search != null) params.put("search", search);
        params.put("limit", limit != null ? limit : 10);

        log.info("Searching aircraft types with params: {}", params);
        ApiResponse<AircraftType[]> response = aviationStackClient.getAircraftTypes(params).block();

        if (response != null && response.getData() != null) {
            return Arrays.asList(response.getData());
        }
        return List.of();
    }
}
