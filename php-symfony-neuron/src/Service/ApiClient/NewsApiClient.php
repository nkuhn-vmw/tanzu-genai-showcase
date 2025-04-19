<?php

namespace App\Service\ApiClient;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Psr\Log\LoggerInterface;

/**
 * News API client for fetching news articles
 *
 * Documentation: https://newsapi.org/docs
 */
class NewsApiClient extends AbstractApiClient
{
    /**
     * {@inheritdoc}
     */
    protected function initialize(): void
    {
        $this->baseUrl = 'https://newsapi.org/v2';
        $this->apiKey = $this->params->get('news_api.api_key', '');

        // During development without API key, log a message
        if (empty($this->apiKey)) {
            $this->logger->warning('News API key not set, using mock data');
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getAuthParams(): array
    {
        return ['apiKey' => $this->apiKey];
    }

    /**
     * Get company news from News API
     *
     * @param string $symbol Company ticker symbol or name
     * @param \DateTime|null $from Start date for news search
     * @param \DateTime|null $to End date for news search
     * @param int $limit Maximum number of articles to return
     * @return array News articles
     */
    public function getCompanyNews(
        string $symbol,
        ?\DateTime $from = null,
        ?\DateTime $to = null,
        int $limit = 10
    ): array {
        // Default date range is last 30 days
        if (!$from) {
            $from = new \DateTime('30 days ago');
        }

        if (!$to) {
            $to = new \DateTime();
        }

        // Format dates for API
        $fromFormatted = $from->format('Y-m-d');
        $toFormatted = $to->format('Y-m-d');

        // Company name or ticker (use both for better results)
        $query = $symbol;

        // Add parameters
        $params = [
            'q' => $query,
            'from' => $fromFormatted,
            'to' => $toFormatted,
            'language' => 'en',
            'sortBy' => 'publishedAt',
            'pageSize' => $limit
        ];

        // Make the API request
        $data = $this->request('GET', '/everything', $params);

        // Process and standardize the results
        $articles = [];

        if (isset($data['articles']) && is_array($data['articles'])) {
            foreach ($data['articles'] as $article) {
                $articles[] = [
                    'title' => $article['title'] ?? '',
                    'description' => $article['description'] ?? '',
                    'content' => $article['content'] ?? '',
                    'url' => $article['url'] ?? '',
                    'imageUrl' => $article['urlToImage'] ?? null,
                    'source' => $article['source']['name'] ?? 'Unknown',
                    'author' => $article['author'] ?? 'Unknown',
                    'publishedAt' => $article['publishedAt'] ?? date('Y-m-d H:i:s'),
                    'sentiment' => 0,  // Default neutral sentiment
                ];
            }
        }

        return $articles;
    }

    /**
     * Search for top headlines in a specific category
     *
     * @param string $category News category (business, technology, etc.)
     * @param string $country Country code (us, gb, etc.)
     * @param int $limit Maximum number of articles to return
     * @return array News articles
     */
    public function getTopHeadlines(string $category = 'business', string $country = 'us', int $limit = 10): array
    {
        $params = [
            'category' => $category,
            'country' => $country,
            'pageSize' => $limit
        ];

        // Make the API request
        $data = $this->request('GET', '/top-headlines', $params);

        // Process and standardize the results
        $articles = [];

        if (isset($data['articles']) && is_array($data['articles'])) {
            foreach ($data['articles'] as $article) {
                $articles[] = [
                    'title' => $article['title'] ?? '',
                    'description' => $article['description'] ?? '',
                    'content' => $article['content'] ?? '',
                    'url' => $article['url'] ?? '',
                    'imageUrl' => $article['urlToImage'] ?? null,
                    'source' => $article['source']['name'] ?? 'Unknown',
                    'author' => $article['author'] ?? 'Unknown',
                    'publishedAt' => $article['publishedAt'] ?? date('Y-m-d H:i:s'),
                    'sentiment' => 0,  // Default neutral sentiment
                ];
            }
        }

        return $articles;
    }

    /**
     * Get market news for multiple companies
     *
     * @param array $symbols Array of ticker symbols
     * @param int $limit Maximum number of articles per symbol
     * @return array News articles grouped by symbol
     */
    public function getMarketNews(array $symbols, int $limit = 5): array
    {
        $results = [];

        foreach ($symbols as $symbol) {
            $results[$symbol] = $this->getCompanyNews($symbol, null, null, $limit);
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    protected function getMockData(string $endpoint, array $params): array
    {
        switch ($endpoint) {
            case '/everything':
                return $this->getMockCompanyNews($params['q'] ?? '');
            case '/top-headlines':
                return $this->getMockTopHeadlines($params['category'] ?? 'business');
            default:
                return ['articles' => []];
        }
    }

    /**
     * Generate mock company news
     *
     * @param string $query Company query
     * @return array Mock news data
     */
    private function getMockCompanyNews(string $query): array
    {
        // Strip any quotes from query
        $query = trim(str_replace('"', '', $query));

        // Get a company name from the query (either use as-is or extract from query like "AAPL Apple")
        $parts = explode(' ', $query);
        $symbol = $parts[0];
        $companyName = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : $symbol;

        // Map known symbols to company names
        if (strtoupper($symbol) === 'AAPL') {
            $companyName = 'Apple';
        } elseif (strtoupper($symbol) === 'MSFT') {
            $companyName = 'Microsoft';
        } elseif (strtoupper($symbol) === 'AVGO') {
            $companyName = 'Broadcom';
        } elseif (strtoupper($symbol) === 'AMZN') {
            $companyName = 'Amazon';
        } elseif (strtoupper($symbol) === 'GOOGL') {
            $companyName = 'Google';
        } elseif (strtoupper($symbol) === 'TSLA') {
            $companyName = 'Tesla';
        } elseif (strtoupper($symbol) === 'META') {
            $companyName = 'Meta';
        } elseif (strtoupper($symbol) === 'NVDA') {
            $companyName = 'NVIDIA';
        }
        // else keep the existing company name

        // Current time for timestamps
        $now = time();
        $day = 86400; // seconds in a day

        // Generate mock articles with company name
        $articles = [
            [
                'title' => $companyName . ' Reports Strong Quarterly Growth',
                'description' => $companyName . ' announced financial results for its fiscal quarter, exceeding analyst expectations with revenue growth of 15% year-over-year.',
                'content' => $companyName . ' announced financial results for its fiscal quarter today, reporting revenue of $XX billion, an increase of 15% year-over-year. The company also reported earnings per share of $X.XX, above the consensus estimate of $X.XX. "We\'re pleased with our performance this quarter," said the CEO of ' . $companyName . '. "Our continued investment in innovation is driving growth across all business segments."',
                'url' => 'https://example.com/business/' . strtolower(str_replace(' ', '-', $companyName)) . '-earnings',
                'urlToImage' => 'https://example.com/images/finance-chart.jpg',
                'source' => ['id' => null, 'name' => 'Financial Times'],
                'author' => 'Financial Reporter',
                'publishedAt' => date('Y-m-d H:i:s', $now - (2 * $day)),
            ],
            [
                'title' => $companyName . ' Announces New Product Line',
                'description' => 'Today at their annual conference, ' . $companyName . ' unveiled a new product line that analysts say could significantly impact their market position.',
                'content' => $companyName . ' has announced a new product line at their annual conference today. The new offerings include innovative features that analysts believe will strengthen the company\'s competitive position. "This represents a major step forward," said an industry analyst. "We expect these products to drive significant revenue growth over the next fiscal year."',
                'url' => 'https://example.com/tech/' . strtolower(str_replace(' ', '-', $companyName)) . '-new-products',
                'urlToImage' => 'https://example.com/images/product-launch.jpg',
                'source' => ['id' => null, 'name' => 'Tech Insider'],
                'author' => 'Tech Reporter',
                'publishedAt' => date('Y-m-d H:i:s', $now - (5 * $day)),
            ],
            [
                'title' => 'Analysts Upgrade ' . $companyName . ' to "Buy" Rating',
                'description' => 'Following strong performance and positive outlook, several major analysts have upgraded ' . $companyName . ' stock to a "Buy" rating.',
                'content' => 'Several major analyst firms have upgraded ' . $companyName . ' stock to a "Buy" rating following the company\'s strong performance and positive outlook. The consensus price target has been raised to $XXX, representing a XX% upside from current levels. Analysts cite the company\'s market position, product pipeline, and operational efficiency as key factors in their upgraded outlook.',
                'url' => 'https://example.com/markets/' . strtolower(str_replace(' ', '-', $companyName)) . '-analyst-upgrade',
                'urlToImage' => 'https://example.com/images/stock-chart-up.jpg',
                'source' => ['id' => null, 'name' => 'Market Watch'],
                'author' => 'Market Analyst',
                'publishedAt' => date('Y-m-d H:i:s', $now - (7 * $day)),
            ],
            [
                'title' => $companyName . ' Expands International Operations',
                'description' => $companyName . ' announced plans to expand its operations in Asia and Europe, with significant investments in new facilities and hiring.',
                'content' => $companyName . ' has announced plans to expand its international operations, with significant investments in new facilities in Asia and Europe. The company plans to hire hundreds of new employees as part of this expansion. "Global markets represent a tremendous growth opportunity for us," said the company\'s COO. "These investments will position us to better serve our international customers and accelerate our global growth strategy."',
                'url' => 'https://example.com/business/' . strtolower(str_replace(' ', '-', $companyName)) . '-global-expansion',
                'urlToImage' => 'https://example.com/images/global-business.jpg',
                'source' => ['id' => null, 'name' => 'Business Wire'],
                'author' => 'Business Reporter',
                'publishedAt' => date('Y-m-d H:i:s', $now - (10 * $day)),
            ],
            [
                'title' => $companyName . ' Partners with Leading Technology Provider',
                'description' => 'Strategic partnership announced between ' . $companyName . ' and a leading technology provider to develop next-generation solutions.',
                'content' => $companyName . ' has announced a strategic partnership with a leading technology provider to develop next-generation solutions for enterprise customers. The partnership will combine ' . $companyName . '\'s industry expertise with cutting-edge technology to address complex business challenges. "This collaboration will accelerate innovation and create significant value for our customers," said the CEO of ' . $companyName . '.',
                'url' => 'https://example.com/tech/' . strtolower(str_replace(' ', '-', $companyName)) . '-partnership',
                'urlToImage' => 'https://example.com/images/partnership.jpg',
                'source' => ['id' => null, 'name' => 'Reuters'],
                'author' => 'Technology Correspondent',
                'publishedAt' => date('Y-m-d H:i:s', $now - (14 * $day)),
            ],
        ];

        return ['articles' => $articles];
    }

    /**
     * Generate mock top headlines
     *
     * @param string $category News category
     * @return array Mock headlines data
     */
    private function getMockTopHeadlines(string $category): array
    {
        $now = time();
        $day = 86400; // seconds in a day

        // Different headlines based on category
        $headlines = [];

        switch ($category) {
            case 'business':
                $headlines = [
                    [
                        'title' => 'Global Markets Rally on Strong Economic Data',
                        'description' => 'Stock markets around the world rallied today following stronger-than-expected economic data from major economies.',
                        'content' => 'Global stock markets posted significant gains today as economic data from the United States, Europe, and China exceeded expectations. The Dow Jones Industrial Average rose X.X%, while European and Asian markets also recorded substantial gains. Analysts pointed to strong manufacturing data and better-than-expected employment figures as key drivers of the market rally.',
                        'url' => 'https://example.com/business/global-markets-rally',
                        'urlToImage' => 'https://example.com/images/stock-market.jpg',
                        'source' => ['id' => null, 'name' => 'Financial Times'],
                        'author' => 'Financial Reporter',
                        'publishedAt' => date('Y-m-d H:i:s', $now - (1 * $day)),
                    ],
                    [
                        'title' => 'Central Bank Signals Potential Interest Rate Cut',
                        'description' => 'The central bank has indicated it may consider cutting interest rates in response to changing economic conditions.',
                        'content' => 'In a statement released today, the central bank signaled it may consider reducing interest rates in the coming months in response to changing economic conditions. The bank cited moderating inflation pressures and concerns about economic growth as factors in its evolving monetary policy stance. Economists now predict a higher probability of a rate cut at the next policy meeting.',
                        'url' => 'https://example.com/business/central-bank-rate-cut',
                        'urlToImage' => 'https://example.com/images/central-bank.jpg',
                        'source' => ['id' => null, 'name' => 'Bloomberg'],
                        'author' => 'Economics Editor',
                        'publishedAt' => date('Y-m-d H:i:s', $now - (2 * $day)),
                    ],
                ];
                break;

            case 'technology':
                $headlines = [
                    [
                        'title' => 'AI Breakthrough Promises Faster Computing',
                        'description' => 'Researchers announce major breakthrough in artificial intelligence that could revolutionize computing speeds.',
                        'content' => 'Researchers at a leading technology institute have announced a major breakthrough in artificial intelligence that could significantly increase computing speeds. The new algorithm architecture reportedly improves processing efficiency by up to 40% while reducing energy consumption. "This represents a potential paradigm shift in how we approach machine learning tasks," said the lead researcher.',
                        'url' => 'https://example.com/technology/ai-breakthrough',
                        'urlToImage' => 'https://example.com/images/ai-research.jpg',
                        'source' => ['id' => null, 'name' => 'Tech Insider'],
                        'author' => 'Technology Correspondent',
                        'publishedAt' => date('Y-m-d H:i:s', $now - (1 * $day)),
                    ],
                    [
                        'title' => 'Major Tech Companies Announce Quantum Computing Alliance',
                        'description' => 'Leading technology firms have formed an alliance to accelerate quantum computing research and development.',
                        'content' => 'Several major technology companies have announced the formation of a quantum computing alliance aimed at accelerating research and development in this emerging field. The collaboration will focus on developing practical quantum computing applications and establishing industry standards. The alliance members have committed substantial resources to advance quantum computing technology over the next five years.',
                        'url' => 'https://example.com/technology/quantum-computing-alliance',
                        'urlToImage' => 'https://example.com/images/quantum-computer.jpg',
                        'source' => ['id' => null, 'name' => 'Wired'],
                        'author' => 'Science & Technology Editor',
                        'publishedAt' => date('Y-m-d H:i:s', $now - (3 * $day)),
                    ],
                ];
                break;

            default:
                $headlines = [
                    [
                        'title' => 'Breaking News in ' . ucfirst($category),
                        'description' => 'Major developments reported in the ' . $category . ' sector today.',
                        'content' => 'This is mock content for a breaking news story in the ' . $category . ' category. The full article would contain detailed information about recent events and their potential impact.',
                        'url' => 'https://example.com/' . $category . '/breaking-news',
                        'urlToImage' => 'https://example.com/images/news.jpg',
                        'source' => ['id' => null, 'name' => 'News Service'],
                        'author' => 'News Reporter',
                        'publishedAt' => date('Y-m-d H:i:s', $now - (1 * $day)),
                    ],
                ];
        }

        return ['articles' => $headlines];
    }
}
