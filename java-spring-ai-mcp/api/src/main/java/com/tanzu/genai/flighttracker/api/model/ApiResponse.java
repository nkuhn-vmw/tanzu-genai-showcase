package com.tanzu.genai.flighttracker.api.model;

import lombok.Data;

/**
 * Base response class for AviationStack API responses.
 */
@Data
public class ApiResponse<T> {
    private Pagination pagination;
    private T data;
}
