<?php

namespace App\Service\ApiClient;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;

/**
 * SEC EDGAR API client for fetching official SEC filings like 10-K reports
 */
class EdgarApiClient extends AbstractApiClient
{
    /**
     * EDGAR API base URL (public data)
     */
    private const EDGAR_BASE_URL = 'https://www.sec.gov/Archives/edgar/data';

    /**
     * Search API URL
     */
    private const EDGAR_SEARCH_URL = 'https://efts.sec.gov/LATEST/search-index';

    /**
     * Company ticker to CIK mapping
     */
    private array $cikMapping = [];

    /**
     * {@inheritdoc}
     */
    protected function initialize(): void
    {
        $this->baseUrl = self::EDGAR_BASE_URL;
        $this->apiKey = $this->params->get('edgar_api.api_key', '');

        // Load CIK mapping from file or create it if it doesn't exist
        $this->initializeCikMapping();
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
            'User-Agent' => 'CompanyResearchTool research@example.com', // SEC requires a valid user agent
            'Accept' => 'application/json',
        ];
    }

    /**
     * Initialize CIK mapping from file or create it
     */
    private function initializeCikMapping(): void
    {
        // Path to CIK mapping file
        $cikFile = __DIR__ . '/../../../var/cik_mapping.json';

        // Check if file exists and isn't too old (refresh weekly)
        if (file_exists($cikFile) && filemtime($cikFile) > (time() - 7 * 24 * 60 * 60)) {
            $this->cikMapping = json_decode(file_get_contents($cikFile), true);
            return;
        }

        // Otherwise, fetch the latest mapping and save it
        try {
            $this->fetchAndSaveCikMapping($cikFile);
        } catch (\Exception $e) {
            $this->logger->error('Could not fetch CIK mapping: ' . $e->getMessage());
            // If file exists but is old, still use it as fallback
            if (file_exists($cikFile)) {
                $this->cikMapping = json_decode(file_get_contents($cikFile), true);
            }
        }
    }

    /**
     * Fetch the CIK mapping from SEC and save it to file
     */
    private function fetchAndSaveCikMapping(string $filename): void
    {
        $url = 'https://www.sec.gov/files/company_tickers.json';
        $client = HttpClient::create();

        $response = $client->request('GET', $url, [
            'headers' => $this->getHeaders()
        ]);

        if ($response->getStatusCode() === 200) {
            $data = $response->toArray();
            $mapping = [];

            // Process the data into a more usable format
            foreach ($data as $entry) {
                $ticker = strtoupper($entry['ticker']);
                $cik = str_pad($entry['cik_str'], 10, '0', STR_PAD_LEFT);
                $name = $entry['title'];

                $mapping[$ticker] = [
                    'cik' => $cik,
                    'name' => $name
                ];
            }

            // Save to file
            file_put_contents($filename, json_encode($mapping));
            $this->cikMapping = $mapping;
        } else {
            throw new \Exception('Failed to fetch CIK mapping: ' . $response->getStatusCode());
        }
    }

    /**
     * Get CIK for a ticker symbol
     */
    public function getCik(string $ticker): ?string
    {
        $ticker = strtoupper($ticker);
        return $this->cikMapping[$ticker]['cik'] ?? null;
    }

    /**
     * Search for filings
     *
     * @param string $query Search query
     * @param array $filters Additional filters
     * @return array Search results
     */
    public function searchFilings(string $query, array $filters = []): array
    {
        // Build search query
        $searchQuery = [
            'q' => $query,
            'dateRange' => $filters['dateRange'] ?? 'custom',
            'startdt' => $filters['startDate'] ?? date('Y-m-d', strtotime('-1 year')),
            'enddt' => $filters['endDate'] ?? date('Y-m-d'),
            'category' => $filters['category'] ?? 'form-cat1',
            'locationType' => 'located',
            'forms' => $filters['forms'] ?? ['10-K', '10-Q'],
            'page' => $filters['page'] ?? 1
        ];

        // Make the API request
        try {
            $response = $this->request('POST', self::EDGAR_SEARCH_URL, [], ['json' => $searchQuery]);
            return $response;
        } catch (\Exception $e) {
            $this->logger->error('SEC EDGAR search error: ' . $e->getMessage());
            // Return mock data for development
            return $this->getMockFilingSearch($query, $filters);
        }
    }

    /**
     * Get 10-K reports for a company
     *
     * @param string $ticker Company ticker symbol
     * @param int $limit Maximum number of reports to return
     * @return array 10-K filings
     */
    public function get10KReports(string $ticker, int $limit = 5): array
    {
        // Get CIK
        $cik = $this->getCik($ticker);
        if (!$cik) {
            $this->logger->warning('CIK not found for ticker: ' . $ticker);
            return $this->getMock10KReports($ticker, $limit);
        }

        // Search for 10-K filings
        $query = "cik:" . preg_replace('/^0+/', '', $cik) . " AND formType:\"10-K\"";
        $filters = [
            'forms' => ['10-K'],
            'limit' => $limit
        ];

        try {
            $searchResults = $this->searchFilings($query, $filters);

            // If successful, process the results
            if (!empty($searchResults) && isset($searchResults['hits'])) {
                return $this->processFilingResults($searchResults['hits']['hits']);
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to get 10-K reports: ' . $e->getMessage());
        }

        // Return mock data if no results or error
        return $this->getMock10KReports($ticker, $limit);
    }

    /**
     * Process raw filing search results into a standardized format
     *
     * @param array $hits Raw search results
     * @return array Processed filing data
     */
    private function processFilingResults(array $hits): array
    {
        $results = [];

        foreach ($hits as $hit) {
            $filing = $hit['_source'];

            $results[] = [
                'id' => $filing['adsh'] ?? $hit['_id'],
                'cik' => $filing['cik'] ?? '',
                'companyName' => $filing['companyName'] ?? '',
                'formType' => $filing['formType'] ?? '',
                'filingDate' => $filing['filingDate'] ?? '',
                'reportDate' => $filing['reportDate'] ?? $filing['periodOfReport'] ?? '',
                'accessionNumber' => $filing['accessionNumber'] ?? '',
                'fileNumber' => $filing['fileNumber'] ?? '',
                'documentUrl' => $this->getDocumentUrl($filing),
                'htmlUrl' => $this->getHtmlUrl($filing),
                'textUrl' => $this->getTextUrl($filing),
                'description' => $filing['formDescription'] ?? '',
            ];
        }

        return $results;
    }

    /**
     * Get document URL for a filing
     *
     * @param array $filing Filing data
     * @return string Document URL
     */
    private function getDocumentUrl(array $filing): string
    {
        $cik = str_pad(isset($filing['cik']) ? $filing['cik'] : '', 10, '0', STR_PAD_LEFT);
        $accession = str_replace('-', '', isset($filing['accessionNumber']) ? $filing['accessionNumber'] : '');

        if (empty($cik) || empty($accession)) {
            return '';
        }

        $primaryDoc = isset($filing['primaryDocument']) ? $filing['primaryDocument'] : 'primary-document.xml';
        return "https://www.sec.gov/Archives/edgar/data/{$cik}/{$accession}/{$primaryDoc}";
    }

    /**
     * Get HTML URL for a filing
     *
     * @param array $filing Filing data
     * @return string HTML URL
     */
    private function getHtmlUrl(array $filing): string
    {
        $cik = str_pad(isset($filing['cik']) ? $filing['cik'] : '', 10, '0', STR_PAD_LEFT);
        $accession = str_replace('-', '', isset($filing['accessionNumber']) ? $filing['accessionNumber'] : '');

        if (empty($cik) || empty($accession)) {
            return '';
        }

        $primaryDoc = isset($filing['primaryDocument']) ? $filing['primaryDocument'] : '';
        return "https://www.sec.gov/Archives/edgar/data/{$cik}/{$accession}/{$primaryDoc}";
    }

    /**
     * Get text URL for a filing
     *
     * @param array $filing Filing data
     * @return string Text URL
     */
    private function getTextUrl(array $filing): string
    {
        $cik = str_pad(isset($filing['cik']) ? $filing['cik'] : '', 10, '0', STR_PAD_LEFT);
        $accession = str_replace('-', '', isset($filing['accessionNumber']) ? $filing['accessionNumber'] : '');

        if (empty($cik) || empty($accession)) {
            return '';
        }

        return "https://www.sec.gov/Archives/edgar/data/{$cik}/{$accession}/{$accession}.txt";
    }

    /**
     * Download a 10-K report
     *
     * @param string $url Report URL
     * @param string $format Desired format (html, text, raw)
     * @return string Report content
     */
    public function downloadReport(string $url, string $format = 'html'): string
    {
        try {
            $response = $this->requestRaw('GET', $url);
            return $response;
        } catch (\Exception $e) {
            $this->logger->error('Failed to download report: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Make a raw HTTP request (for file downloads)
     *
     * @param string $method HTTP method
     * @param string $url Full URL
     * @param array $params Query parameters
     * @param array $options Additional options
     * @return string Response content
     */
    protected function requestRaw(string $method, string $url, array $params = [], array $options = []): string
    {
        // Wait for a second to comply with SEC's fair access policy
        sleep(1);

        $client = HttpClient::create();

        $requestOptions = [
            'headers' => $this->getHeaders(),
        ];

        if (!empty($params)) {
            $requestOptions['query'] = $params;
        }

        if (!empty($options)) {
            $requestOptions = array_merge($requestOptions, $options);
        }

        try {
            $response = $client->request($method, $url, $requestOptions);
            return $response->getContent();
        } catch (\Exception $e) {
            $this->logger->error('API request error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Extract sections from a 10-K report
     *
     * @param string $content Report content
     * @return array Extracted sections
     */
    public function extractReportSections(string $content): array
    {
        // This is a simplified approach - a real implementation would use more sophisticated parsing
        $sections = [
            'item1' => $this->extractSection($content, 'Item 1.', 'Item 1A.', 'Business'),
            'item1a' => $this->extractSection($content, 'Item 1A.', 'Item 1B.', 'Risk Factors'),
            'item7' => $this->extractSection($content, 'Item 7.', 'Item 7A.', 'Management\'s Discussion and Analysis'),
            'item8' => $this->extractSection($content, 'Item 8.', 'Item 9.', 'Financial Statements and Supplementary Data'),
        ];

        return $sections;
    }

    /**
     * Extract a section from report content
     *
     * @param string $content Full report content
     * @param string $startMarker Section start marker
     * @param string $endMarker Section end marker
     * @param string $fallbackTitle Fallback title for searching
     * @return string Extracted section content
     */
    private function extractSection(string $content, string $startMarker, string $endMarker, string $fallbackTitle): string
    {
        // Try to find the section using the markers
        $pattern = '/' . preg_quote($startMarker, '/') . '.*?(?=' . preg_quote($endMarker, '/') . ')/s';
        if (preg_match($pattern, $content, $matches)) {
            return $matches[0];
        }

        // If that fails, try using the title
        $pattern = '/' . preg_quote($fallbackTitle, '/') . '.{0,100}.*?(?=' . preg_quote($endMarker, '/') . ')/s';
        if (preg_match($pattern, $content, $matches)) {
            return $matches[0];
        }

        // Return an empty string if section not found
        return '';
    }

    /**
     * Summarize a report section using Neuron AI
     *
     * @param string $sectionContent Section content
     * @param string $sectionTitle Section title
     * @return string Summary
     */
    public function summarizeSection(string $sectionContent, string $sectionTitle): string
    {
        // This would normally call the Neuron AI service
        // For now, return a placeholder
        return "Summary of {$sectionTitle} would be generated by Neuron AI.";
    }

    /**
     * {@inheritdoc}
     */
    protected function getMockData(string $endpoint, array $params): array
    {
        // Not directly applicable for EDGAR API
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function searchCompanies(string $term): array
    {
        // For EDGAR, we'll search by company name in the CIK mapping
        $results = [];
        $term = strtoupper($term);

        foreach ($this->cikMapping as $ticker => $company) {
            if (strpos($ticker, $term) !== false || strpos(strtoupper($company['name']), $term) !== false) {
                $results[] = [
                    'symbol' => $ticker,
                    'name' => $company['name'],
                    'cik' => $company['cik'],
                    'exchange' => 'US',  // Default value
                ];
            }

            // Limit to reasonable number of results
            if (count($results) >= 10) {
                break;
            }
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function getCompanyProfile(string $symbol): array
    {
        $symbol = strtoupper($symbol);
        $cik = $this->getCik($symbol);

        if (!$cik) {
            $this->logger->warning('CIK not found for ticker: ' . $symbol);
            return [
                'symbol' => $symbol,
                'name' => 'Unknown Company',
                'exchange' => 'US',
                'cik' => '',
                'industry' => '',
                'sector' => '',
                'description' => 'Company information not available',
                'website' => '',
                'employees' => 0,
                'address' => '',
                'phone' => '',
            ];
        }

        // In a real implementation, we would search SEC data for company info
        // For now, return mock data
        return [
            'symbol' => $symbol,
            'name' => $this->cikMapping[$symbol]['name'] ?? 'Unknown Company',
            'exchange' => 'US',
            'cik' => $cik,
            'industry' => 'Not available from SEC EDGAR',
            'sector' => 'Not available from SEC EDGAR',
            'description' => 'Company description would be extracted from 10-K filings',
            'website' => '',
            'employees' => 0,
            'address' => '',
            'phone' => '',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getQuote(string $symbol): array
    {
        // SEC EDGAR doesn't provide real-time quotes
        // This would normally be provided by a market data API
        return [
            'symbol' => $symbol,
            'price' => 0.0,
            'change' => 0.0,
            'changePercent' => 0.0,
            'volume' => 0,
            'previousClose' => 0.0,
            'open' => 0.0,
            'high' => 0.0,
            'low' => 0.0,
            'marketCap' => 0.0,
            'pe' => 0.0,
            'dividend' => 0.0,
            'dividendYield' => 0.0,
            'updated' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getFinancials(string $symbol, string $period = 'quarterly'): array
    {
        // Get the latest 10-K/10-Q reports and extract financial data
        $symbol = strtoupper($symbol);
        $cik = $this->getCik($symbol);

        if (!$cik) {
            return [];
        }

        $formType = $period === 'annual' ? '10-K' : '10-Q';
        $filings = $this->searchFilings("cik:{$cik} AND formType:\"{$formType}\"", [
            'forms' => [$formType],
            'limit' => 4  // Get last 4 quarters or years
        ]);

        // In a real implementation, we would extract financial data from filings
        // For now, return empty array
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getCompanyNews(string $symbol, int $limit = 5): array
    {
        // SEC EDGAR doesn't provide news articles
        // This would normally come from a news API
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getExecutives(string $symbol): array
    {
        // This data would normally be extracted from DEF 14A (proxy statement) filings
        // For now, return empty array
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getHistoricalPrices(string $symbol, string $interval = 'daily', string $outputSize = 'compact'): array
    {
        // SEC EDGAR doesn't provide historical price data
        // This would normally come from a market data API
        return [];
    }

    /**
     * Generate mock filing search results
     *
     * @param string $query Search query
     * @param array $filters Search filters
     * @return array Mock search results
     */
    private function getMockFilingSearch(string $query, array $filters): array
    {
        // Extract ticker from query if possible
        preg_match('/cik:(\d+)/', $query, $matches);
        $cik = $matches[1] ?? '320193'; // Default to Apple's CIK

        // Extract form types
        $formTypes = $filters['forms'] ?? ['10-K', '10-Q'];
        $limit = $filters['limit'] ?? 5;

        $hits = [];
        $now = time();
        $year = 365 * 24 * 60 * 60;

        for ($i = 0; $i < $limit; $i++) {
            $formType = in_array('10-K', $formTypes) ? '10-K' : $formTypes[0];
            $filingDate = date('Y-m-d', $now - ($i * $year));
            $reportDate = date('Y-m-d', $now - ($i * $year) - (90 * 24 * 60 * 60));
            $accessionNumber = date('Y', $now - ($i * $year)) . '0101-' . mt_rand(10000, 99999);

            $hits[] = [
                '_id' => 'mock-' . mt_rand(1000000, 9999999),
                '_source' => [
                    'cik' => $cik,
                    'companyName' => $this->getCompanyNameFromCik($cik),
                    'formType' => $formType,
                    'filingDate' => $filingDate,
                    'reportDate' => $reportDate,
                    'accessionNumber' => $accessionNumber,
                    'fileNumber' => '000-00000',
                    'primaryDocument' => 'primary-document.htm',
                    'formDescription' => "Annual Report for fiscal year ending {$reportDate}"
                ]
            ];
        }

        return [
            'hits' => [
                'total' => [
                    'value' => count($hits),
                ],
                'hits' => $hits
            ]
        ];
    }

    /**
     * Get company name from CIK (for mock data)
     */
    private function getCompanyNameFromCik(string $cik): string
    {
        $companies = [
            '320193' => 'Apple Inc.',
            '789019' => 'Microsoft Corporation',
            '1652044' => 'Alphabet Inc.',
            '1018724' => 'Amazon.com, Inc.',
            '1326801' => 'Meta Platforms, Inc.',
            '1341439' => 'Tesla, Inc.',
        ];

        return $companies[$cik] ?? 'Example Corporation';
    }

    /**
     * Generate mock 10-K reports for a company
     *
     * @param string $ticker Company ticker symbol
     * @param int $limit Maximum number of reports
     * @return array Mock 10-K reports
     */
    private function getMock10KReports(string $ticker, int $limit): array
    {
        $ticker = strtoupper($ticker);
        $companyMapping = [
            'AAPL' => ['name' => 'Apple Inc.', 'cik' => '0000320193'],
            'MSFT' => ['name' => 'Microsoft Corporation', 'cik' => '0000789019'],
            'GOOGL' => ['name' => 'Alphabet Inc.', 'cik' => '0001652044'],
            'AMZN' => ['name' => 'Amazon.com, Inc.', 'cik' => '0001018724'],
            'META' => ['name' => 'Meta Platforms, Inc.', 'cik' => '0001326801'],
            'TSLA' => ['name' => 'Tesla, Inc.', 'cik' => '0001341439'],
        ];

        $companyInfo = $companyMapping[$ticker] ?? ['name' => 'Example Corporation', 'cik' => '0000000000'];
        $cik = str_replace('0000', '', $companyInfo['cik']);

        $reports = [];
        $now = time();
        $year = 365 * 24 * 60 * 60;

        for ($i = 0; $i < $limit; $i++) {
            $filingDate = date('Y-m-d', $now - ($i * $year));
            $reportDate = date('Y-m-d', $now - ($i * $year) - (60 * 24 * 60 * 60));
            $fiscalYear = date('Y', $now - ($i * $year));
            $accessionNumber = $fiscalYear . '0101-' . mt_rand(10000, 99999);
            $accessionNumberClean = str_replace('-', '', $accessionNumber);

            $reports[] = [
                'id' => 'mock-10k-' . $ticker . '-' . $fiscalYear,
                'cik' => $cik,
                'companyName' => $companyInfo['name'],
                'formType' => '10-K',
                'filingDate' => $filingDate,
                'reportDate' => $reportDate,
                'fiscalYear' => $fiscalYear,
                'accessionNumber' => $accessionNumber,
                'fileNumber' => '001-' . mt_rand(10000, 99999),
                'documentUrl' => "https://www.sec.gov/Archives/edgar/data/{$companyInfo['cik']}/{$accessionNumberClean}/{$accessionNumber}-index.htm",
                'htmlUrl' => "https://www.sec.gov/Archives/edgar/data/{$companyInfo['cik']}/{$accessionNumberClean}/{$accessionNumber}-index.htm",
                'textUrl' => "https://www.sec.gov/Archives/edgar/data/{$companyInfo['cik']}/{$accessionNumberClean}/{$accessionNumberClean}.txt",
                'description' => "Annual Report for {$companyInfo['name']} - Fiscal Year ending {$reportDate}",
            ];
        }

        return $reports;
    }
}
