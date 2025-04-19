<?php

namespace App\Service\ApiClient;

/**
 * SEC API client for fetching insider trading and institutional data
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
        $this->apiKey = $this->params->get('sec_api.api_key', '');
        
        // During development without API key, log a message
        if (empty($this->apiKey)) {
            $this->logger->warning('SEC API key not set, using mock data');
        }
    }
    
    /**
     * {@inheritdoc}
     */
    protected function getAuthParams(): array
    {
        return [];
    }
    
    /**
     * {@inheritdoc}
     */
    protected function getHeaders(): array
    {
        return [
            'Authorization' => $this->apiKey,
            'Content-Type' => 'application/json',
        ];
    }
    
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
                    'query' => "ticker:{$symbol} AND formType:\"4\""
                ]
            ],
            'from' => 0,
            'size' => $limit,
            'sort' => [
                [
                    'filedAt' => [
                        'order' => 'desc'
                    ]
                ]
            ]
        ];
        
        if ($fromFormatted && $toFormatted) {
            $query['query']['query_string']['query'] .= " AND filedAt:[{$fromFormatted} TO {$toFormatted}]";
        }
        
        // Make the API request to the SEC API search endpoint
        $response = $this->request('POST', '/v1/filings', [], ['json' => $query]);
        
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
     * Get institutional ownership data
     *
     * @param string $symbol Company ticker symbol
     * @param int $limit Maximum number of results
     * @return array Institutional ownership data
     */
    public function getInstitutionalOwnership(string $symbol, int $limit = 20): array {
        // Build search query for Form 13F filings
        $query = [
            'query' => [
                'query_string' => [
                    'query' => "formType:\"13F\" AND holdings.cusip:{$symbol}"
                ]
            ],
            'from' => 0,
            'size' => $limit,
            'sort' => [
                [
                    'filedAt' => [
                        'order' => 'desc'
                    ]
                ]
            ]
        ];
        
        // Make the API request to the SEC API search endpoint
        $response = $this->request('POST', '/v1/filings', [], ['json' => $query]);
        
        // Process and normalize the data
        $institutionalHoldings = [];
        
        if (isset($response['filings']) && is_array($response['filings'])) {
            foreach ($response['filings'] as $filing) {
                // Only process the holdings related to the target symbol
                if (isset($filing['holdings']) && is_array($filing['holdings'])) {
                    foreach ($filing['holdings'] as $holding) {
                        if ($holding['cusip'] === $symbol || $holding['nameOfIssuer'] === $symbol) {
                            $institutionalHoldings[] = [
                                'institutionName' => $filing['companyName'],
                                'cik' => $filing['cik'],
                                'filingDate' => $filing['filedAt'],
                                'reportDate' => $filing['periodOfReport'],
                                'sharesHeld' => (int)$holding['value'],
                                'valueInDollars' => (float)($holding['value'] * $holding['sharePrice']),
                                'percentOfPortfolio' => (float)($holding['percentage'] ?? 0),
                                'changeFromPrevious' => (int)($holding['shares'] - ($holding['previouslyReported'] ?? 0)),
                            ];
                        }
                    }
                }
            }
        }
        
        return $institutionalHoldings;
    }
    
    /**
     * Get analyst ratings
     *
     * @param string $symbol Company ticker symbol
     * @return array Analyst ratings data
     */
    public function getAnalystRatings(string $symbol): array {
        // Currently there is not a direct SEC API endpoint for analyst ratings
        // This is a placeholder for mock data implementation
        return $this->getMockAnalystRatings($symbol);
    }
    
    /**
     * Parse Form 4 filing data into a standardized format
     *
     * @param array $filing Raw Form 4 filing data
     * @return array|null Parsed insider trading data or null if not parsable
     */
    private function parseForm4Data(array $filing): ?array {
        // Skip if missing essential data
        if (!isset($filing['documentFormatFiles']) || 
            !isset($filing['issuer']) ||
            !isset($filing['reportingOwner'])) {
            return null;
        }
        
        // Get the XML document URL from the filing
        $xmlUrl = null;
        foreach ($filing['documentFormatFiles'] as $document) {
            if ($document['type'] === 'xml') {
                $xmlUrl = $document['documentUrl'];
                break;
            }
        }
        
        // If no XML document, we can't parse transaction details
        if (!$xmlUrl) {
            return null;
        }
        
        // In a real implementation, we would fetch and parse the XML
        // For now, extract what we can from the general filing data
        
        return [
            'filingId' => $filing['id'] ?? '',
            'filingDate' => $filing['filedAt'] ?? '',
            'issuerName' => $filing['issuer']['companyName'] ?? '',
            'issuerTicker' => $filing['issuer']['ticker'] ?? '',
            'ownerName' => $filing['reportingOwner']['name'] ?? '',
            'ownerTitle' => $filing['reportingOwner']['relationship']['officerTitle'] ?? '',
            'isDirector' => $filing['reportingOwner']['relationship']['isDirector'] ?? false,
            'isOfficer' => $filing['reportingOwner']['relationship']['isOfficer'] ?? false,
            'isTenPercentOwner' => $filing['reportingOwner']['relationship']['isTenPercentOwner'] ?? false,
            'transactionDate' => $filing['periodOfReport'] ?? '',
            'formType' => $filing['formType'] ?? '',
            'formUrl' => $filing['linkToFilingDetails'] ?? '',
            'transactions' => $this->getMockTransactions(), // In a real impl we would parse the XML
        ];
    }
    
    /**
     * Generate mock insider trading data
     *
     * @return array Mock insider transactions
     */
    private function getMockTransactions(): array {
        // Generate 1-3 mock transactions
        $transactions = [];
        $transactionCount = mt_rand(1, 3);
        
        $transactionTypes = ['P', 'S', 'A', 'D', 'G'];
        $securityTypes = ['Common Stock', 'Option', 'Restricted Stock Unit'];
        
        for ($i = 0; $i < $transactionCount; $i++) {
            $type = $transactionTypes[array_rand($transactionTypes)];
            $shareCount = mt_rand(1000, 100000);
            $pricePerShare = mt_rand(10, 500) + (mt_rand(0, 99) / 100);
            
            $transactions[] = [
                'transactionType' => $type,
                'securityType' => $securityTypes[array_rand($securityTypes)],
                'shares' => $shareCount,
                'pricePerShare' => $pricePerShare,
                'totalValue' => $shareCount * $pricePerShare,
                'ownershipType' => mt_rand(0, 1) === 0 ? 'Direct' : 'Indirect',
                'sharesOwnedFollowing' => mt_rand(100000, 5000000),
            ];
        }
        
        return $transactions;
    }
    
    /**
     * {@inheritdoc}
     */
    protected function getMockData(string $endpoint, array $params): array {
        // Handle different endpoints
        if ($endpoint === '/v1/filings') {
            // Determine which mock data to return based on the query
            $body = $params['json'] ?? [];
            $query = $body['query']['query_string']['query'] ?? '';
            
            if (strpos($query, 'formType:"4"') !== false) {
                // For Form 4 (insider trading)
                preg_match('/ticker:([A-Z]+)/', $query, $matches);
                $symbol = $matches[1] ?? 'AAPL';
                return $this->getMockInsiderTrading($symbol);
            } else if (strpos($query, 'formType:"13F"') !== false) {
                // For Form 13F (institutional ownership)
                preg_match('/holdings.cusip:([A-Z]+)/', $query, $matches);
                $symbol = $matches[1] ?? 'AAPL';
                return $this->getMockInstitutionalOwnership($symbol);
            }
        }
        
        // Default mock data
        return ['filings' => []];
    }
    
    /**
     * Generate mock insider trading data
     *
     * @param string $symbol Company ticker symbol
     * @return array Mock insider trading data
     */
    private function getMockInsiderTrading(string $symbol): array {
        // Mock company names based on symbol
        $companyNames = [
            'AAPL' => 'Apple Inc.',
            'MSFT' => 'Microsoft Corporation',
            'AVGO' => 'Broadcom Inc.',
            'AMZN' => 'Amazon.com, Inc.',
            'GOOGL' => 'Alphabet Inc.',
        ];
        
        $companyName = $companyNames[$symbol] ?? 'Example Corporation';
        
        // Mock data for CEO, CFO, and CTO
        $insiders = [
            [
                'name' => 'John Smith',
                'title' => 'Chief Executive Officer',
                'isDirector' => true,
                'isOfficer' => true
            ],
            [
                'name' => 'Sarah Johnson',
                'title' => 'Chief Financial Officer',
                'isDirector' => false,
                'isOfficer' => true
            ],
            [
                'name' => 'Robert Chen',
                'title' => 'Chief Technology Officer',
                'isDirector' => false,
                'isOfficer' => true
            ],
            [
                'name' => 'Jane Williams',
                'title' => 'Director',
                'isDirector' => true,
                'isOfficer' => false
            ],
        ];
        
        // Generate filings for each insider
        $filings = [];
        $dateBase = new \DateTime();
        
        foreach ($insiders as $index => $insider) {
            // Create 1-3 filings per insider, each a few days apart
            $filingCount = mt_rand(1, 3);
            
            for ($i = 0; $i < $filingCount; $i++) {
                $date = clone $dateBase;
                $date->modify('-' . ($index * 7 + $i * 3) . ' days');
                
                $filings[] = [
                    'id' => 'mock-' . mt_rand(1000000, 9999999),
                    'filedAt' => $date->format('Y-m-d'),
                    'formType' => '4',
                    'issuer' => [
                        'companyName' => $companyName,
                        'ticker' => $symbol,
                        'cik' => '000' . mt_rand(100000, 999999)
                    ],
                    'reportingOwner' => [
                        'name' => $insider['name'],
                        'relationship' => [
                            'officerTitle' => $insider['title'],
                            'isDirector' => $insider['isDirector'],
                            'isOfficer' => $insider['isOfficer'],
                            'isTenPercentOwner' => false
                        ]
                    ],
                    'periodOfReport' => $date->format('Y-m-d'),
                    'linkToFilingDetails' => 'https://www.sec.gov/example/mock-filing.html',
                    'documentFormatFiles' => [
                        [
                            'type' => 'xml',
                            'documentUrl' => 'https://www.sec.gov/example/mock-filing.xml'
                        ]
                    ]
                ];
            }
        }
        
        return ['filings' => $filings];
    }
    
    /**
     * Generate mock institutional ownership data
     *
     * @param string $symbol Company ticker symbol
     * @return array Mock institutional ownership data
     */
    private function getMockInstitutionalOwnership(string $symbol): array {
        // Mock institutional investors
        $institutions = [
            [
                'name' => 'Vanguard Group, Inc.',
                'cik' => '000102909',
                'holdings' => mt_rand(5000000, 20000000)
            ],
            [
                'name' => 'BlackRock, Inc.',
                'cik' => '000105799',
                'holdings' => mt_rand(4000000, 18000000)
            ],
            [
                'name' => 'State Street Corporation',
                'cik' => '000093751',
                'holdings' => mt_rand(3000000, 15000000)
            ],
            [
                'name' => 'Fidelity Management & Research',
                'cik' => '000315252',
                'holdings' => mt_rand(2500000, 12000000)
            ],
            [
                'name' => 'T. Rowe Price Associates',
                'cik' => '000080255',
                'holdings' => mt_rand(2000000, 10000000)
            ],
        ];
        
        // Generate 13F filings
        $filings = [];
        $dateBase = new \DateTime();
        $sharePrice = mt_rand(50, 500) + (mt_rand(0, 99) / 100);
        
        foreach ($institutions as $index => $institution) {
            $date = clone $dateBase;
            $date->modify('-' . $index * 15 . ' days');
            
            $previousHoldings = $institution['holdings'] - mt_rand(-500000, 500000);
            
            $filings[] = [
                'id' => 'mock-13f-' . mt_rand(1000000, 9999999),
                'filedAt' => $date->format('Y-m-d'),
                'formType' => '13F',
                'companyName' => $institution['name'],
                'cik' => $institution['cik'],
                'periodOfReport' => $date->format('Y-m-d'),
                'holdings' => [
                    [
                        'cusip' => $symbol,
                        'nameOfIssuer' => $symbol,
                        'value' => $institution['holdings'],
                        'shares' => $institution['holdings'],
                        'previouslyReported' => $previousHoldings,
                        'sharePrice' => $sharePrice,
                        'percentage' => mt_rand(1, 8) / 100
                    ]
                ]
            ];
        }
        
        return ['filings' => $filings];
    }
    
    /**
     * Generate mock analyst ratings
     *
     * @param string $symbol Company ticker symbol
     * @return array Mock analyst ratings
     */
    private function getMockAnalystRatings(string $symbol): array {
        // Mock financial firms
        $firms = [
            'Morgan Stanley',
            'Goldman Sachs',
            'JP Morgan',
            'Citigroup',
            'Bank of America',
            'Wells Fargo',
            'UBS Group',
            'Deutsche Bank',
            'Barclays',
            'Credit Suisse'
        ];
        
        // Mock ratings types
        $ratings = [
            'Buy',
            'Overweight',
            'Neutral',
            'Hold',
            'Underweight',
            'Sell'
        ];
        
        // Generate base price and calculate targets with some variation
        $currentPrice = mt_rand(50, 500);
        
        // Generate ratings
        $analystRatings = [];
        $now = new \DateTime();
        
        // Aggregate data
        $ratingCounts = [
            'Buy' => 0,
            'Overweight' => 0,
            'Neutral' => 0,
            'Hold' => 0,
            'Underweight' => 0,
            'Sell' => 0
        ];
        
        $totalPriceTarget = 0;
        $ratingCount = mt_rand(5, 10);
        
        for ($i = 0; $i < $ratingCount; $i++) {
            $firm = $firms[array_rand($firms)];
            $rating = $ratings[array_rand($ratings)];
            
            // More likely to be positive than negative
            if (mt_rand(1, 10) < 7) {
                $rating = 'Buy'; // Or Overweight
            }
            
            // Price target usually above current price
            $priceDeviation = mt_rand(-20, 40);
            $priceTarget = $currentPrice * (1 + ($priceDeviation / 100));
            
            // Date in last 90 days
            $date = clone $now;
            $date->modify('-' . mt_rand(1, 90) . ' days');
            
            $analystRating = [
                'firm' => $firm,
                'analyst' => $this->generateAnalystName(),
                'rating' => $rating,
                'priceTarget' => round($priceTarget, 2),
                'previousRating' => $rating,
                'previousPriceTarget' => round($priceTarget * (1 + (mt_rand(-10, 10) / 100)), 2),
                'date' => $date->format('Y-m-d'),
            ];
            
            $analystRatings[] = $analystRating;
            
            // Track for consensus
            $ratingCounts[$rating]++;
            $totalPriceTarget += $priceTarget;
        }
        
        // Calculate consensus
        $consensus = [
            'buy' => $ratingCounts['Buy'] + $ratingCounts['Overweight'],
            'hold' => $ratingCounts['Neutral'] + $ratingCounts['Hold'],
            'sell' => $ratingCounts['Underweight'] + $ratingCounts['Sell'],
            'consensusRating' => 'Hold', // Default
            'averagePriceTarget' => round($totalPriceTarget / $ratingCount, 2),
            'highPriceTarget' => round($currentPrice * (1 + (40 / 100)), 2),
            'lowPriceTarget' => round($currentPrice * (1 - (20 / 100)), 2),
            'upside' => round(($totalPriceTarget / $ratingCount / $currentPrice - 1) * 100, 2),
        ];
        
        // Determine consensus rating
        $buyRatio = $consensus['buy'] / $ratingCount;
        $holdRatio = $consensus['hold'] / $ratingCount;
        $sellRatio = $consensus['sell'] / $ratingCount;
        
        if ($buyRatio > 0.7) {
            $consensus['consensusRating'] = 'Strong Buy';
        } elseif ($buyRatio > 0.5) {
            $consensus['consensusRating'] = 'Buy';
        } elseif ($holdRatio > 0.7) {
            $consensus['consensusRating'] = 'Hold';
        } elseif ($sellRatio > 0.5) {
            $consensus['consensusRating'] = 'Sell';
        } elseif ($sellRatio > 0.7) {
            $consensus['consensusRating'] = 'Strong Sell';
        }
        
        return [
            'symbol' => $symbol,
            'currentPrice' => $currentPrice,
            'consensus' => $consensus,
            'ratings' => $analystRatings
        ];
    }
    
    /**
     * Generate a random analyst name
     *
     * @return string Analyst name
     */
    private function generateAnalystName(): string {
        $firstNames = ['John', 'Michael', 'David', 'Robert', 'James', 'William', 'Richard', 'Thomas', 'Mary', 'Sarah', 'Jennifer', 'Elizabeth', 'Linda', 'Patricia', 'Susan'];
        $lastNames = ['Smith', 'Johnson', 'Williams', 'Jones', 'Brown', 'Miller', 'Davis', 'Garcia', 'Rodriguez', 'Wilson', 'Martinez', 'Anderson', 'Taylor', 'Thomas', 'Moore'];
        
        return $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
    }
}
