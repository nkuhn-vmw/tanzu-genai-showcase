<?php

namespace App\Service\ApiClient;

/**
 * Interface for financial data API clients
 */
interface ApiClientInterface
{
    /**
     * Search for companies matching a term (name or symbol)
     *
     * @param string $term The search term
     * @return array An array of matching companies with basic info
     */
    public function searchCompanies(string $term): array;

    /**
     * Get company profile information
     *
     * @param string $symbol Company ticker symbol
     * @return array Company profile data
     */
    public function getCompanyProfile(string $symbol): array;

    /**
     * Get latest stock quote
     *
     * @param string $symbol Company ticker symbol
     * @return array Quote data
     */
    public function getQuote(string $symbol): array;

    /**
     * Get company financial data
     *
     * @param string $symbol Company ticker symbol
     * @param string $period Period (quarterly, annual)
     * @return array Financial data
     */
    public function getFinancials(string $symbol, string $period = 'quarterly'): array;

    /**
     * Get latest news for a company
     *
     * @param string $symbol Company ticker symbol
     * @param int $limit Number of news items to return
     * @return array Company news
     */
    public function getCompanyNews(string $symbol, int $limit = 5): array;

    /**
     * Get company executive/leadership data
     *
     * @param string $symbol Company ticker symbol
     * @return array Executive data
     */
    public function getExecutives(string $symbol): array;

    /**
     * Get historical stock prices
     *
     * @param string $symbol Company ticker symbol
     * @param string $interval Time interval (daily, weekly, monthly)
     * @param string $outputSize Size of time series (compact = 100 data points, full = 20+ years)
     * @return array Historical price data
     */
    public function getHistoricalPrices(string $symbol, string $interval = 'daily', string $outputSize = 'compact'): array;
}
