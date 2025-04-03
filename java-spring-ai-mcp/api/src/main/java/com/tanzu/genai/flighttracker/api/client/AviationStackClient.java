package com.tanzu.genai.flighttracker.api.client;

import com.tanzu.genai.flighttracker.api.config.AviationStackProperties;
import com.tanzu.genai.flighttracker.api.model.*;
import lombok.RequiredArgsConstructor;
import org.springframework.stereotype.Component;
import org.springframework.web.reactive.function.client.WebClient;
import reactor.core.publisher.Mono;

import java.util.HashMap;
import java.util.Map;

/**
 * Client for interacting with the AviationStack API.
 */
@Component
@RequiredArgsConstructor
public class AviationStackClient {

    private final WebClient webClient;
    private final AviationStackProperties properties;

    /**
     * Retrieves real-time flight information.
     *
     * @param params Map of query parameters to filter results
     * @return API response containing flight data
     */
    public Mono<ApiResponse<Flight[]>> getRealTimeFlights(Map<String, Object> params) {
        return executeRequest("/flights", params, Flight[].class);
    }

    /**
     * Retrieves historical flight information.
     *
     * @param params Map of query parameters to filter results
     * @return API response containing historical flight data
     */
    public Mono<ApiResponse<Flight[]>> getHistoricalFlights(Map<String, Object> params) {
        if (!params.containsKey("flight_date")) {
            throw new IllegalArgumentException("flight_date parameter is required for historical flights");
        }
        return executeRequest("/flights", params, Flight[].class);
    }

    /**
     * Retrieves airport information.
     *
     * @param params Map of query parameters to filter results
     * @return API response containing airport data
     */
    public Mono<ApiResponse<Airport[]>> getAirports(Map<String, Object> params) {
        return executeRequest("/airports", params, Airport[].class);
    }

    /**
     * Retrieves airline information.
     *
     * @param params Map of query parameters to filter results
     * @return API response containing airline data
     */
    public Mono<ApiResponse<Airline[]>> getAirlines(Map<String, Object> params) {
        return executeRequest("/airlines", params, Airline[].class);
    }

    /**
     * Retrieves aircraft information.
     *
     * @param params Map of query parameters to filter results
     * @return API response containing aircraft data
     */
    public Mono<ApiResponse<Aircraft[]>> getAircraft(Map<String, Object> params) {
        return executeRequest("/airplanes", params, Aircraft[].class);
    }

    /**
     * Retrieves route information.
     *
     * @param params Map of query parameters to filter results
     * @return API response containing route data
     */
    public Mono<ApiResponse<Route[]>> getRoutes(Map<String, Object> params) {
        return executeRequest("/routes", params, Route[].class);
    }

    /**
     * Retrieves aircraft type information.
     *
     * @param params Map of query parameters to filter results
     * @return API response containing aircraft type data
     */
    public Mono<ApiResponse<AircraftType[]>> getAircraftTypes(Map<String, Object> params) {
        return executeRequest("/aircraft_types", params, AircraftType[].class);
    }

    /**
     * Executes a request to the AviationStack API.
     *
     * @param endpoint API endpoint
     * @param params Query parameters
     * @param responseType Class of the response data
     * @return API response
     */
    private <T> Mono<ApiResponse<T>> executeRequest(String endpoint, Map<String, Object> params, Class<T> responseType) {
        Map<String, Object> queryParams = new HashMap<>(params);
        queryParams.put("access_key", properties.getAccessKey());

        return webClient.get()
            .uri(uriBuilder -> {
                uriBuilder.path(endpoint);
                queryParams.forEach((key, value) -> uriBuilder.queryParam(key, value));
                return uriBuilder.build();
            })
            .retrieve()
            .bodyToMono(ApiResponse.class)
            .cast(ApiResponse.class);
    }
}
