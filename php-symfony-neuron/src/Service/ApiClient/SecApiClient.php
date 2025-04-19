<?php

namespace App\Service\ApiClient;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * SEC API client for fetching insider trading and institutional data
 * Uses sec-api.io
 *
 * Documentation: https://sec-api.io/docs
 */
class SecApiClient extends AbstractApiClient
{
    /**
     * {@inheritdoc}
     */
    protected function initialize(): void
    {
        $this->baseUrl = 'https://api.sec-api.io';
        // The parameter name in stock_api.yaml is sec_api.api_key
        $this->apiKey = $this->params->get('sec_api.api_key', '');

        // During development without API key, log a message
        if (empty($this->apiKey)) {
            $this->logger->warning('SEC API (sec-api.io) key not set, using mock data');
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getAuthParams(): array
    {
        // For sec-api.io, authentication is done via query parameter 'token'
        return ['token' => $this->apiKey];
    }

    /**
     * Override the default request method to use query param for API key
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $params Query parameters (excluding API key)
     * @param array $options Additional options for the HTTP client
     * @return array Response data
     * @throws \Exception If API request fails
     */
    protected function request(string $method, string $endpoint, array $params = [], array $options = []): array
    {
        $url = $this->baseUrl . $endpoint;

        // Add API key to parameters if required
        if (!empty($this->apiKey)) {
            $params = array_merge($params, $this->getAuthParams());
        }

        $requestOptions = [
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'Symfony/NeuronAI Financial Research Application'
            ],
        ];

        // Add query parameters for GET requests
        if ($method === 'GET' && !empty($params)) {
            $requestOptions['query'] = $params;
        }

        // Add JSON body for POST requests
        if ($method === 'POST' && !empty($params)) {
            // sec-api.io uses query params even for POST for the token
            $requestOptions['query'] = $this->getAuthParams();
            $requestOptions['json'] = $params; // The actual payload
        } else {
            // Ensure query params are set for GET even if $params is empty initially
             $requestOptions['query'] = $params;
        }


        // Merge with additional options
        $requestOptions = array_merge_recursive($requestOptions, $options);


        try {
            $this->logger->info("Making {$method} request to {$url}", ['params' => $params]);
            $response = $this->httpClient->request($method, $url, $requestOptions);

            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                 // Log the response body if available for debugging
                $errorBody = '';
                try {
                    $errorBody = $response->getContent(false); // Don't throw on non-2xx
                } catch (\Exception $e) {
                    // Ignore if content cannot be fetched
                }
                $this->logger->error("API returned error {$statusCode}", ['response' => $errorBody]);
                throw new \Exception("API returned error: {$statusCode}");
            }

            $data = $response->toArray();
            return $data;
        } catch (\Exception $e) {
            $this->logger->error("API request failed: {$e->getMessage()}");

            // During development with no API keys, return mock data
            if (empty($this->apiKey) || strpos($e->getMessage(), 'authentication required') !== false) {
                 $this->logger->warning("Returning mock data for {$endpoint} due to missing API key or auth error.");
                 return $this->getMockData($endpoint, $params);
            }

            throw $e;
        }
    }


    // --- Implementation of ApiClientInterface methods ---

    /**
     * {@inheritdoc}
     * SecApiClient does not support general company search.
     */
    public function searchCompanies(string $term): array
    {
        $this->logger->warning('SecApiClient does not support searchCompanies method.');
        return $this->getMockData('/searchCompanies', ['term' => $term]); // Return mock or empty
    }

    /**
     * {@inheritdoc}
     * SecApiClient does not support general company profiles.
     */
    public function getCompanyProfile(string $symbol): array
    {
        $this->logger->warning('SecApiClient does not support getCompanyProfile method.');
         return $this->getMockData('/getCompanyProfile', ['symbol' => $symbol]); // Return mock or empty
    }

    /**
     * {@inheritdoc}
     * SecApiClient does not support real-time quotes.
     */
    public function getQuote(string $symbol): array
    {
        $this->logger->warning('SecApiClient does not support getQuote method.');
        return $this->getMockData('/getQuote', ['symbol' => $symbol]); // Return mock or empty
    }

    /**
     * {@inheritdoc}
     * SecApiClient does not support general financials retrieval. Use specific methods for SEC forms.
     */
    public function getFinancials(string $symbol, string $period = 'quarterly'): array
    {
        $this->logger->warning('SecApiClient does not support getFinancials method.');
        return $this->getMockData('/getFinancials', ['symbol' => $symbol, 'period' => $period]); // Return mock or empty
    }

    /**
     * {@inheritdoc}
     * SecApiClient does not support news retrieval.
     */
    public function getCompanyNews(string $symbol, int $limit = 5): array
    {
        $this->logger->warning('SecApiClient does not support getCompanyNews method.');
        return $this->getMockData('/getCompanyNews', ['symbol' => $symbol, 'limit' => $limit]); // Return mock or empty
    }

    /**
     * {@inheritdoc}
     * SecApiClient does not support general executive retrieval. Use specific methods for SEC forms if available.
     */
    public function getExecutives(string $symbol): array
    {
        $this->logger->warning('SecApiClient does not support getExecutives method.');
        return $this->getMockData('/getExecutives', ['symbol' => $symbol]); // Return mock or empty
    }

    /**
     * {@inheritdoc}
     * SecApiClient does not support historical price retrieval.
     */
    public function getHistoricalPrices(string $symbol, string $interval = 'daily', string $outputSize = 'compact'): array
    {
        $this->logger->warning('SecApiClient does not support getHistoricalPrices method.');
        return $this->getMockData('/getHistoricalPrices', ['symbol' => $symbol, 'interval' => $interval]); // Return mock or empty
    }

    // --- Specific methods for SecApiClient ---

    /**
     * Get insider trading data (Form 4 filings)
     *
     * @param string $symbol Company ticker symbol
     * @param int $limit Maximum number of results
     * @param \DateTime|null $from Start date for search
     * @param \DateTime|null $to End date for search
     * @return array Insider trading data
     */
    public function getInsiderTrading(
        string $symbol,
        int $limit = 20,
        ?\DateTime $from = null,
        ?\DateTime $to = null
    ): array {
        // Set default date range to last 90 days if not specified
        if (!$from) {
            $from = new \DateTime('-90 days');
        }

        if (!$to) {
            $to = new \DateTime();
        }

        // Format dates for API
        $fromFormatted = $from->format('Y-m-d');
        $toFormatted = $to->format('Y-m-d');

        // Build search query for Form 4 filings
        $query = [
            'query' => [
                'query_string' => [
                    // Use ticker OR companyName for broader match potential
                    'query' => "(ticker:{$symbol} OR companyName:\"{$symbol}\") AND formType:\"4\" AND filedAt:[{$fromFormatted} TO {$toFormatted}]"
                ]
            ],
            'from' => 0,
            'size' => $limit,
            'sort' => [['filedAt' => ['order' => 'desc']]]
        ];

        // Make the API request to the SEC API search endpoint
        $response = $this->request('POST', '/filings', [], ['json' => $query]);

        // Process and normalize the data
        $insiderTrades = [];

        if (isset($response['filings']) && is_array($response['filings'])) {
            foreach ($response['filings'] as $filing) {
                // Parse the raw data from each Form 4 filing
                $parsedData = $this->parseForm4Data($filing);

                // Add to the results if successfully parsed
                if ($parsedData) {
                    $insiderTrades[] = $parsedData;
                }
            }
        }

        return $insiderTrades;
    }

     /**
     * Get institutional ownership data (13F Filings)
     *
     * @param string $symbol Company ticker symbol or CUSIP
     * @param int $limit Maximum number of results
     * @return array Institutional ownership data
     */
    public function getInstitutionalOwnership(string $symbol, int $limit = 20): array
    {
        // Build search query for Form 13F filings, searching holdings
        $query = [
            'query' => [
                'query_string' => [
                    'query' => "formType:\"13F*\" AND holdings.ticker:{$symbol}" // Search within holdings for the ticker
                ]
            ],
            'from' => 0,
            'size' => $limit, // Limits the number of 13F filings returned, not institutions
            'sort' => [['filedAt' => ['order' => 'desc']]]
        ];

        $response = $this->request('POST', '/filings', [], ['json' => $query]);

        // Process and normalize the data
        $institutionalHoldings = [];
        $institutionCiks = []; // Track CIKs to avoid duplicates from multiple filings

        if (isset($response['filings']) && is_array($response['filings'])) {
            foreach ($response['filings'] as $filing) {
                 $cik = $filing['cik'] ?? null;
                 // Skip if we already processed this institution from a more recent filing
                 if (!$cik || isset($institutionCiks[$cik])) {
                      continue;
                 }

                // Only process the holdings related to the target symbol
                if (isset($filing['holdings']) && is_array($filing['holdings'])) {
                    foreach ($filing['holdings'] as $holding) {
                        if (isset($holding['ticker']) && $holding['ticker'] === $symbol) {
                             $institutionCiks[$cik] = true; // Mark CIK as processed
                             $institutionalHoldings[] = [
                                'institutionName' => $filing['companyName'] ?? 'Unknown Institution',
                                'cik' => $cik,
                                'filingDate' => $filing['filedAt'] ?? '',
                                'reportDate' => $filing['periodOfReport'] ?? '',
                                'sharesHeld' => (int)($holding['shares'] ?? 0),
                                'valueInDollars' => (float)($holding['value'] ?? 0) * 1000, // Value is often in thousands
                                'percentOfPortfolio' => (float)($holding['percentage'] ?? 0),
                                'changeFromPrevious' => (int)(($holding['shares'] ?? 0) - ($holding['priorShares'] ?? 0)),
                            ];
                             // Since we only want one entry per institution (latest), break inner loop
                             break;
                        }
                    }
                }
                // Limit the number of unique institutions returned
                 if (count($institutionalHoldings) >= $limit) {
                      break;
                 }
            }
        }

        return $institutionalHoldings;
    }

    /**
     * Get analyst ratings data (Note: SEC API doesn't directly provide this, relies on external parsing or partners)
     *
     * @param string $symbol Company ticker symbol
     * @return array Analyst ratings data
     */
    public function getAnalystRatings(string $symbol): array
    {
        // SEC API doesn't have a dedicated endpoint for this.
        // This method might need to query a different endpoint or parse filings if available.
        // Returning mock data for now.
        $this->logger->warning("getAnalystRatings is not directly supported by sec-api.io, returning mock data.");
        return $this->getMockData('/ratings', ['symbol' => $symbol]);
    }

     /**
     * Parse Form 4 filing data into a standardized format
     *
     * @param array $filing Raw Form 4 filing data
     * @return array|null Parsed insider trading data or null if not parsable
     */
    private function parseForm4Data(array $filing): ?array
    {
        // Skip if missing essential data
        if (!isset($filing['reportingOwner']) || !isset($filing['transactions'])) {
             $this->logger->warning('Skipping Form 4 parsing due to missing reportingOwner or transactions data.', ['filingId' => $filing['id'] ?? 'N/A']);
             return null;
        }

        $owner = $filing['reportingOwner'];
        $relationship = $owner['relationship'] ?? [];

        $parsedTransactions = [];
        if (isset($filing['transactions']) && is_array($filing['transactions'])) {
            foreach ($filing['transactions'] as $tx) {
                 $parsedTransactions[] = [
                    'transactionType' => $tx['transactionCode'] ?? 'N/A', // e.g., 'P' for Purchase, 'S' for Sale
                    'securityType' => $tx['securityTitle'] ?? 'N/A',
                    'shares' => (float)($tx['transactionShares']['value'] ?? 0),
                    'pricePerShare' => (float)($tx['transactionPricePerShare']['value'] ?? 0),
                    'totalValue' => (float)($tx['transactionShares']['value'] ?? 0) * (float)($tx['transactionPricePerShare']['value'] ?? 0),
                    'ownershipType' => $tx['ownershipNature']['directOrIndirectOwnership']['value'] ?? 'N/A', // 'D' or 'I'
                    'sharesOwnedFollowing' => (float)($tx['postTransactionAmounts']['sharesOwnedFollowingTransaction']['value'] ?? 0),
                ];
            }
        }

        // If no valid transactions were parsed, maybe return null or empty transactions array
        if (empty($parsedTransactions)) {
            $this->logger->warning('No valid transactions parsed from Form 4.', ['filingId' => $filing['id'] ?? 'N/A']);
           // return null; // Decide if a filing with no transactions is valid
        }


        return [
            'filingId' => $filing['id'] ?? ($filing['accessionNo'] ?? 'N/A'),
            'filingDate' => $filing['filedAt'] ?? '',
            'issuerName' => $filing['companyName'] ?? 'Unknown', // Issuer info might be top-level
            'issuerTicker' => $filing['ticker'] ?? ($filing['tickers'][0] ?? 'N/A'), // Ticker might be top-level
            'ownerName' => $owner['reportingOwnerName'] ?? 'Unknown Insider',
            'ownerTitle' => $relationship['officerTitle'] ?? 'N/A',
            'isDirector' => $relationship['isDirector'] ?? false,
            'isOfficer' => $relationship['isOfficer'] ?? false,
            'isTenPercentOwner' => $relationship['isTenPercentOwner'] ?? false,
            'transactionDate' => $filing['periodOfReport'] ?? '', // Usually the transaction date reference
            'formType' => $filing['formType'] ?? '4',
            'formUrl' => $filing['linkToFilingDetails'] ?? '',
            'transactions' => $parsedTransactions,
        ];
    }


    /**
     * {@inheritdoc}
     */
    protected function getMockData(string $endpoint, array $params): array
    {
        $symbol = $params['symbol'] ?? ($params['ticker'] ?? 'AAPL');

        switch ($endpoint) {
            case '/filings': // Assuming this is used for insider/institutional search
                if (isset($params['json']['query']['query_string']['query'])) {
                    $queryString = $params['json']['query']['query_string']['query'];
                     if (strpos($queryString, 'formType:"4"') !== false) {
                          return ['filings' => $this->getMockInsiderFilings($symbol)];
                     }
                     if (strpos($queryString, 'formType:"13F') !== false) {
                          return ['filings' => $this->getMockInstitutionalFilings($symbol)];
                     }
                }
                return ['filings' => []];

            case '/ratings': // Mock analyst ratings
                return $this->getMockAnalystRatings($symbol);

            // Mocks for methods not implemented by this client
            case '/searchCompanies': return ['results' => []];
            case '/getCompanyProfile': return ['symbol' => $symbol, 'name' => 'Mock Company Profile (SEC)'];
            case '/getQuote': return ['symbol' => $symbol, 'price' => 0];
            case '/getFinancials': return [];
            case '/getCompanyNews': return [];
            case '/getExecutives': return [];
            case '/getHistoricalPrices': return [];

            default:
                return [];
        }
    }

    // --- Mock Data Generation ---

    private function getMockInsiderFilings(string $symbol): array
    {
        $filings = [];
        $names = ['Alice Johnson (CEO)', 'Bob Williams (CFO)', 'Charlie Davis (Director)'];
        for ($i = 0; $i < 5; $i++) {
            $date = (new \DateTime())->modify('-' . ($i * 10 + mt_rand(0, 5)) . ' days');
            $insider = $names[array_rand($names)];
            $filings[] = [
                'id' => 'mock-form4-' . $i,
                'filedAt' => $date->format('Y-m-d'),
                'companyName' => 'Mock ' . $symbol . ' Inc.',
                'ticker' => $symbol,
                'formType' => '4',
                'reportingOwner' => ['reportingOwnerName' => $insider, 'relationship' => ['isDirector' => (bool)mt_rand(0, 1), 'isOfficer' => (bool)mt_rand(0, 1)]],
                'transactions' => $this->getMockTransactions(),
                'periodOfReport' => $date->modify('-1 day')->format('Y-m-d'),
                'linkToFilingDetails' => 'https://www.sec.gov/Archives/edgar/data/mock/mock-form4.html',
            ];
        }
        return $filings;
    }

    private function getMockInstitutionalFilings(string $symbol): array
    {
        $filings = [];
        $institutions = ['Vanguard Mock Group', 'BlackRock Mock Advisors', 'State Street Mock Corp'];
         for ($i = 0; $i < 3; $i++) {
             $date = (new \DateTime())->modify('-' . ($i * 90 + mt_rand(0, 15)) . ' days');
             $institution = $institutions[array_rand($institutions)];
             $shares = mt_rand(100000, 5000000);
             $value = $shares * mt_rand(50, 200); // Mock value
             $filings[] = [
                  'id' => 'mock-13f-' . $i,
                  'filedAt' => $date->format('Y-m-d'),
                  'companyName' => $institution,
                  'cik' => '000' . mt_rand(100000, 999999),
                  'formType' => '13F-HR',
                   'periodOfReport' => $date->modify('-30 days')->format('Y-m-d'), // Approx quarter end
                  'holdings' => [
                       [
                            'ticker' => $symbol,
                            'shares' => $shares,
                            'value' => $value / 1000, // Value in thousands
                            'percentage' => mt_rand(1, 100) / 100, // 0.01 to 1.00 %
                            'priorShares' => $shares - mt_rand(-50000, 50000)
                       ]
                  ]
             ];
         }
        return $filings;
    }

    private function getMockAnalystRatings(string $symbol): array
    {
        $ratings = [];
        $firms = ['Mock Stanley', 'Goldman Mock', 'JP Mock'];
        $actions = ['Initiated', 'Upgraded', 'Downgraded', 'Reiterated'];
        $ratingsList = ['Buy', 'Hold', 'Sell', 'Overweight', 'Underweight'];

        for ($i = 0; $i < 5; $i++) {
             $date = (new \DateTime())->modify('-' . ($i * 15 + mt_rand(0, 10)) . ' days');
             $ratings[] = [
                  'firm' => $firms[array_rand($firms)],
                  'action' => $actions[array_rand($actions)],
                  'rating' => $ratingsList[array_rand($ratingsList)],
                  'priceTarget' => mt_rand(100, 300),
                  'date' => $date->format('Y-m-d'),
             ];
        }
        return [
            'symbol' => $symbol,
            'ratings' => $ratings,
            'consensus' => [ // Add mock consensus
                 'buy' => mt_rand(5,15),
                 'hold' => mt_rand(3,8),
                 'sell' => mt_rand(0,3),
                 'consensusRating' => $ratingsList[array_rand($ratingsList)],
                 'averagePriceTarget' => mt_rand(150,250),
                 'highPriceTarget' => mt_rand(250,350),
                 'lowPriceTarget' => mt_rand(100,150),
                 'upside' => mt_rand(-10,30),
            ]
       ];
    }

     /**
     * Generate mock insider transactions. Helper for getMockInsiderFilings.
     *
     * @return array Mock insider transactions
     */
    private function getMockTransactions(): array
    {
        // Generate 1-3 mock transactions
        $transactions = [];
        $transactionCount = mt_rand(1, 2); // Usually 1 or 2 transactions per filing

        $transactionCodes = ['P', 'S']; // Purchase or Sale are most common
        $securityTitles = ['Common Stock']; // Focus on common stock

        for ($i = 0; $i < $transactionCount; $i++) {
            $code = $transactionCodes[array_rand($transactionCodes)];
            $shareCount = mt_rand(100, 5000);
            $pricePerShare = mt_rand(50, 200) + (mt_rand(0, 99) / 100);

            $transactions[] = [
                'transactionCode' => $code,
                'securityTitle' => $securityTitles[array_rand($securityTitles)],
                'transactionShares' => ['value' => $shareCount],
                'transactionPricePerShare' => ['value' => $pricePerShare],
                'ownershipNature' => ['directOrIndirectOwnership' => ['value' => mt_rand(0, 1) === 0 ? 'D' : 'I']],
                'postTransactionAmounts' => ['sharesOwnedFollowingTransaction' => ['value' => mt_rand(10000, 100000)]],
            ];
        }

        return $transactions;
    }
}