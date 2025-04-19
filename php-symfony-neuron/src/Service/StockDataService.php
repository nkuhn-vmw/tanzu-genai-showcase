<?php

namespace App\Service;

use App\Entity\Company;
use App\Entity\ExecutiveProfile;
use App\Entity\FinancialData;
use App\Service\ApiClient\AlphaVantageClient;
use App\Service\ApiClient\NewsApiClient;
use App\Service\ApiClient\SecApiClient;
use App\Service\ApiClient\YahooFinanceClient;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;

/**
 * Service for fetching stock and company data from external APIs
 */
class StockDataService
{
    private AlphaVantageClient $alphaVantageClient;
    private YahooFinanceClient $yahooFinanceClient;
    private NewsApiClient $newsApiClient;
    private SecApiClient $secApiClient;
    private EntityManagerInterface $entityManager;
    private AdapterInterface $cache;
    private LoggerInterface $logger;
    
    /**
     * Constructor
     */
    public function __construct(
        AlphaVantageClient $alphaVantageClient,
        YahooFinanceClient $yahooFinanceClient,
        NewsApiClient $newsApiClient,
        SecApiClient $secApiClient,
        EntityManagerInterface $entityManager,
        AdapterInterface $cache,
        LoggerInterface $logger
    ) {
        $this->alphaVantageClient = $alphaVantageClient;
        $this->yahooFinanceClient = $yahooFinanceClient;
        $this->newsApiClient = $newsApiClient;
        $this->secApiClient = $secApiClient;
        $this->entityManager = $entityManager;
        $this->cache = $cache;
        $this->logger = $logger;
    }
    
    /**
     * Search for companies by name or ticker symbol
     *
     * @param string $term Search term
     * @return array Search results
     */
    public function searchCompanies(string $term): array
    {
        // Try to get from cache first
        $cacheKey = 'company_search_' . md5($term);
        $cacheItem = $this->cache->getItem($cacheKey);
        
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }
        
        // Otherwise, search APIs with fallback
        try {
            // First try Alpha Vantage
            $results = $this->alphaVantageClient->searchCompanies($term);
            
            if (empty($results)) {
                // If no results, try Yahoo Finance
                $this->logger->info('No results from Alpha Vantage, trying Yahoo Finance');
                $results = $this->yahooFinanceClient->searchCompanies($term);
            }
            
            // Cache results for 1 hour
            $cacheItem->set($results);
            $cacheItem->expiresAfter(3600);
            $this->cache->save($cacheItem);
            
            return $results;
        } catch (\Exception $e) {
            $this->logger->error('Error searching companies: ' . $e->getMessage());
            
            // Try Yahoo Finance as fallback
            try {
                $results = $this->yahooFinanceClient->searchCompanies($term);
                
                // Cache results
                $cacheItem->set($results);
                $cacheItem->expiresAfter(3600);
                $this->cache->save($cacheItem);
                
                return $results;
            } catch (\Exception $e) {
                $this->logger->error('Error with fallback search: ' . $e->getMessage());
                return [];
            }
        }
    }
    
    /**
     * Get a company profile by ticker symbol
     *
     * @param string $symbol Ticker symbol
     * @return array|null Company profile data
     */
    public function getCompanyProfile(string $symbol): ?array
    {
        // Try to get from cache first
        $cacheKey = 'company_profile_' . $symbol;
        $cacheItem = $this->cache->getItem($cacheKey);
        
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }
        
        // Otherwise, get from APIs with fallback
        try {
            // First try Alpha Vantage
            $profile = $this->alphaVantageClient->getCompanyProfile($symbol);
            
            if (empty($profile['name'])) {
                // If no valid profile, try Yahoo Finance
                $this->logger->info('No valid profile from Alpha Vantage, trying Yahoo Finance');
                $profile = $this->yahooFinanceClient->getCompanyProfile($symbol);
            }
            
            // Cache results for 24 hours
            $cacheItem->set($profile);
            $cacheItem->expiresAfter(86400);
            $this->cache->save($cacheItem);
            
            return $profile;
        } catch (\Exception $e) {
            $this->logger->error('Error getting company profile: ' . $e->getMessage());
            
            // Try Yahoo Finance as fallback
            try {
                $profile = $this->yahooFinanceClient->getCompanyProfile($symbol);
                
                // Cache results
                $cacheItem->set($profile);
                $cacheItem->expiresAfter(86400);
                $this->cache->save($cacheItem);
                
                return $profile;
            } catch (\Exception $e) {
                $this->logger->error('Error with fallback profile: ' . $e->getMessage());
                return null;
            }
        }
    }
    
    /**
     * Get current stock quote
     *
     * @param string $symbol Ticker symbol
     * @return array|null Quote data
     */
    public function getStockQuote(string $symbol): ?array
    {
        // Try to get from cache first (shorter TTL for quotes - 5 minutes)
        $cacheKey = 'stock_quote_' . $symbol;
        $cacheItem = $this->cache->getItem($cacheKey);
        
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }
        
        // Otherwise, get from APIs with fallback
        try {
            // First try Alpha Vantage
            $quote = $this->alphaVantageClient->getQuote($symbol);
            
            if (empty($quote['price'])) {
                // If no valid quote, try Yahoo Finance
                $this->logger->info('No valid quote from Alpha Vantage, trying Yahoo Finance');
                $quote = $this->yahooFinanceClient->getQuote($symbol);
            }
            
            // Cache results for 5 minutes
            $cacheItem->set($quote);
            $cacheItem->expiresAfter(300);
            $this->cache->save($cacheItem);
            
            return $quote;
        } catch (\Exception $e) {
            $this->logger->error('Error getting stock quote: ' . $e->getMessage());
            
            // Try Yahoo Finance as fallback
            try {
                $quote = $this->yahooFinanceClient->getQuote($symbol);
                
                // Cache results
                $cacheItem->set($quote);
                $cacheItem->expiresAfter(300);
                $this->cache->save($cacheItem);
                
                return $quote;
            } catch (\Exception $e) {
                $this->logger->error('Error with fallback quote: ' . $e->getMessage());
                return null;
            }
        }
    }
    
    /**
     * Get financial data
     *
     * @param string $symbol Ticker symbol
     * @param string $period Period (quarterly, annual)
     * @return array Financial data
     */
    public function getFinancialData(string $symbol, string $period = 'quarterly'): array
    {
        // Try to get from cache first
        $cacheKey = 'financial_data_' . $symbol . '_' . $period;
        $cacheItem = $this->cache->getItem($cacheKey);
        
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }
        
        // Otherwise, get from APIs with fallback
        try {
            // First try Alpha Vantage
            $financials = $this->alphaVantageClient->getFinancials($symbol, $period);
            
            if (empty($financials)) {
                // If no valid data, try Yahoo Finance
                $this->logger->info('No valid financial data from Alpha Vantage, trying Yahoo Finance');
                $financials = $this->yahooFinanceClient->getFinancials($symbol, $period);
            }
            
            // Cache results for 24 hours
            $cacheItem->set($financials);
            $cacheItem->expiresAfter(86400);
            $this->cache->save($cacheItem);
            
            return $financials;
        } catch (\Exception $e) {
            $this->logger->error('Error getting financial data: ' . $e->getMessage());
            
            // Try Yahoo Finance as fallback
            try {
                $financials = $this->yahooFinanceClient->getFinancials($symbol, $period);
                
                // Cache results
                $cacheItem->set($financials);
                $cacheItem->expiresAfter(86400);
                $this->cache->save($cacheItem);
                
                return $financials;
            } catch (\Exception $e) {
                $this->logger->error('Error with fallback financials: ' . $e->getMessage());
                return [];
            }
        }
    }
    
    /**
     * Get company news
     *
     * @param string $symbol Ticker symbol
     * @param int $limit Number of news items to return
     * @return array News items
     */
    public function getCompanyNews(string $symbol, int $limit = 5): array
    {
        // Try to get from cache first
        $cacheKey = 'company_news_' . $symbol . '_' . $limit;
        $cacheItem = $this->cache->getItem($cacheKey);
        
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }
        
        // First try NewsAPI (dedicated news service)
        try {
            // Get company information to improve search
            $companyInfo = $this->getCompanyProfile($symbol);
            $companyName = $companyInfo['name'] ?? '';
            
            // Create search term with both symbol and name if available
            $searchTerm = $symbol;
            if (!empty($companyName)) {
                $searchTerm = $symbol . ' ' . $companyName;
            }
            
            $news = $this->newsApiClient->getCompanyNews($searchTerm, null, null, $limit);
            
            // Cache results for 1 hour
            $cacheItem->set($news);
            $cacheItem->expiresAfter(3600);
            $this->cache->save($cacheItem);
            
            return $news;
        } catch (\Exception $e) {
            $this->logger->error('Error getting company news from NewsAPI: ' . $e->getMessage());
            
            // First fallback: Yahoo Finance
            try {
                $news = $this->yahooFinanceClient->getCompanyNews($symbol, $limit);
                
                // Cache results
                $cacheItem->set($news);
                $cacheItem->expiresAfter(3600);
                $this->cache->save($cacheItem);
                
                return $news;
            } catch (\Exception $e) {
                $this->logger->error('Error with Yahoo Finance news fallback: ' . $e->getMessage());
                
                // Second fallback: Alpha Vantage
                try {
                    $news = $this->alphaVantageClient->getCompanyNews($symbol, $limit);
                    
                    // Cache results
                    $cacheItem->set($news);
                    $cacheItem->expiresAfter(3600);
                    $this->cache->save($cacheItem);
                    
                    return $news;
                } catch (\Exception $e) {
                    $this->logger->error('Error with all news sources: ' . $e->getMessage());
                    return [];
                }
            }
        }
    }
    
    /**
     * Get company executives
     *
     * @param string $symbol Ticker symbol
     * @return array Executive data
     */
    public function getExecutives(string $symbol): array
    {
        // Try to get from cache first
        $cacheKey = 'company_executives_' . $symbol;
        $cacheItem = $this->cache->getItem($cacheKey);
        
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }
        
        // Yahoo Finance often has more executive info, so use it as primary
        try {
            $executives = $this->yahooFinanceClient->getExecutives($symbol);
            
            // Cache results for 24 hours
            $cacheItem->set($executives);
            $cacheItem->expiresAfter(86400);
            $this->cache->save($cacheItem);
            
            return $executives;
        } catch (\Exception $e) {
            $this->logger->error('Error getting company executives: ' . $e->getMessage());
            
            // Fallback to Alpha Vantage
            try {
                $executives = $this->alphaVantageClient->getExecutives($symbol);
                
                // Cache results
                $cacheItem->set($executives);
                $cacheItem->expiresAfter(86400);
                $this->cache->save($cacheItem);
                
                return $executives;
            } catch (\Exception $e) {
                $this->logger->error('Error with fallback executives: ' . $e->getMessage());
                return [];
            }
        }
    }
    
    /**
     * Get historical stock prices
     *
     * @param string $symbol Ticker symbol
     * @param string $interval Time interval (daily, weekly, monthly)
     * @param string $outputSize Size of time series (compact = 100 data points, full = 20+ years)
     * @return array Historical price data
     */
    public function getHistoricalPrices(string $symbol, string $interval = 'daily', string $outputSize = 'compact'): array
    {
        // Try to get from cache first
        $cacheKey = 'historical_prices_' . $symbol . '_' . $interval . '_' . $outputSize;
        $cacheItem = $this->cache->getItem($cacheKey);
        
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }
        
        // Alpha Vantage has good historical data, try it first
        try {
            $prices = $this->alphaVantageClient->getHistoricalPrices($symbol, $interval, $outputSize);
            
            if (empty($prices)) {
                // If no valid data, try Yahoo Finance
                $this->logger->info('No valid historical price data from Alpha Vantage, trying Yahoo Finance');
                $prices = $this->yahooFinanceClient->getHistoricalPrices($symbol, $interval, $outputSize);
            }
            
            // Cache TTL depends on the interval
            $cacheTtl = match($interval) {
                'daily' => 3600, // 1 hour for daily data (more frequently updated)
                'weekly' => 86400, // 1 day for weekly data
                'monthly' => 86400 * 3, // 3 days for monthly data
                default => 3600,
            };
            
            // Cache results
            $cacheItem->set($prices);
            $cacheItem->expiresAfter($cacheTtl);
            $this->cache->save($cacheItem);
            
            return $prices;
        } catch (\Exception $e) {
            $this->logger->error('Error getting historical prices: ' . $e->getMessage());
            
            // Fallback to Yahoo Finance
            try {
                $prices = $this->yahooFinanceClient->getHistoricalPrices($symbol, $interval, $outputSize);
                
                // Cache results
                $cacheItem->set($prices);
                $cacheItem->expiresAfter(3600); // 1 hour
                $this->cache->save($cacheItem);
                
                return $prices;
            } catch (\Exception $e) {
                $this->logger->error('Error with fallback prices: ' . $e->getMessage());
                return [];
            }
        }
    }
    
    /**
     * Import a company and its data into the database
     *
     * @param string $symbol Ticker symbol
     * @return Company The imported company
     * @throws \Exception If import fails
     */
    public function importCompany(string $symbol): Company
    {
        // Get company profile
        $profile = $this->getCompanyProfile($symbol);
        if (!$profile) {
            throw new \Exception('Could not find company profile for ' . $symbol);
        }
        
        // Check if company already exists
        $existingCompany = $this->entityManager->getRepository(Company::class)
            ->findOneBy(['tickerSymbol' => $symbol]);
        
        if ($existingCompany) {
            $this->logger->info('Company already exists, updating: ' . $symbol);
            $company = $existingCompany;
        } else {
            $this->logger->info('Creating new company: ' . $symbol);
            $company = new Company();
            $company->setTickerSymbol($symbol);
            $company->setCreatedAt(new \DateTimeImmutable());
        }
        
        // Update company data
        $company->setName($profile['name']);
        $company->setDescription($profile['description']);
        $company->setSector($profile['sector']);
        $company->setIndustry($profile['industry']);
        $company->setHeadquarters($profile['address'] . ', ' . 
                                 $profile['city'] . ', ' . 
                                 $profile['state'] . ', ' . 
                                 $profile['country']);
        $company->setWebsite($profile['website']);
        $company->setUpdatedAt(new \DateTimeImmutable());
        
        // Import financial data
        $this->importFinancialData($company);
        
        // Import executive profiles
        $this->importExecutiveProfiles($company);
        
        // Import historical prices (last 100 days)
        $this->importHistoricalPrices($company, 'daily', 100);
        
        // Also import weekly prices for longer trends (last 52 weeks)
        $this->importHistoricalPrices($company, 'weekly', 52);
        
        // Persist the company
        $this->entityManager->persist($company);
        $this->entityManager->flush();
        
        return $company;
    }
    
    /**
     * Import financial data for a company
     *
     * @param Company $company The company to import data for
     * @return void
     */
    public function importFinancialData(Company $company): void
    {
        $financials = $this->getFinancialData($company->getTickerSymbol());
        if (empty($financials)) {
            $this->logger->warning('No financial data available for ' . $company->getTickerSymbol());
            return;
        }
        
        // Get current stock quote
        $quote = $this->getStockQuote($company->getTickerSymbol());
        
        // Get existing financial data periods to avoid duplicates
        $existingPeriods = [];
        foreach ($company->getFinancialData() as $data) {
            $key = $data->getFiscalQuarter() . '-' . $data->getFiscalYear();
            $existingPeriods[$key] = $data;
        }
        
        foreach ($financials as $financial) {
            $quarter = $financial['fiscalQuarter'];
            $year = $financial['fiscalYear'];
            $key = $quarter . '-' . $year;
            
            if (isset($existingPeriods[$key])) {
                // Update existing entry
                $data = $existingPeriods[$key];
                $this->logger->info('Updating financial data for ' . $company->getTickerSymbol() . ': ' . $key);
            } else {
                // Create new entry
                $data = new FinancialData();
                $data->setCompany($company);
                $data->setFiscalQuarter($quarter);
                $data->setFiscalYear($year);
                $data->setCreatedAt(new \DateTimeImmutable());
                $this->logger->info('Creating financial data for ' . $company->getTickerSymbol() . ': ' . $key);
            }
            
            // Set or update data
            $data->setReportType('Income Statement');
            
            if (isset($financial['fiscalDate'])) {
                $data->setReportDate(new \DateTime($financial['fiscalDate']));
            }
            
            $data->setRevenue($financial['revenue'] ?? 0);
            $data->setNetIncome($financial['netIncome'] ?? 0);
            $data->setEps($financial['eps'] ?? 0);
            $data->setEbitda($financial['ebitda'] ?? 0);
            
            $data->setCostOfRevenue($financial['costOfRevenue'] ?? 0);
            $data->setGrossProfit($financial['grossProfit'] ?? 0);
            $data->setOperatingIncome($financial['operatingIncome'] ?? 0);
            $data->setOperatingExpenses($financial['operatingExpenses'] ?? 0);
            $data->setResearchAndDevelopment($financial['researchAndDevelopment'] ?? 0);
            
            // Calculate profit margin if not provided
            if (!isset($financial['profitMargin']) && $financial['revenue'] > 0) {
                $profitMargin = $financial['netIncome'] / $financial['revenue'];
                $data->setProfitMargin($profitMargin);
            } else {
                $data->setProfitMargin($financial['profitMargin'] ?? 0);
            }
            
            // Add quote data to the most recent quarter
            if ($quote && $key === $financials[0]['fiscalQuarter'] . '-' . $financials[0]['fiscalYear']) {
                $data->setMarketCap($quote['marketCap'] ?? 0);
                
                // Set PE ratio if available and EPS is not zero
                if (isset($quote['price']) && $data->getEps() != 0) {
                    $data->setPeRatio($quote['price'] / $data->getEps());
                } else {
                    $data->setPeRatio(0);
                }
            }
            
            $this->entityManager->persist($data);
        }
    }
    
    /**
     * Import executive profiles for a company
     *
     * @param Company $company The company to import executives for
     * @return void
     */
    public function importExecutiveProfiles(Company $company): void
    {
        $executives = $this->getExecutives($company->getTickerSymbol());
        if (empty($executives)) {
            $this->logger->warning('No executive data available for ' . $company->getTickerSymbol());
            return;
        }
        
        // Get existing executives to avoid duplicates
        $existingExecutives = [];
        foreach ($company->getExecutiveProfiles() as $profile) {
            $existingExecutives[$profile->getName()] = $profile;
        }
        
        foreach ($executives as $executive) {
            if (isset($existingExecutives[$executive['name']])) {
                // Update existing entry
                $profile = $existingExecutives[$executive['name']];
                $this->logger->info('Updating executive profile for ' . $executive['name']);
            } else {
                // Create new entry
                $profile = new ExecutiveProfile();
                $profile->setCompany($company);
                $profile->setName($executive['name']);
                $profile->setCreatedAt(new \DateTimeImmutable());
                $this->logger->info('Creating executive profile for ' . $executive['name']);
            }
            
            // Set or update data
            $profile->setTitle($executive['title']);
            $profile->setBio($executive['bio'] ?? '');
            $profile->setEducation($executive['education'] ?? '');
            $profile->setPreviousCompanies($executive['previousCompanies'] ?? '');
            $profile->setAchievements($executive['achievements'] ?? '');
            
            if (isset($executive['yearJoined'])) {
                $profile->setStartYear($executive['yearJoined']);
            }
            
            if (isset($executive['photoUrl'])) {
                $profile->setPhotoUrl($executive['photoUrl']);
            }
            
            $profile->setUpdatedAt(new \DateTimeImmutable());
            
            $this->entityManager->persist($profile);
        }
    }

    /**
     * Get analyst ratings
     *
     * @param string $symbol Ticker symbol
     * @return array Analyst rating data
     */
    public function getAnalystRatings(string $symbol): array
    {
        // Try to get from cache first
        $cacheKey = 'analyst_ratings_' . $symbol;
        $cacheItem = $this->cache->getItem($cacheKey);
        
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }
        
        // Get ratings from SEC API client
        try {
            $ratings = $this->secApiClient->getAnalystRatings($symbol);
            
            // Cache results for 6 hours (ratings often updated throughout day)
            $cacheItem->set($ratings);
            $cacheItem->expiresAfter(21600);
            $this->cache->save($cacheItem);
            
            return $ratings;
        } catch (\Exception $e) {
            $this->logger->error('Error getting analyst ratings: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get insider trading data
     *
     * @param string $symbol Ticker symbol
     * @param int $limit Maximum number of transactions to return
     * @return array Insider trading data
     */
    public function getInsiderTrading(string $symbol, int $limit = 20): array
    {
        // Try to get from cache first
        $cacheKey = 'insider_trading_' . $symbol . '_' . $limit;
        $cacheItem = $this->cache->getItem($cacheKey);
        
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }
        
        // Get insider trading data from SEC API client
        try {
            $insiderData = $this->secApiClient->getInsiderTrading($symbol, $limit);
            
            // Cache results for 1 day (insider filings don't change that often)
            $cacheItem->set($insiderData);
            $cacheItem->expiresAfter(86400);
            $this->cache->save($cacheItem);
            
            return $insiderData;
        } catch (\Exception $e) {
            $this->logger->error('Error getting insider trading data: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get institutional ownership data
     *
     * @param string $symbol Ticker symbol
     * @param int $limit Maximum number of institutions to return
     * @return array Institutional ownership data
     */
    public function getInstitutionalOwnership(string $symbol, int $limit = 20): array
    {
        // Try to get from cache first
        $cacheKey = 'institutional_ownership_' . $symbol . '_' . $limit;
        $cacheItem = $this->cache->getItem($cacheKey);
        
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }
        
        // Get institutional ownership data from SEC API client
        try {
            $ownershipData = $this->secApiClient->getInstitutionalOwnership($symbol, $limit);
            
            // Cache results for 1 week (13F filings quarterly)
            $cacheItem->set($ownershipData);
            $cacheItem->expiresAfter(604800);
            $this->cache->save($cacheItem);
            
            return $ownershipData;
        } catch (\Exception $e) {
            $this->logger->error('Error getting institutional ownership data: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Calculate consensus data from analyst ratings
     *
     * @param string $symbol Ticker symbol
     * @return array Consensus data
     */
    public function getAnalystConsensus(string $symbol): array
    {
        $ratingsData = $this->getAnalystRatings($symbol);
        
        if (empty($ratingsData) || empty($ratingsData['ratings'])) {
            return [
                'symbol' => $symbol,
                'consensus' => 'N/A',
                'averagePriceTarget' => 0,
                'lowPriceTarget' => 0,
                'highPriceTarget' => 0,
                'ratings' => [
                    'buy' => 0,
                    'hold' => 0,
                    'sell' => 0,
                ],
                'upside' => 0,
                'ratings_count' => 0
            ];
        }
        
        // Just return the consensus data that's already calculated
        return [
            'symbol' => $symbol,
            'consensus' => $ratingsData['consensus']['consensusRating'] ?? 'N/A',
            'averagePriceTarget' => $ratingsData['consensus']['averagePriceTarget'] ?? 0,
            'lowPriceTarget' => $ratingsData['consensus']['lowPriceTarget'] ?? 0,
            'highPriceTarget' => $ratingsData['consensus']['highPriceTarget'] ?? 0,
            'ratings' => [
                'buy' => $ratingsData['consensus']['buy'] ?? 0,
                'hold' => $ratingsData['consensus']['hold'] ?? 0,
                'sell' => $ratingsData['consensus']['sell'] ?? 0,
            ],
            'upside' => $ratingsData['consensus']['upside'] ?? 0,
            'ratings_count' => count($ratingsData['ratings'] ?? [])
        ];
    }
    
    /**
     * Import historical stock prices for a company
     *
     * @param Company $company The company to import stock prices for
     * @param string $interval Time interval (daily, weekly, monthly)
     * @param int $limit Maximum number of historical data points to import (0 for all)
     * @return int Number of price records imported/updated
     */
    public function importHistoricalPrices(Company $company, string $interval = 'daily', int $limit = 100): int
    {
        $prices = $this->getHistoricalPrices(
            $company->getTickerSymbol(), 
            $interval, 
            $limit > 100 ? 'full' : 'compact'
        );
        
        if (empty($prices)) {
            $this->logger->warning('No historical price data available for ' . $company->getTickerSymbol());
            return 0;
        }
        
        $count = 0;
        $repository = $this->entityManager->getRepository(\App\Entity\StockPrice::class);
        
        // Limit the number of records if needed
        if ($limit > 0 && count($prices) > $limit) {
            $prices = array_slice($prices, 0, $limit);
        }
        
        foreach ($prices as $priceData) {
            // Check if this date already exists
            $date = new \DateTime($priceData['date']);
            $existingPrice = $repository->findOneBy([
                'company' => $company,
                'date' => $date,
                'period' => $interval
            ]);
            
            if ($existingPrice) {
                // Update existing price
                $price = $existingPrice;
                $this->logger->debug('Updating price data for ' . $company->getTickerSymbol() . ': ' . $priceData['date']);
            } else {
                // Create new price record
                $price = new \App\Entity\StockPrice();
                $price->setCompany($company);
                $price->setDate($date);
                $price->setPeriod($interval);
                $price->setCreatedAt(new \DateTimeImmutable());
                $this->logger->debug('Creating price data for ' . $company->getTickerSymbol() . ': ' . $priceData['date']);
            }
            
            // Set price data
            $price->setOpen($priceData['open']);
            $price->setHigh($priceData['high']);
            $price->setLow($priceData['low']);
            $price->setClose($priceData['close']);
            $price->setAdjustedClose($priceData['adjustedClose']);
            $price->setVolume($priceData['volume']);
            
            // Calculate change and change percent
            if (isset($prices[$count+1])) {
                $previousClose = $prices[$count+1]['close'];
                $change = $priceData['close'] - $previousClose;
                $changePercent = $previousClose > 0 ? ($change / $previousClose) * 100 : 0;
                
                $price->setChange($change);
                $price->setChangePercent($changePercent);
            }
            
            $price->setSource('API');
            $price->setUpdatedAt(new \DateTimeImmutable());
            
            $this->entityManager->persist($price);
            $count++;
            
            // Periodically flush to avoid memory issues with large datasets
            if ($count % 50 === 0) {
                $this->entityManager->flush();
            }
        }
        
        $this->entityManager->flush();
        $this->logger->info('Imported ' . $count . ' price records for ' . $company->getTickerSymbol());
        
        return $count;
    }
}
