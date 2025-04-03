package api

import (
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"net/url"
)

// CongressClient is a client for the Congress.gov API
type CongressClient struct {
	apiKey     string
	baseURL    string
	httpClient *http.Client
}

// NewCongressClient creates a new Congress.gov API client
func NewCongressClient(apiKey string) *CongressClient {
	return &CongressClient{
		apiKey:     apiKey,
		baseURL:    "https://api.congress.gov/v3",
		httpClient: &http.Client{},
	}
}

// SearchBills searches for bills in the Congress.gov API
func (c *CongressClient) SearchBills(query string, offset, limit int) (map[string]interface{}, error) {
	endpoint := fmt.Sprintf("%s/bill", c.baseURL)

	// Build query parameters
	params := url.Values{}
	params.Add("api_key", c.apiKey)
	if query != "" {
		params.Add("query", query)
	}
	params.Add("offset", fmt.Sprintf("%d", offset))
	params.Add("limit", fmt.Sprintf("%d", limit))

	return c.makeRequest(endpoint, params)
}

// GetBill retrieves a specific bill by congress and bill number
func (c *CongressClient) GetBill(congress, billNumber string) (map[string]interface{}, error) {
	endpoint := fmt.Sprintf("%s/bill/%s/%s", c.baseURL, congress, billNumber)

	params := url.Values{}
	params.Add("api_key", c.apiKey)

	return c.makeRequest(endpoint, params)
}

// GetBillSummary retrieves the summary of a specific bill
func (c *CongressClient) GetBillSummary(congress, billNumber string) (map[string]interface{}, error) {
	endpoint := fmt.Sprintf("%s/bill/%s/%s/summaries", c.baseURL, congress, billNumber)

	params := url.Values{}
	params.Add("api_key", c.apiKey)

	return c.makeRequest(endpoint, params)
}

// SearchMembers searches for members of Congress
func (c *CongressClient) SearchMembers(query string, offset, limit int) (map[string]interface{}, error) {
	endpoint := fmt.Sprintf("%s/member", c.baseURL)

	params := url.Values{}
	params.Add("api_key", c.apiKey)
	if query != "" {
		params.Add("query", query)
	}
	params.Add("offset", fmt.Sprintf("%d", offset))
	params.Add("limit", fmt.Sprintf("%d", limit))

	return c.makeRequest(endpoint, params)
}

// GetMember retrieves a specific member of Congress by bioguideId
func (c *CongressClient) GetMember(bioguideId string) (map[string]interface{}, error) {
	endpoint := fmt.Sprintf("%s/member/%s", c.baseURL, bioguideId)

	params := url.Values{}
	params.Add("api_key", c.apiKey)

	return c.makeRequest(endpoint, params)
}

// SearchAmendments searches for amendments
func (c *CongressClient) SearchAmendments(query string, offset, limit int) (map[string]interface{}, error) {
	endpoint := fmt.Sprintf("%s/amendment", c.baseURL)

	params := url.Values{}
	params.Add("api_key", c.apiKey)
	if query != "" {
		params.Add("query", query)
	}
	params.Add("offset", fmt.Sprintf("%d", offset))
	params.Add("limit", fmt.Sprintf("%d", limit))

	return c.makeRequest(endpoint, params)
}

// makeRequest makes an HTTP request to the Congress.gov API
func (c *CongressClient) makeRequest(endpoint string, params url.Values) (map[string]interface{}, error) {
	// Add parameters to URL
	requestURL := fmt.Sprintf("%s?%s", endpoint, params.Encode())

	// Create request
	req, err := http.NewRequest("GET", requestURL, nil)
	if err != nil {
		return nil, err
	}

	// Set headers
	req.Header.Set("Accept", "application/json")

	// Make request
	resp, err := c.httpClient.Do(req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	// Check status code
	if resp.StatusCode != http.StatusOK {
		return nil, fmt.Errorf("API request failed with status code: %d", resp.StatusCode)
	}

	// Read response body
	body, err := io.ReadAll(resp.Body)
	if err != nil {
		return nil, err
	}

	// Parse JSON
	var result map[string]interface{}
	if err := json.Unmarshal(body, &result); err != nil {
		return nil, err
	}

	return result, nil
}
