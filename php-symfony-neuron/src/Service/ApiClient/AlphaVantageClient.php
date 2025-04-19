<?php

namespace App\Service\ApiClient;

/**
 * Alpha Vantage API client
 * 
 * Documentation: https://www.alphavantage.co/documentation/
 */
class AlphaVantageClient extends AbstractApiClient
{
    /**
     * {@inheritdoc}
     */
    protected function initialize(): void
    {
        $this->baseUrl = 'https://www.alphavantage.co/query';
        $this->apiKey = $this->params->get('alpha_vantage.api_key', '');
        
        // During development without API key, log a message
        if (empty($this->apiKey)) {
            $this->logger->warning('Alpha Vantage API key not set, using mock data');
        }
    }
    
    /**
     * {@inheritdoc}
     */
    protected function getAuthParams(): array
    {
        return ['apikey' => $this->apiKey];
    }
    
    /**
     * {@inheritdoc}
     */
    public function searchCompanies(string $term): array
    {
        $params = [
            'function' => 'SYMBOL_SEARCH',
            'keywords' => $term
        ];
        
        $data = $this->request('GET', '', $params);
        
        $results = [];
        if (isset($data['bestMatches']) && is_array($data['bestMatches'])) {
            foreach ($data['bestMatches'] as $match) {
                $results[] = [
                    'symbol' => $match['1. symbol'] ?? '',
                    'name' => $match['2. name'] ?? '',
                    'type' => $match['3. type'] ?? '',
                    'region' => $match['4. region'] ?? '',
                    'currency' => $match['8. currency'] ?? '',
                    'matchScore' => $match['9. matchScore'] ?? '',
                ];
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
            'function' => 'OVERVIEW',
            'symbol' => $symbol
        ];
        
        $data = $this->request('GET', '', $params);
        
        // Format the data
        return [
            'symbol' => $data['Symbol'] ?? $symbol,
            'name' => $data['Name'] ?? '',
            'description' => $data['Description'] ?? '',
            'exchange' => $data['Exchange'] ?? '',
            'currency' => $data['Currency'] ?? 'USD',
            'country' => $data['Country'] ?? '',
            'sector' => $data['Sector'] ?? '',
            'industry' => $data['Industry'] ?? '',
            'address' => $data['Address'] ?? '',
            'employees' => (int)($data['FullTimeEmployees'] ?? 0),
            'fiscalYearEnd' => $data['FiscalYearEnd'] ?? '',
            'marketCap' => (float)($data['MarketCapitalization'] ?? 0),
            'peRatio' => (float)($data['PERatio'] ?? 0),
            'pegRatio' => (float)($data['PEGRatio'] ?? 0),
            'bookValue' => (float)($data['BookValue'] ?? 0),
            'dividendPerShare' => (float)($data['DividendPerShare'] ?? 0),
            'dividendYield' => (float)($data['DividendYield'] ?? 0),
            'eps' => (float)($data['EPS'] ?? 0),
            'revenuePerShareTTM' => (float)($data['RevenuePerShareTTM'] ?? 0),
            'profitMargin' => (float)($data['ProfitMargin'] ?? 0),
            'operatingMarginTTM' => (float)($data['OperatingMarginTTM'] ?? 0),
            'returnOnAssetsTTM' => (float)($data['ReturnOnAssetsTTM'] ?? 0),
            'returnOnEquityTTM' => (float)($data['ReturnOnEquityTTM'] ?? 0),
            'revenueTTM' => (float)($data['RevenueTTM'] ?? 0),
            'grossProfitTTM' => (float)($data['GrossProfitTTM'] ?? 0),
            'quarterlyEarningsGrowthYOY' => (float)($data['QuarterlyEarningsGrowthYOY'] ?? 0),
            'quarterlyRevenueGrowthYOY' => (float)($data['QuarterlyRevenueGrowthYOY'] ?? 0),
            'analystTargetPrice' => (float)($data['AnalystTargetPrice'] ?? 0),
            'beta' => (float)($data['Beta'] ?? 0),
            '52WeekHigh' => (float)($data['52WeekHigh'] ?? 0),
            '52WeekLow' => (float)($data['52WeekLow'] ?? 0),
            '50DayMovingAverage' => (float)($data['50DayMovingAverage'] ?? 0),
            '200DayMovingAverage' => (float)($data['200DayMovingAverage'] ?? 0),
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function getQuote(string $symbol): array
    {
        $params = [
            'function' => 'GLOBAL_QUOTE',
            'symbol' => $symbol
        ];
        
        $data = $this->request('GET', '', $params);
        
        if (isset($data['Global Quote'])) {
            $quote = $data['Global Quote'];
            return [
                'symbol' => $quote['01. symbol'] ?? $symbol,
                'price' => (float)($quote['05. price'] ?? 0),
                'change' => (float)($quote['09. change'] ?? 0),
                'changePercent' => str_replace('%', '', $quote['10. change percent'] ?? '0'),
                'volume' => (int)($quote['06. volume'] ?? 0),
                'latestTradingDay' => $quote['07. latest trading day'] ?? date('Y-m-d'),
                'previousClose' => (float)($quote['08. previous close'] ?? 0),
                'open' => (float)($quote['02. open'] ?? 0),
                'high' => (float)($quote['03. high'] ?? 0),
                'low' => (float)($quote['04. low'] ?? 0),
            ];
        }
        
        return [
            'symbol' => $symbol,
            'price' => 0,
            'change' => 0,
            'changePercent' => 0,
            'volume' => 0,
            'latestTradingDay' => date('Y-m-d'),
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
        $function = $period === 'quarterly' ? 'INCOME_STATEMENT' : 'INCOME_STATEMENT';
        
        $params = [
            'function' => $function,
            'symbol' => $symbol
        ];
        
        $data = $this->request('GET', '', $params);
        
        $financials = [];
        if (isset($data['quarterlyReports']) && $period === 'quarterly') {
            $reports = array_slice($data['quarterlyReports'], 0, 4); // Get last 4 quarters
            foreach ($reports as $report) {
                $financials[] = $this->formatFinancialReport($report, $symbol);
            }
        } elseif (isset($data['annualReports'])) {
            $reports = array_slice($data['annualReports'], 0, 3); // Get last 3 years
            foreach ($reports as $report) {
                $financials[] = $this->formatFinancialReport($report, $symbol);
            }
        }
        
        return $financials;
    }
    
    /**
     * Format a financial report
     * 
     * @param array $report Raw report data
     * @param string $symbol Company symbol
     * @return array Formatted report
     */
    private function formatFinancialReport(array $report, string $symbol): array
    {
        // Extract fiscal period from fiscal date ending (e.g., 2021-12-31 -> Q4 2021)
        $fiscalDate = $report['fiscalDateEnding'] ?? date('Y-m-d');
        $date = new \DateTime($fiscalDate);
        $year = $date->format('Y');
        $month = (int)$date->format('m');
        
        if ($month <= 3) {
            $quarter = 'Q1';
        } elseif ($month <= 6) {
            $quarter = 'Q2';
        } elseif ($month <= 9) {
            $quarter = 'Q3';
        } else {
            $quarter = 'Q4';
        }
        
        return [
            'symbol' => $symbol,
            'fiscalDate' => $fiscalDate,
            'fiscalQuarter' => $quarter,
            'fiscalYear' => $year,
            'reportDate' => $fiscalDate,
            'reportType' => 'Income Statement',
            'currency' => 'USD',
            'revenue' => (float)($report['totalRevenue'] ?? 0),
            'costOfRevenue' => (float)($report['costOfRevenue'] ?? 0),
            'grossProfit' => (float)($report['grossProfit'] ?? 0),
            'netIncome' => (float)($report['netIncome'] ?? 0),
            'operatingIncome' => (float)($report['operatingIncome'] ?? 0),
            'ebitda' => (float)($report['ebitda'] ?? 0),
            'eps' => 0, // Not directly provided in income statement
            'researchAndDevelopment' => (float)($report['researchAndDevelopment'] ?? 0),
            'sellingGeneralAdministrative' => (float)($report['sellingGeneralAndAdministrativeExpenses'] ?? 0),
            'operatingExpenses' => (float)($report['operatingExpenses'] ?? 0),
            'interestExpense' => (float)($report['interestExpense'] ?? 0),
            'incomeTaxExpense' => (float)($report['incomeTaxExpense'] ?? 0),
            'netIncomeFromContinuingOperations' => (float)($report['netIncomeFromContinuingOperations'] ?? 0),
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function getCompanyNews(string $symbol, int $limit = 5): array
    {
        // Alpha Vantage doesn't have a dedicated news API, so we'll return mock data for now
        $this->logger->info("Alpha Vantage doesn't have a dedicated news API, using mock data");
        return $this->getMockNews($symbol, $limit);
    }
    
    /**
     * {@inheritdoc}
     */
    public function getExecutives(string $symbol): array
    {
        // Alpha Vantage doesn't have an executive/leadership API, so we'll return mock data for now
        $this->logger->info("Alpha Vantage doesn't have a leadership API, using mock data");
        return $this->getMockExecutives($symbol);
    }
    
    /**
     * {@inheritdoc}
     */
    public function getHistoricalPrices(string $symbol, string $interval = 'daily', string $outputSize = 'compact'): array
    {
        // Map interval to Alpha Vantage function
        $function = match($interval) {
            'daily' => 'TIME_SERIES_DAILY',
            'weekly' => 'TIME_SERIES_WEEKLY',
            'monthly' => 'TIME_SERIES_MONTHLY',
            default => 'TIME_SERIES_DAILY',
        };
        
        $params = [
            'function' => $function,
            'symbol' => $symbol,
            'outputsize' => $outputSize,
            'datatype' => 'json'
        ];
        
        $data = $this->request('GET', '', $params);
        
        // Extract the time series data
        $timeSeriesKey = match($interval) {
            'daily' => 'Time Series (Daily)',
            'weekly' => 'Weekly Time Series',
            'monthly' => 'Monthly Time Series',
            default => 'Time Series (Daily)',
        };
        
        $prices = [];
        
        if (isset($data[$timeSeriesKey]) && is_array($data[$timeSeriesKey])) {
            foreach ($data[$timeSeriesKey] as $date => $priceData) {
                $prices[] = [
                    'date' => $date,
                    'open' => (float)($priceData['1. open'] ?? 0),
                    'high' => (float)($priceData['2. high'] ?? 0),
                    'low' => (float)($priceData['3. low'] ?? 0),
                    'close' => (float)($priceData['4. close'] ?? 0),
                    'volume' => (int)($priceData['5. volume'] ?? 0),
                    'adjustedClose' => (float)($priceData['5. adjusted close'] ?? $priceData['4. close'] ?? 0),
                    'dividend' => (float)($priceData['7. dividend amount'] ?? 0),
                    'split' => (float)($priceData['8. split coefficient'] ?? 1),
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
        $function = $params['function'] ?? '';
        $symbol = $params['symbol'] ?? 'UNKNOWN';
        
        switch ($function) {
            case 'SYMBOL_SEARCH':
                return $this->getMockSearchResults($params['keywords'] ?? '');
            case 'OVERVIEW':
                return $this->getMockCompanyProfile($symbol);
            case 'GLOBAL_QUOTE':
                return $this->getMockQuote($symbol);
            case 'INCOME_STATEMENT':
                return $this->getMockIncomeStatement($symbol);
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
        
        // For AVGO (Broadcom) search
        if (strpos($term, 'AVGO') !== false || strpos($term, 'BROADCOM') !== false) {
            return [
                'bestMatches' => [
                    [
                        '1. symbol' => 'AVGO',
                        '2. name' => 'Broadcom Inc',
                        '3. type' => 'Equity',
                        '4. region' => 'United States',
                        '8. currency' => 'USD',
                        '9. matchScore' => '0.9000'
                    ]
                ]
            ];
        }
        
        // For AAPL (Apple) search
        if (strpos($term, 'AAPL') !== false || strpos($term, 'APPLE') !== false) {
            return [
                'bestMatches' => [
                    [
                        '1. symbol' => 'AAPL',
                        '2. name' => 'Apple Inc',
                        '3. type' => 'Equity',
                        '4. region' => 'United States',
                        '8. currency' => 'USD',
                        '9. matchScore' => '0.9500'
                    ]
                ]
            ];
        }
        
        // For MSFT (Microsoft) search
        if (strpos($term, 'MSFT') !== false || strpos($term, 'MICROSOFT') !== false) {
            return [
                'bestMatches' => [
                    [
                        '1. symbol' => 'MSFT',
                        '2. name' => 'Microsoft Corporation',
                        '3. type' => 'Equity',
                        '4. region' => 'United States',
                        '8. currency' => 'USD',
                        '9. matchScore' => '0.9500'
                    ]
                ]
            ];
        }
        
        // Default: Return empty results
        return ['bestMatches' => []];
    }
    
    /**
     * Generate mock company profile
     *
     * @param string $symbol Company ticker symbol
     * @return array Mock company profile
     */
    private function getMockCompanyProfile(string $symbol): array
    {
        switch (strtoupper($symbol)) {
            case 'AVGO':
                return [
                    'Symbol' => 'AVGO',
                    'Name' => 'Broadcom Inc',
                    'Description' => 'Broadcom Inc. designs, develops, and markets digital and analog semiconductor connectivity solutions. It offers memory and storage connectivity solutions. The company provides infrastructure software solutions that enable customers to plan, develop, automate, manage, and secure applications across mainframe, distributed, mobile, and cloud platforms.',
                    'Exchange' => 'NASDAQ',
                    'Currency' => 'USD',
                    'Country' => 'USA',
                    'Sector' => 'Technology',
                    'Industry' => 'Semiconductors',
                    'Address' => '1320 Ridder Park Drive, San Jose, CA, United States, 95131',
                    'FullTimeEmployees' => '20000',
                    'FiscalYearEnd' => 'October',
                    'MarketCapitalization' => '515000000000',
                    'PERatio' => '35.6',
                    'PEGRatio' => '1.5',
                    'BookValue' => '100.25',
                    'DividendPerShare' => '16.40',
                    'DividendYield' => '0.0155',
                    'EPS' => '32.50',
                    'RevenuePerShareTTM' => '145.80',
                    'ProfitMargin' => '0.225',
                    'OperatingMarginTTM' => '0.27',
                    'ReturnOnAssetsTTM' => '0.09',
                    'ReturnOnEquityTTM' => '0.28',
                    'RevenueTTM' => '50300000000',
                    'GrossProfitTTM' => '32500000000',
                    'QuarterlyEarningsGrowthYOY' => '0.124',
                    'QuarterlyRevenueGrowthYOY' => '0.085',
                    'AnalystTargetPrice' => '1275.00',
                    'Beta' => '1.1',
                    '52WeekHigh' => '1445.00',
                    '52WeekLow' => '981.00',
                    '50DayMovingAverage' => '1275.50',
                    '200DayMovingAverage' => '1150.25',
                ];
            
            case 'AAPL':
                return [
                    'Symbol' => 'AAPL',
                    'Name' => 'Apple Inc',
                    'Description' => 'Apple Inc. designs, manufactures, and markets smartphones, personal computers, tablets, wearables, and accessories worldwide. The company offers iPhone, Mac, iPad, and Wearables, Home, and Accessories.',
                    'Exchange' => 'NASDAQ',
                    'Currency' => 'USD',
                    'Country' => 'USA',
                    'Sector' => 'Technology',
                    'Industry' => 'Consumer Electronics',
                    'Address' => 'One Apple Park Way, Cupertino, CA, United States, 95014',
                    'FullTimeEmployees' => '154000',
                    'FiscalYearEnd' => 'September',
                    'MarketCapitalization' => '2750000000000',
                    'PERatio' => '29.8',
                    'PEGRatio' => '2.1',
                    'BookValue' => '4.25',
                    'DividendPerShare' => '0.92',
                    'DividendYield' => '0.0054',
                    'EPS' => '6.14',
                    'RevenuePerShareTTM' => '24.35',
                    'ProfitMargin' => '0.252',
                    'OperatingMarginTTM' => '0.302',
                    'ReturnOnAssetsTTM' => '0.18',
                    'ReturnOnEquityTTM' => '0.48',
                    'RevenueTTM' => '383000000000',
                    'GrossProfitTTM' => '170000000000',
                    'QuarterlyEarningsGrowthYOY' => '0.075',
                    'QuarterlyRevenueGrowthYOY' => '0.024',
                    'AnalystTargetPrice' => '200.00',
                    'Beta' => '1.3',
                    '52WeekHigh' => '199.62',
                    '52WeekLow' => '158.77',
                    '50DayMovingAverage' => '178.35',
                    '200DayMovingAverage' => '185.42',
                ];
                
            default:
                return [
                    'Symbol' => $symbol,
                    'Name' => 'Unknown Company',
                    'Description' => 'No description available',
                    'Exchange' => 'UNKNOWN',
                    'Currency' => 'USD',
                    'Country' => 'Unknown',
                    'Sector' => 'Unknown',
                    'Industry' => 'Unknown',
                ];
        }
    }
    
    /**
     * Generate mock quote data
     *
     * @param string $symbol Company ticker symbol
     * @return array Mock quote data
     */
    private function getMockQuote(string $symbol): array
    {
        switch (strtoupper($symbol)) {
            case 'AVGO':
                return [
                    'Global Quote' => [
                        '01. symbol' => 'AVGO',
                        '02. open' => '1253.50',
                        '03. high' => '1267.85',
                        '04. low' => '1245.20',
                        '05. price' => '1262.45',
                        '06. volume' => '3500000',
                        '07. latest trading day' => date('Y-m-d'),
                        '08. previous close' => '1255.75',
                        '09. change' => '6.70',
                        '10. change percent' => '0.5337%'
                    ]
                ];
                
            case 'AAPL':
                return [
                    'Global Quote' => [
                        '01. symbol' => 'AAPL',
                        '02. open' => '175.20',
                        '03. high' => '178.35',
                        '04. low' => '174.85',
                        '05. price' => '177.50',
                        '06. volume' => '58500000',
                        '07. latest trading day' => date('Y-m-d'),
                        '08. previous close' => '175.10',
                        '09. change' => '2.40',
                        '10. change percent' => '1.3707%'
                    ]
                ];
                
            default:
                return [
                    'Global Quote' => [
                        '01. symbol' => $symbol,
                        '02. open' => '100.00',
                        '03. high' => '102.00',
                        '04. low' => '98.00',
                        '05. price' => '101.50',
                        '06. volume' => '1000000',
                        '07. latest trading day' => date('Y-m-d'),
                        '08. previous close' => '100.50',
                        '09. change' => '1.00',
                        '10. change percent' => '0.9950%'
                    ]
                ];
        }
    }
    
    /**
     * Generate mock income statement data
     *
     * @param string $symbol Company ticker symbol
     * @return array Mock income statement data
     */
    private function getMockIncomeStatement(string $symbol): array
    {
        $now = new \DateTime();
        $quarter1 = clone $now;
        $quarter1->modify('-3 months');
        $quarter2 = clone $now;
        $quarter2->modify('-6 months');
        $quarter3 = clone $now;
        $quarter3->modify('-9 months');
        $quarter4 = clone $now;
        $quarter4->modify('-12 months');
        
        switch (strtoupper($symbol)) {
            case 'AVGO':
                return [
                    'quarterlyReports' => [
                        [
                            'fiscalDateEnding' => $quarter1->format('Y-m-d'),
                            'totalRevenue' => '12800000000',
                            'costOfRevenue' => '4100000000',
                            'grossProfit' => '8700000000',
                            'netIncome' => '3600000000',
                            'operatingIncome' => '4800000000',
                            'ebitda' => '5200000000',
                            'researchAndDevelopment' => '1500000000',
                            'sellingGeneralAndAdministrativeExpenses' => '850000000',
                            'operatingExpenses' => '2350000000',
                            'interestExpense' => '520000000',
                            'incomeTaxExpense' => '380000000',
                            'netIncomeFromContinuingOperations' => '3600000000',
                        ],
                        [
                            'fiscalDateEnding' => $quarter2->format('Y-m-d'),
                            'totalRevenue' => '12350000000',
                            'costOfRevenue' => '3950000000',
                            'grossProfit' => '8400000000',
                            'netIncome' => '3400000000',
                            'operatingIncome' => '4650000000',
                            'ebitda' => '5050000000',
                            'researchAndDevelopment' => '1480000000',
                            'sellingGeneralAndAdministrativeExpenses' => '830000000',
                            'operatingExpenses' => '2310000000',
                            'interestExpense' => '510000000',
                            'incomeTaxExpense' => '370000000',
                            'netIncomeFromContinuingOperations' => '3400000000',
                        ],
                        [
                            'fiscalDateEnding' => $quarter3->format('Y-m-d'),
                            'totalRevenue' => '12100000000',
                            'costOfRevenue' => '3900000000',
                            'grossProfit' => '8200000000',
                            'netIncome' => '3150000000',
                            'operatingIncome' => '4500000000',
                            'ebitda' => '4900000000',
                            'researchAndDevelopment' => '1450000000',
                            'sellingGeneralAndAdministrativeExpenses' => '820000000',
                            'operatingExpenses' => '2270000000',
                            'interestExpense' => '500000000',
                            'incomeTaxExpense' => '360000000',
                            'netIncomeFromContinuingOperations' => '3150000000',
                        ],
                        [
                            'fiscalDateEnding' => $quarter4->format('Y-m-d'),
                            'totalRevenue' => '11800000000',
                            'costOfRevenue' => '3850000000',
                            'grossProfit' => '7950000000',
                            'netIncome' => '3000000000',
                            'operatingIncome' => '4350000000',
                            'ebitda' => '4750000000',
                            'researchAndDevelopment' => '1430000000',
                            'sellingGeneralAndAdministrativeExpenses' => '800000000',
                            'operatingExpenses' => '2230000000',
                            'interestExpense' => '490000000',
                            'incomeTaxExpense' => '350000000',
                            'netIncomeFromContinuingOperations' => '3000000000',
                        ],
                    ],
                    'annualReports' => [
                        [
                            'fiscalDateEnding' => (new \DateTime('last day of October'))->modify('-1 year')->format('Y-m-d'),
                            'totalRevenue' => '50300000000',
                            'costOfRevenue' => '16200000000',
                            'grossProfit' => '34100000000',
                            'netIncome' => '14000000000',
                            'operatingIncome' => '18500000000',
                            'ebitda' => '20100000000',
                            'researchAndDevelopment' => '5860000000',
                            'sellingGeneralAndAdministrativeExpenses' => '3300000000',
                            'operatingExpenses' => '9160000000',
                            'interestExpense' => '2050000000',
                            'incomeTaxExpense' => '1500000000',
                            'netIncomeFromContinuingOperations' => '14000000000',
                        ],
                    ],
                ];
                
            default:
                return [
                    'quarterlyReports' => [
                        [
                            'fiscalDateEnding' => $quarter1->format('Y-m-d'),
                            'totalRevenue' => '5000000000',
                            'costOfRevenue' => '2500000000',
                            'grossProfit' => '2500000000',
                            'netIncome' => '1000000000',
                            'operatingIncome' => '1500000000',
                            'ebitda' => '2000000000',
                            'researchAndDevelopment' => '750000000',
                            'sellingGeneralAndAdministrativeExpenses' => '500000000',
                            'operatingExpenses' => '1250000000',
                            'interestExpense' => '100000000',
                            'incomeTaxExpense' => '200000000',
                            'netIncomeFromContinuingOperations' => '1000000000',
                        ],
                    ],
                    'annualReports' => [
                        [
                            'fiscalDateEnding' => (new \DateTime('last day of December'))->modify('-1 year')->format('Y-m-d'),
                            'totalRevenue' => '20000000000',
                            'costOfRevenue' => '10000000000',
                            'grossProfit' => '10000000000',
                            'netIncome' => '4000000000',
                            'operatingIncome' => '6000000000',
                            'ebitda' => '8000000000',
                            'researchAndDevelopment' => '3000000000',
                            'sellingGeneralAndAdministrativeExpenses' => '2000000000',
                            'operatingExpenses' => '5000000000',
                            'interestExpense' => '400000000',
                            'incomeTaxExpense' => '800000000',
                            'netIncomeFromContinuingOperations' => '4000000000',
                        ],
                    ],
                ];
        }
    }
    
    /**
     * Generate mock news articles
     *
     * @param string $symbol Company ticker symbol
     * @param int $limit Number of articles to return
     * @return array Mock news articles
     */
    private function getMockNews(string $symbol, int $limit): array
    {
        $news = [];
        
        // Generic news items customized by company
        $companyName = match (strtoupper($symbol)) {
            'AVGO' => 'Broadcom',
            'AAPL' => 'Apple',
            'MSFT' => 'Microsoft',
            default => ucfirst(strtolower($symbol)),
        };
        
        $now = new \DateTime();
        
        $newsItems = [
            [
                'title' => $companyName . ' Reports Record Quarterly Revenue',
                'summary' => $companyName . ' announced financial results for its fiscal quarter, reporting record revenue of $X billion, an increase of Y% year-over-year.',
                'source' => 'Financial Times',
                'publishedAt' => $now->modify('-1 day')->format('Y-m-d H:i:s'),
                'url' => 'https://example.com/financial-news/' . strtolower($symbol) . '-earnings',
                'imageUrl' => 'https://example.com/images/earnings-chart.jpg',
            ],
            [
                'title' => $companyName . ' Announces New Strategic Partnership',
                'summary' => $companyName . ' has entered into a strategic partnership with ABC Corp to accelerate innovation in emerging technologies.',
                'source' => 'Business Wire',
                'publishedAt' => $now->modify('-3 days')->format('Y-m-d H:i:s'),
                'url' => 'https://example.com/business-news/' . strtolower($symbol) . '-partnership',
                'imageUrl' => 'https://example.com/images/partnership.jpg',
            ],
            [
                'title' => 'Analysts Upgrade ' . $companyName . ' to "Buy" Rating',
                'summary' => 'Financial analysts at XYZ Securities have upgraded ' . $companyName . ' stock from "hold" to "buy", citing strong growth prospects and market leadership.',
                'source' => 'Market Watch',
                'publishedAt' => $now->modify('-5 days')->format('Y-m-d H:i:s'),
                'url' => 'https://example.com/analyst-ratings/' . strtolower($symbol),
                'imageUrl' => 'https://example.com/images/stock-chart.jpg',
            ],
            [
                'title' => $companyName . ' Expands Operations in Asia-Pacific Region',
                'summary' => $companyName . ' announced plans to expand its operations in the Asia-Pacific region, with new offices opening in Singapore and Tokyo.',
                'source' => 'Reuters',
                'publishedAt' => $now->modify('-7 days')->format('Y-m-d H:i:s'),
                'url' => 'https://example.com/business-expansion/' . strtolower($symbol) . '-asia',
                'imageUrl' => 'https://example.com/images/global-expansion.jpg',
            ],
            [
                'title' => $companyName . ' CEO Discusses Future Strategy in Interview',
                'summary' => 'In a recent interview, the CEO of ' . $companyName . ' outlined the company\'s long-term strategy, focusing on innovation, sustainability, and market expansion.',
                'source' => 'CNBC',
                'publishedAt' => $now->modify('-9 days')->format('Y-m-d H:i:s'),
                'url' => 'https://example.com/interviews/' . strtolower($symbol) . '-ceo',
                'imageUrl' => 'https://example.com/images/ceo-interview.jpg',
            ],
        ];
        
        // Return limited number of news items
        return array_slice($newsItems, 0, $limit);
    }
    
    /**
     * Generate mock executive data
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
