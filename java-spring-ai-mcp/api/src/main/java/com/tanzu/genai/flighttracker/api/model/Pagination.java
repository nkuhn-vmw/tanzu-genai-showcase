package com.tanzu.genai.flighttracker.api.model;

import lombok.Data;

/**
 * Pagination information for API responses.
 */
@Data
public class Pagination {
    private int limit;
    private int offset;
    private int count;
    private int total;
}
