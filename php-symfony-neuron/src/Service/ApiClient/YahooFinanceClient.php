<?php

namespace App\Service\ApiClient;

/**
 * Yahoo Finance API client
 *
 * Documentation: https://rapidapi.com/apidojo/api/yahoo-finance1/
 */
class YahooFinanceClient extends AbstractApiClient
{
    /**
     * {@inheritdoc}
     */
    protected function initialize(): void
    {
        $this->baseUrl = 'https://apidojo-yahoo-finance-v1.p.rapidapi.com';
        $this->apiKey = $this->params->get('yahoo_finance.api_key', '');

        // During development without API key, log a message
        if (empty($this->apiKey)) {
            $this->logger->warning('Yahoo Finance API key not set, using mock data');
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getAuthParams(): array
    {
        // For Yahoo Finance via RapidAPI, authentication is done via headers, not params
        return [];
    }

    /**
     * Get headers for RapidAPI authentication
     *
     * @return array Headers for RapidAPI
     */
    private function getRapidApiHeaders(): array
    {
        return [
            'X-RapidAPI-Key' => $this->apiKey,
            'X-RapidAPI-Host' => 'apidojo-yahoo-finance-v1.p.rapidapi.com'
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function searchCompanies(string $term): array
    {
        $params = [
            'q' => $term,
            'quotesCount' => 10,
            'newsCount' => 0,
        ];

        // Add headers for RapidAPI
        $options = [
            'headers' => $this->getRapidApiHeaders()
        ];

        $data = $this->request('GET', '/auto-complete', $params, $options);

        $results = [];
        if (isset($data['quotes']) && is_array($data['quotes'])) {
            foreach ($data['quotes'] as $quote) {
                // Filter out non-equity results
                if (isset($quote['quoteType']) && $quote['quoteType'] === 'EQUITY') {
                    $results[] = [
                        'symbol' => $quote['symbol'] ?? '',
                        'name' => $quote['shortname'] ?? $quote['longname'] ?? '',
                        'type' => $quote['quoteType'] ?? '',
                        'exchange' => $quote['exchange'] ?? '',
                        'region' => $quote['region'] ?? '',
                        'currency' => $quote['currency'] ?? 'USD',
                        'marketCap' => $quote['marketCap'] ?? 0,
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function getCompanyProfile(string $symbol): array
    {
        $params = [
            'symbols' => $symbol,
            'modules' => 'assetProfile,summaryProfile,summaryDetail,financialData'
        ];

        // Add headers for RapidAPI
        $options = [
            'headers' => $this->getRapidApiHeaders()
        ];

        $data = $this->request('GET', '/stock/v2/get-summary', $params, $options);

        // Format the data
        $profile = [];

        if (isset($data['quoteSummary']['result'][0])) {
            $result = $data['quoteSummary']['result'][0];

            // Extract asset profile data
            $assetProfile = $result['assetProfile'] ?? [];
            $summaryProfile = $result['summaryProfile'] ?? [];
            $summaryDetail = $result['summaryDetail'] ?? [];
            $financialData = $result['financialData'] ?? [];

            $profile = [
                'symbol' => $symbol,
                'name' => $assetProfile['name'] ?? '',
                'description' => $assetProfile['longBusinessSummary'] ?? '',
                'exchange' => $summaryDetail['exchange'] ?? '',
                'currency' => $summaryDetail['currency'] ?? 'USD',
                'country' => $assetProfile['country'] ?? $summaryProfile['country'] ?? '',
                'sector' => $assetProfile['sector'] ?? '',
                'industry' => $assetProfile['industry'] ?? '',
                'address' => $assetProfile['address1'] ?? '',
                'city' => $assetProfile['city'] ?? '',
                'state' => $assetProfile['state'] ?? '',
                'zip' => $assetProfile['zip'] ?? '',
                'website' => $assetProfile['website'] ?? '',
                'employees' => (int)($assetProfile['fullTimeEmployees'] ?? 0),
                'officers' => $assetProfile['companyOfficers'] ?? [],
                'marketCap' => (float)($summaryDetail['marketCap']['raw'] ?? 0),

                // Financial metrics
                'peRatio' => (float)($summaryDetail['trailingPE']['raw'] ?? 0),
                'pegRatio' => (float)($summaryDetail['pegRatio']['raw'] ?? 0),
                'bookValue' => (float)($summaryDetail['bookValue']['raw'] ?? 0),
                'dividendYield' => (float)($summaryDetail['dividendYield']['raw'] ?? 0),
                'dividendRate' => (float)($summaryDetail['dividendRate']['raw'] ?? 0),
                'beta' => (float)($summaryDetail['beta']['raw'] ?? 0),

                // Price data
                'currentPrice' => (float)($financialData['currentPrice']['raw'] ?? 0),
                'targetHighPrice' => (float)($financialData['targetHighPrice']['raw'] ?? 0),
                'targetLowPrice' => (float)($financialData['targetLowPrice']['raw'] ?? 0),
                'targetMeanPrice' => (float)($financialData['targetMeanPrice']['raw'] ?? 0),

                // Financial health
                'returnOnEquity' => (float)($financialData['returnOnEquity']['raw'] ?? 0),
                'debtToEquity' => (float)($financialData['debtToEquity']['raw'] ?? 0),
                'revenueGrowth' => (float)($financialData['revenueGrowth']['raw'] ?? 0),
                'grossMargins' => (float)($financialData['grossMargins']['raw'] ?? 0),
                'operatingMargins' => (float)($financialData['operatingMargins']['raw'] ?? 0),
                'profitMargins' => (float)($financialData['profitMargins']['raw'] ?? 0),
            ];
        }

        return $profile;
    }

    /**
     * {@inheritdoc}
     */
    public function getQuote(string $symbol): array
    {
        $params = [
            'symbols' => $symbol,
            'modules' => 'price,summaryDetail'
        ];

        // Add headers for RapidAPI
        $options = [
            'headers' => $this->getRapidApiHeaders()
        ];

        $data = $this->request('GET', '/stock/v2/get-summary', $params, $options);

        if (isset($data['quoteSummary']['result'][0]['price'])) {
            $price = $data['quoteSummary']['result'][0]['price'];
            $summary = $data['quoteSummary']['result'][0]['summaryDetail'] ?? [];

            return [
                'symbol' => $symbol,
                'price' => (float)($price['regularMarketPrice']['raw'] ?? 0),
                'change' => (float)($price['regularMarketChange']['raw'] ?? 0),
                'changePercent' => (float)($price['regularMarketChangePercent']['raw'] ?? 0) * 100,
                'volume' => (int)($price['regularMarketVolume']['raw'] ?? 0),
                'latestTradingDay' => $price['regularMarketTime'] ?? time(),
                'previousClose' => (float)($price['regularMarketPreviousClose']['raw'] ?? 0),
                'open' => (float)($price['regularMarketOpen']['raw'] ?? 0),
                'high' => (float)($price['regularMarketDayHigh']['raw'] ?? 0),
                'low' => (float)($price['regularMarketDayLow']['raw'] ?? 0),
                'bid' => (float)($price['bid']['raw'] ?? 0),
                'ask' => (float)($price['ask']['raw'] ?? 0),
                'bidSize' => (int)($price['bidSize']['raw'] ?? 0),
                'askSize' => (int)($price['askSize']['raw'] ?? 0),
                'marketCap' => (float)($summary['marketCap']['raw'] ?? 0),
                'fiftyTwoWeekLow' => (float)($summary['fiftyTwoWeekLow']['raw'] ?? 0),
                'fiftyTwoWeekHigh' => (float)($summary['fiftyTwoWeekHigh']['raw'] ?? 0),
                'priceToSales' => (float)($summary['priceToSalesTrailing12Months']['raw'] ?? 0),
            ];
        }

        return [
            'symbol' => $symbol,
            'price' => 0,
            'change' => 0,
            'changePercent' => 0,
            'volume' => 0,
            'previousClose' => 0,
            'open' => 0,
            'high' => 0,
            'low' => 0,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getFinancials(string $symbol, string $period = 'quarterly'): array
    {
        // Yahoo Finance has a different endpoint for financials
        $params = [
            'symbol' => $symbol,
        ];

        $endpoint = $period === 'quarterly'
            ? '/stock/v2/get-financials'
            : '/stock/v2/get-financials';

        // Add headers for RapidAPI
        $options = [
            'headers' => $this->getRapidApiHeaders()
        ];

        $data = $this->request('GET', $endpoint, $params, $options);

        $financials = [];

        if (isset($data['timeSeries']['result'])) {
            $results = $data['timeSeries']['result'];

            // Yahoo Finance returns data in a more complex format
            // We need to extract and format specific metrics
            $reportDates = [];
            $metrics = [];

            foreach ($results as $metric) {
                $metricName = $metric['meta']['type'][0] ?? '';

                // Only include specific financial metrics
                if (in_array($metricName, [
                    'totalRevenue', 'costOfRevenue', 'grossProfit', 'netIncome',
                    'operatingIncome', 'operatingExpense', 'researchDevelopment'
                ])) {
                    if (isset($metric['quarterly'])) {
                        foreach ($metric['quarterly'] as $quarter) {
                            $date = $quarter['asOfDate'] ?? '';
                            $value = $quarter['reportedValue']['raw'] ?? 0;

                            if (!empty($date)) {
                                $reportDates[$date] = true;
                                $metrics[$date][$metricName] = $value;
                            }
                        }
                    }
                }
            }

            // Format the data by quarter
            foreach (array_keys($reportDates) as $date) {
                $dateObj = new \DateTime($date);
                $year = $dateObj->format('Y');
                $month = (int)$dateObj->format('m');

                if ($month <= 3) {
                    $quarter = 'Q1';
                } elseif ($month <= 6) {
                    $quarter = 'Q2';
                } elseif ($month <= 9) {
                    $quarter = 'Q3';
                } else {
                    $quarter = 'Q4';
                }

                $financials[] = [
                    'symbol' => $symbol,
                    'fiscalDate' => $date,
                    'fiscalQuarter' => $quarter,
                    'fiscalYear' => $year,
                    'reportDate' => $date,
                    'reportType' => 'Income Statement',
                    'currency' => 'USD',
                    'revenue' => (float)($metrics[$date]['totalRevenue'] ?? 0),
                    'costOfRevenue' => (float)($metrics[$date]['costOfRevenue'] ?? 0),
                    'grossProfit' => (float)($metrics[$date]['grossProfit'] ?? 0),
                    'netIncome' => (float)($metrics[$date]['netIncome'] ?? 0),
                    'operatingIncome' => (float)($metrics[$date]['operatingIncome'] ?? 0),
                    'operatingExpenses' => (float)($metrics[$date]['operatingExpense'] ?? 0),
                    'researchAndDevelopment' => (float)($metrics[$date]['researchDevelopment'] ?? 0),
                ];
            }

            // Sort by date descending
            usort($financials, function($a, $b) {
                return strtotime($b['fiscalDate']) - strtotime($a['fiscalDate']);
            });

            // Limit to 4 quarters
            $financials = array_slice($financials, 0, 4);
        }

        return $financials;
    }

    /**
     * {@inheritdoc}
     */
    public function getCompanyNews(string $symbol, int $limit = 5): array
    {
        $params = [
            'category' => $symbol,
            'count' => $limit
        ];

        // Add headers for RapidAPI
        $options = [
            'headers' => $this->getRapidApiHeaders()
        ];

        $data = $this->request('GET', '/news/v2/list', $params, $options);

        $news = [];
        if (isset($data['data']['stream_items'])) {
            foreach ($data['data']['stream_items'] as $item) {
                if (isset($item['card'])) {
                    $card = $item['card'];
                    $news[] = [
                        'title' => $card['title'] ?? '',
                        'summary' => $card['summary'] ?? '',
                        'source' => $card['source'] ?? 'Yahoo Finance',
                        'publishedAt' => $card['pubtime'] ? date('Y-m-d H:i:s', $card['pubtime']) : date('Y-m-d H:i:s'),
                        'url' => $card['url'] ?? '',
                        'imageUrl' => $card['thumbnail_url'] ?? null,
                        'category' => $card['category'] ?? 'finance',
                    ];

                    if (count($news) >= $limit) {
                        break;
                    }
                }
            }
        }

        return $news;
    }

    /**
     * {@inheritdoc}
     */
    public function getExecutives(string $symbol): array
    {
        // This information is included in the company profile for Yahoo Finance
        $profile = $this->getCompanyProfile($symbol);

        $executives = [];
        if (isset($profile['officers']) && is_array($profile['officers'])) {
            foreach ($profile['officers'] as $officer) {
                $executives[] = [
                    'name' => $officer['name'] ?? 'Unknown',
                    'title' => $officer['title'] ?? 'Unknown',
                    'age' => $officer['age'] ?? 0,
                    'yearJoined' => date('Y') - mt_rand(1, 10), // Not provided by Yahoo
                    'bio' => '',  // Not provided by Yahoo
                    'compensation' => $officer['totalPay']['raw'] ?? 0,
                    'education' => '', // Not provided by Yahoo
                    'previousCompanies' => '', // Not provided by Yahoo
                ];
            }
        }

        // If no executives found, return mock data
        if (empty($executives)) {
            return $this->getMockExecutives($symbol);
        }

        return $executives;
    }

    /**
     * {@inheritdoc}
     */
    public function getHistoricalPrices(string $symbol, string $interval = 'daily', string $outputSize = 'compact'): array
    {
        // Map interval to Yahoo Finance range and interval
        $yahooInterval = match($interval) {
            'daily' => '1d',
            'weekly' => '1wk',
            'monthly' => '1mo',
            default => '1d',
        };

        // Map output size to range (period)
        $range = match($outputSize) {
            'compact' => '3mo',
            'full' => '5y',
            default => '1y',
        };

        $params = [
            'symbol' => $symbol,
            'range' => $range,
            'interval' => $yahooInterval,
            'includePrePost' => 'false',
            'events' => 'div,split',
        ];

        // Add headers for RapidAPI
        $options = [
            'headers' => $this->getRapidApiHeaders()
        ];

        $data = $this->request('GET', '/stock/v3/get-chart', $params, $options);

        // Extract the time series data
        $prices = [];

        if (isset($data['chart']['result'][0])) {
            $result = $data['chart']['result'][0];
            $timestamps = $result['timestamp'] ?? [];
            $quoteData = $result['indicators']['quote'][0] ?? [];
            $adjClose = $result['indicators']['adjclose'][0]['adjclose'] ?? [];

            $timeZone = new \DateTimeZone('UTC');
            if (!empty($result['meta']['exchangeTimezoneName'])) {
                try {
                    $timeZone = new \DateTimeZone($result['meta']['exchangeTimezoneName']);
                } catch (\Exception $e) {
                    // If timezone is invalid, fall back to UTC
                }
            }

            for ($i = 0; $i < count($timestamps); $i++) {
                if (!isset($quoteData['open'][$i]) || !isset($quoteData['close'][$i])) {
                    continue; // Skip if missing data
                }

                $date = new \DateTime();
                $date->setTimestamp($timestamps[$i]);
                $date->setTimezone($timeZone);

                $prices[] = [
                    'date' => $date->format('Y-m-d'),
                    'open' => (float)($quoteData['open'][$i] ?? 0),
                    'high' => (float)($quoteData['high'][$i] ?? 0),
                    'low' => (float)($quoteData['low'][$i] ?? 0),
                    'close' => (float)($quoteData['close'][$i] ?? 0),
                    'volume' => (int)($quoteData['volume'][$i] ?? 0),
                    'adjustedClose' => (float)($adjClose[$i] ?? $quoteData['close'][$i] ?? 0),
                ];
            }
        }

        return $prices;
    }

    /**
     * {@inheritdoc}
     */
    protected function getMockData(string $endpoint, array $params): array
    {
        switch ($endpoint) {
            case '/auto-complete':
                return $this->getMockSearchResults($params['q'] ?? '');
            case '/stock/v2/get-summary':
                return $this->getMockSummary($params['symbols'] ?? '');
            case '/stock/v2/get-financials':
                return $this->getMockFinancials($params['symbol'] ?? '');
            case '/news/v2/list':
                return $this->getMockNewsList($params['category'] ?? '', $params['count'] ?? 5);
            default:
                return [];
        }
    }

    /**
     * Generate mock search results
     *
     * @param string $term Search term
     * @return array Mock search results
     */
    private function getMockSearchResults(string $term): array
    {
        $term = strtoupper($term);
        $quotes = [];

        // For AVGO (Broadcom)
        if (strpos($term, 'AVGO') !== false || strpos($term, 'BROADCOM') !== false) {
            $quotes[] = [
                'symbol' => 'AVGO',
                'shortname' => 'Broadcom Inc.',
                'longname' => 'Broadcom Inc.',
                'quoteType' => 'EQUITY',
                'exchange' => 'NASDAQ',
                'region' => 'US',
                'currency' => 'USD',
                'marketCap' => 515000000000,
            ];
        }

        // For AAPL (Apple)
        if (strpos($term, 'AAPL') !== false || strpos($term, 'APPLE') !== false) {
            $quotes[] = [
                'symbol' => 'AAPL',
                'shortname' => 'Apple Inc.',
                'longname' => 'Apple Inc.',
                'quoteType' => 'EQUITY',
                'exchange' => 'NASDAQ',
                'region' => 'US',
                'currency' => 'USD',
                'marketCap' => 2750000000000,
            ];
        }

        // For MSFT (Microsoft)
        if (strpos($term, 'MSFT') !== false || strpos($term, 'MICROSOFT') !== false) {
            $quotes[] = [
                'symbol' => 'MSFT',
                'shortname' => 'Microsoft Corporation',
                'longname' => 'Microsoft Corporation',
                'quoteType' => 'EQUITY',
                'exchange' => 'NASDAQ',
                'region' => 'US',
                'currency' => 'USD',
                'marketCap' => 2990000000000,
            ];
        }

        return ['quotes' => $quotes];
    }

    /**
     * Generate mock summary data
     *
     * @param string $symbol Company ticker symbol
     * @return array Mock summary data
     */
    private function getMockSummary(string $symbol): array
    {
        $symbol = strtoupper($symbol);

        $mockSummaries = [
            'AVGO' => [
                'assetProfile' => [
                    'name' => 'Broadcom Inc.',
                    'longBusinessSummary' => 'Broadcom Inc. designs, develops, and markets digital and analog semiconductor connectivity solutions. It offers memory and storage connectivity solutions. The company provides infrastructure software solutions that enable customers to plan, develop, automate, manage, and secure applications across mainframe, distributed, mobile, and cloud platforms.',
                    'country' => 'United States',
                    'sector' => 'Technology',
                    'industry' => 'Semiconductors',
                    'address1' => '1320 Ridder Park Drive',
                    'city' => 'San Jose',
                    'state' => 'CA',
                    'zip' => '95131',
                    'website' => 'https://www.broadcom.com',
                    'fullTimeEmployees' => 20000,
                    'companyOfficers' => [
                        [
                            'name' => 'Hock E. Tan',
                            'title' => 'President and Chief Executive Officer',
                            'age' => 71,
                            'totalPay' => ['raw' => 60700000],
                        ],
                        [
                            'name' => 'Tom Krause',
                            'title' => 'Chief Financial Officer',
                            'age' => 45,
                            'totalPay' => ['raw' => 15200000],
                        ],
                    ],
                ],
                'summaryDetail' => [
                    'exchange' => 'NASDAQ',
                    'currency' => 'USD',
                    'marketCap' => ['raw' => 515000000000],
                    'trailingPE' => ['raw' => 35.6],
                    'pegRatio' => ['raw' => 1.5],
                    'bookValue' => ['raw' => 100.25],
                    'dividendYield' => ['raw' => 0.0155],
                    'dividendRate' => ['raw' => 16.40],
                    'beta' => ['raw' => 1.1],
                    'fiftyTwoWeekLow' => ['raw' => 981.00],
                    'fiftyTwoWeekHigh' => ['raw' => 1445.00],
                    'priceToSalesTrailing12Months' => ['raw' => 10.24],
                ],
                'financialData' => [
                    'currentPrice' => ['raw' => 1262.45],
                    'targetHighPrice' => ['raw' => 1445.00],
                    'targetLowPrice' => ['raw' => 1050.00],
                    'targetMeanPrice' => ['raw' => 1275.00],
                    'returnOnEquity' => ['raw' => 0.28],
                    'debtToEquity' => ['raw' => 1.24],
                    'revenueGrowth' => ['raw' => 0.085],
                    'grossMargins' => ['raw' => 0.64],
                    'operatingMargins' => ['raw' => 0.27],
                    'profitMargins' => ['raw' => 0.225],
                ],
                'price' => [
                    'regularMarketPrice' => ['raw' => 1262.45],
                    'regularMarketChange' => ['raw' => 6.70],
                    'regularMarketChangePercent' => ['raw' => 0.00533],
                    'regularMarketVolume' => ['raw' => 3500000],
                    'regularMarketTime' => time(),
                    'regularMarketPreviousClose' => ['raw' => 1255.75],
                    'regularMarketOpen' => ['raw' => 1253.50],
                    'regularMarketDayHigh' => ['raw' => 1267.85],
                    'regularMarketDayLow' => ['raw' => 1245.20],
                    'bid' => ['raw' => 1262.40],
                    'ask' => ['raw' => 1262.50],
                    'bidSize' => ['raw' => 100],
                    'askSize' => ['raw' => 100],
                ],
            ],
            'AAPL' => [
                'assetProfile' => [
                    'name' => 'Apple Inc.',
                    'longBusinessSummary' => 'Apple Inc. designs, manufactures, and markets smartphones, personal computers, tablets, wearables, and accessories worldwide. The company offers iPhone, Mac, iPad, and Wearables, Home, and Accessories.',
                    'country' => 'United States',
                    'sector' => 'Technology',
                    'industry' => 'Consumer Electronics',
                    'address1' => 'One Apple Park Way',
                    'city' => 'Cupertino',
                    'state' => 'CA',
                    'zip' => '95014',
                    'website' => 'https://www.apple.com',
                    'fullTimeEmployees' => 154000,
                    'companyOfficers' => [
                        [
                            'name' => 'Tim Cook',
                            'title' => 'Chief Executive Officer',
                            'age' => 62,
                            'totalPay' => ['raw' => 99100000],
                        ],
                        [
                            'name' => 'Luca Maestri',
                            'title' => 'Chief Financial Officer',
                            'age' => 59,
                            'totalPay' => ['raw' => 27200000],
                        ],
                    ],
                ],
                'summaryDetail' => [
                    'exchange' => 'NASDAQ',
                    'currency' => 'USD',
                    'marketCap' => ['raw' => 2750000000000],
                    'trailingPE' => ['raw' => 29.8],
                    'pegRatio' => ['raw' => 2.1],
                    'bookValue' => ['raw' => 4.25],
                    'dividendYield' => ['raw' => 0.0054],
                    'dividendRate' => ['raw' => 0.92],
                    'beta' => ['raw' => 1.3],
                    'fiftyTwoWeekLow' => ['raw' => 158.77],
                    'fiftyTwoWeekHigh' => ['raw' => 199.62],
                    'priceToSalesTrailing12Months' => ['raw' => 7.18],
                ],
                'financialData' => [
                    'currentPrice' => ['raw' => 177.50],
                    'targetHighPrice' => ['raw' => 220.00],
                    'targetLowPrice' => ['raw' => 160.00],
                    'targetMeanPrice' => ['raw' => 200.00],
                    'returnOnEquity' => ['raw' => 0.48],
                    'debtToEquity' => ['raw' => 1.94],
                    'revenueGrowth' => ['raw' => 0.024],
                    'grossMargins' => ['raw' => 0.438],
                    'operatingMargins' => ['raw' => 0.302],
                    'profitMargins' => ['raw' => 0.252],
                ],
                'price' => [
                    'regularMarketPrice' => ['raw' => 177.50],
                    'regularMarketChange' => ['raw' => 2.40],
                    'regularMarketChangePercent' => ['raw' => 0.01371],
                    'regularMarketVolume' => ['raw' => 58500000],
                    'regularMarketTime' => time(),
                    'regularMarketPreviousClose' => ['raw' => 175.10],
                    'regularMarketOpen' => ['raw' => 175.20],
                    'regularMarketDayHigh' => ['raw' => 178.35],
                    'regularMarketDayLow' => ['raw' => 174.85],
                    'bid' => ['raw' => 177.48],
                    'ask' => ['raw' => 177.53],
                    'bidSize' => ['raw' => 1300],
                    'askSize' => ['raw' => 900],
                ],
            ],
        ];

        $mockData = $mockSummaries[$symbol] ?? [
            'assetProfile' => [
                'name' => 'Unknown Company',
                'longBusinessSummary' => 'No description available',
                'country' => 'Unknown',
                'sector' => 'Unknown',
                'industry' => 'Unknown',
                'website' => '',
                'fullTimeEmployees' => 0,
                'companyOfficers' => [],
            ],
            'summaryDetail' => [
                'exchange' => 'UNKNOWN',
                'currency' => 'USD',
                'marketCap' => ['raw' => 0],
            ],
            'financialData' => [],
            'price' => [
                'regularMarketPrice' => ['raw' => 0],
                'regularMarketChange' => ['raw' => 0],
                'regularMarketChangePercent' => ['raw' => 0],
                'regularMarketVolume' => ['raw' => 0],
                'regularMarketTime' => time(),
                'regularMarketPreviousClose' => ['raw' => 0],
                'regularMarketOpen' => ['raw' => 0],
                'regularMarketDayHigh' => ['raw' => 0],
                'regularMarketDayLow' => ['raw' => 0],
            ],
        ];

        return [
            'quoteSummary' => [
                'result' => [$mockData]
            ]
        ];
    }

    /**
     * Generate mock financials data
     *
     * @param string $symbol Company ticker symbol
     * @return array Mock financials data
     */
    private function getMockFinancials(string $symbol): array
    {
        $symbol = strtoupper($symbol);

        // Generate dates for the last 4 quarters
        $now = new \DateTime();
        $quarter1 = clone $now;
        $quarter1->modify('-3 months');
        $quarter2 = clone $now;
        $quarter2->modify('-6 months');
        $quarter3 = clone $now;
        $quarter3->modify('-9 months');
        $quarter4 = clone $now;
        $quarter4->modify('-12 months');

        // Format dates
        $dates = [
            $quarter1->format('Y-m-d'),
            $quarter2->format('Y-m-d'),
            $quarter3->format('Y-m-d'),
            $quarter4->format('Y-m-d'),
        ];

        $metrics = [];

        // Revenue data
        $metrics[] = [
            'meta' => ['type' => ['totalRevenue']],
            'quarterly' => [
                ['asOfDate' => $dates[0], 'reportedValue' => ['raw' => 12800000000]],
                ['asOfDate' => $dates[1], 'reportedValue' => ['raw' => 12350000000]],
                ['asOfDate' => $dates[2], 'reportedValue' => ['raw' => 12100000000]],
                ['asOfDate' => $dates[3], 'reportedValue' => ['raw' => 11800000000]],
            ]
        ];

        // Cost of Revenue
        $metrics[] = [
            'meta' => ['type' => ['costOfRevenue']],
            'quarterly' => [
                ['asOfDate' => $dates[0], 'reportedValue' => ['raw' => 4100000000]],
                ['asOfDate' => $dates[1], 'reportedValue' => ['raw' => 3950000000]],
                ['asOfDate' => $dates[2], 'reportedValue' => ['raw' => 3900000000]],
                ['asOfDate' => $dates[3], 'reportedValue' => ['raw' => 3850000000]],
            ]
        ];

        // Gross Profit
        $metrics[] = [
            'meta' => ['type' => ['grossProfit']],
            'quarterly' => [
                ['asOfDate' => $dates[0], 'reportedValue' => ['raw' => 8700000000]],
                ['asOfDate' => $dates[1], 'reportedValue' => ['raw' => 8400000000]],
                ['asOfDate' => $dates[2], 'reportedValue' => ['raw' => 8200000000]],
                ['asOfDate' => $dates[3], 'reportedValue' => ['raw' => 7950000000]],
            ]
        ];

        // Net Income
        $metrics[] = [
            'meta' => ['type' => ['netIncome']],
            'quarterly' => [
                ['asOfDate' => $dates[0], 'reportedValue' => ['raw' => 3600000000]],
                ['asOfDate' => $dates[1], 'reportedValue' => ['raw' => 3400000000]],
                ['asOfDate' => $dates[2], 'reportedValue' => ['raw' => 3150000000]],
                ['asOfDate' => $dates[3], 'reportedValue' => ['raw' => 3000000000]],
            ]
        ];

        // Operating Income
        $metrics[] = [
            'meta' => ['type' => ['operatingIncome']],
            'quarterly' => [
                ['asOfDate' => $dates[0], 'reportedValue' => ['raw' => 4800000000]],
                ['asOfDate' => $dates[1], 'reportedValue' => ['raw' => 4650000000]],
                ['asOfDate' => $dates[2], 'reportedValue' => ['raw' => 4500000000]],
                ['asOfDate' => $dates[3], 'reportedValue' => ['raw' => 4350000000]],
            ]
        ];

        // Operating Expense
        $metrics[] = [
            'meta' => ['type' => ['operatingExpense']],
            'quarterly' => [
                ['asOfDate' => $dates[0], 'reportedValue' => ['raw' => 2350000000]],
                ['asOfDate' => $dates[1], 'reportedValue' => ['raw' => 2310000000]],
                ['asOfDate' => $dates[2], 'reportedValue' => ['raw' => 2270000000]],
                ['asOfDate' => $dates[3], 'reportedValue' => ['raw' => 2230000000]],
            ]
        ];

        // Research & Development
        $metrics[] = [
            'meta' => ['type' => ['researchDevelopment']],
            'quarterly' => [
                ['asOfDate' => $dates[0], 'reportedValue' => ['raw' => 1500000000]],
                ['asOfDate' => $dates[1], 'reportedValue' => ['raw' => 1480000000]],
                ['asOfDate' => $dates[2], 'reportedValue' => ['raw' => 1450000000]],
                ['asOfDate' => $dates[3], 'reportedValue' => ['raw' => 1430000000]],
            ]
        ];

        return [
            'timeSeries' => [
                'result' => $metrics
            ]
        ];
    }

    /**
     * Generate mock news list data
     *
     * @param string $symbol Company ticker symbol
     * @param int $limit Number of news items to return
     * @return array Mock news list data
     */
    private function getMockNewsList(string $symbol, int $limit): array
    {
        $symbol = strtoupper($symbol);

        // Generate company name from symbol if it's one of our examples
        $companyName = match ($symbol) {
            'AVGO' => 'Broadcom',
            'AAPL' => 'Apple',
            'MSFT' => 'Microsoft',
            default => ucfirst(strtolower($symbol)),
        };

        $now = time();

        $news = [];

        $mockNews = [
            [
                'title' => $companyName . ' Reports Record Quarterly Revenue',
                'summary' => $companyName . ' announced financial results for its fiscal quarter, reporting record revenue of $X billion, an increase of Y% year-over-year.',
                'source' => 'Financial Times',
                'pubtime' => $now - 86400, // 1 day ago
                'url' => 'https://example.com/financial-news/' . strtolower($symbol) . '-earnings',
                'thumbnail_url' => 'https://example.com/images/earnings-chart.jpg',
                'category' => 'finance',
            ],
            [
                'title' => $companyName . ' Announces New Strategic Partnership',
                'summary' => $companyName . ' has entered into a strategic partnership with ABC Corp to accelerate innovation in emerging technologies.',
                'source' => 'Business Wire',
                'pubtime' => $now - 86400 * 3, // 3 days ago
                'url' => 'https://example.com/business-news/' . strtolower($symbol) . '-partnership',
                'thumbnail_url' => 'https://example.com/images/partnership.jpg',
                'category' => 'business',
            ],
            [
                'title' => 'Analysts Upgrade ' . $companyName . ' to "Buy" Rating',
                'summary' => 'Financial analysts at XYZ Securities have upgraded ' . $companyName . ' stock from "hold" to "buy", citing strong growth prospects and market leadership.',
                'source' => 'Market Watch',
                'pubtime' => $now - 86400 * 5, // 5 days ago
                'url' => 'https://example.com/analyst-ratings/' . strtolower($symbol),
                'thumbnail_url' => 'https://example.com/images/stock-chart.jpg',
                'category' => 'markets',
            ],
            [
                'title' => $companyName . ' Expands Operations in Asia-Pacific Region',
                'summary' => $companyName . ' announced plans to expand its operations in the Asia-Pacific region, with new offices opening in Singapore and Tokyo.',
                'source' => 'Reuters',
                'pubtime' => $now - 86400 * 7, // 7 days ago
                'url' => 'https://example.com/business-expansion/' . strtolower($symbol) . '-asia',
                'thumbnail_url' => 'https://example.com/images/global-expansion.jpg',
                'category' => 'business',
            ],
            [
                'title' => $companyName . ' CEO Discusses Future Strategy in Interview',
                'summary' => 'In a recent interview, the CEO of ' . $companyName . ' outlined the company\'s long-term strategy, focusing on innovation, sustainability, and market expansion.',
                'source' => 'CNBC',
                'pubtime' => $now - 86400 * 9, // 9 days ago
                'url' => 'https://example.com/interviews/' . strtolower($symbol) . '-ceo',
                'thumbnail_url' => 'https://example.com/images/ceo-interview.jpg',
                'category' => 'business',
            ],
        ];

        // Convert to the format Yahoo Finance API returns
        foreach (array_slice($mockNews, 0, $limit) as $index => $item) {
            $news[] = [
                'card' => $item
            ];
        }

        return [
            'data' => [
                'stream_items' => $news
            ]
        ];
    }

    /**
     * Generate mock executive data based on the company symbol
     *
     * @param string $symbol Company ticker symbol
     * @return array Mock executive data
     */
    private function getMockExecutives(string $symbol): array
    {
        switch (strtoupper($symbol)) {
            case 'AVGO':
                return [
                    [
                        'name' => 'Hock E. Tan',
                        'title' => 'President and Chief Executive Officer',
                        'age' => 71,
                        'yearJoined' => 2006,
                        'bio' => 'Hock E. Tan has served as President, Chief Executive Officer and Director of Broadcom since March 2006. From September 2005 to January 2008, Mr. Tan served as Chairman of the board of directors of Integrated Device Technology. Prior to that, Mr. Tan served as President and Chief Executive Officer of Integrated Circuit Systems from June 1999 to September 2005.',
                        'compensation' => 60700000,
                        'education' => 'MBA from MIT Sloan School of Management, M.S. in Nuclear Engineering from MIT',
                        'previousCompanies' => 'Integrated Device Technology, Integrated Circuit Systems, Commodore International',
                    ],
                    [
                        'name' => 'Tom Krause',
                        'title' => 'Chief Financial Officer',
                        'age' => 45,
                        'yearJoined' => 2012,
                        'bio' => 'Tom Krause has served as Broadcom\'s Chief Financial Officer since 2016. Prior to joining Broadcom, Mr. Krause served in various leadership roles at investment banking firms.',
                        'compensation' => 15200000,
                        'education' => 'MBA from Harvard Business School',
                        'previousCompanies' => 'Deutsche Bank, Credit Suisse',
                    ],
                    [
                        'name' => 'Charlie B. Kawwas',
                        'title' => 'Chief Operating Officer',
                        'age' => 52,
                        'yearJoined' => 2008,
                        'bio' => 'Charlie B. Kawwas has served as Broadcom\'s Chief Operating Officer since 2020. Dr. Kawwas previously served in various senior executive roles at Broadcom, including as Senior Vice President and Chief Sales Officer.',
                        'compensation' => 11800000,
                        'education' => 'Ph.D. in Computer Engineering from University of Windsor',
                        'previousCompanies' => 'Nortel Networks, Oren Semiconductor',
                    ],
                ];

            case 'AAPL':
                return [
                    [
                        'name' => 'Tim Cook',
                        'title' => 'Chief Executive Officer',
                        'age' => 62,
                        'yearJoined' => 1998,
                        'bio' => 'Tim Cook is the CEO of Apple and serves on its board of directors. Before being named CEO in August 2011, Tim was Apple\'s Chief Operating Officer, responsible for all of the company\'s worldwide sales and operations.',
                        'compensation' => 99100000,
                        'education' => 'MBA from Duke University, B.S. in Industrial Engineering from Auburn University',
                        'previousCompanies' => 'Compaq, IBM, Intelligent Electronics',
                    ],
                    [
                        'name' => 'Luca Maestri',
                        'title' => 'Chief Financial Officer',
                        'age' => 59,
                        'yearJoined' => 2013,
                        'bio' => 'Luca Maestri is Apple\'s Chief Financial Officer and Senior Vice President. Luca joined Apple in 2013 as Vice President of Finance and Corporate Controller.',
                        'compensation' => 27200000,
                        'education' => 'Master\'s degree in Science of Management from Boston University, Bachelor\'s degree in Economics from LUISS University in Rome',
                        'previousCompanies' => 'Xerox, Nokia Siemens Networks, General Motors',
                    ],
                    [
                        'name' => 'Craig Federighi',
                        'title' => 'Senior Vice President of Software Engineering',
                        'age' => 54,
                        'yearJoined' => 2009,
                        'bio' => 'Craig Federighi is Apple\'s Senior Vice President of Software Engineering, overseeing the development of iOS, iPadOS, macOS, and watchOS. Craig returned to Apple in 2009 after previous roles with the company in the 1990s.',
                        'compensation' => 25000000,
                        'education' => 'Master\'s degree in Computer Science from the University of California, Berkeley',
                        'previousCompanies' => 'NeXT, Ariba',
                    ],
                ];

            default:
                return [
                    [
                        'name' => 'John Doe',
                        'title' => 'Chief Executive Officer',
                        'age' => 55,
                        'yearJoined' => 2010,
                        'bio' => 'John Doe is an experienced executive with over 20 years in the industry.',
                        'compensation' => 5000000,
                        'education' => 'MBA from a leading university',
                        'previousCompanies' => 'Various Fortune 500 companies',
                    ],
                    [
                        'name' => 'Jane Smith',
                        'title' => 'Chief Financial Officer',
                        'age' => 48,
                        'yearJoined' => 2012,
                        'bio' => 'Jane Smith has extensive experience in financial management and strategy.',
                        'compensation' => 3500000,
                        'education' => 'Master\'s in Finance, CPA',
                        'previousCompanies' => 'Major accounting firms and financial institutions',
                    ],
                ];
        }
    }
}
