<?php

namespace App\Service;

use App\Entity\Company;
use App\Entity\SecFiling;
use App\Repository\SecFilingRepository;
use App\Service\ApiClient\EdgarApiClient;
use App\Service\NeuronAiService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for handling SEC filings operations
 */
class SecFilingService
{
    private EdgarApiClient $edgarApiClient;
    private NeuronAiService $neuronAiService;
    private EntityManagerInterface $entityManager;
    private SecFilingRepository $secFilingRepository;
    private LoggerInterface $logger;

    /**
     * Constructor
     */
    public function __construct(
        EdgarApiClient $edgarApiClient,
        NeuronAiService $neuronAiService,
        EntityManagerInterface $entityManager,
        SecFilingRepository $secFilingRepository,
        LoggerInterface $logger
    ) {
        $this->edgarApiClient = $edgarApiClient;
        $this->neuronAiService = $neuronAiService;
        $this->entityManager = $entityManager;
        $this->secFilingRepository = $secFilingRepository;
        $this->logger = $logger;
    }

    /**
     * Fetch and store 10-K reports for a company
     *
     * @param Company $company The company to fetch reports for
     * @param bool $downloadContent Whether to download the full content
     * @param int $limit Maximum number of reports to fetch
     * @return SecFiling[] The imported SEC filings
     */
    public function import10KReports(Company $company, bool $downloadContent = false, int $limit = 5): array
    {
        if (!$company->getTickerSymbol()) {
            $this->logger->warning('Cannot import 10-K reports: company has no ticker symbol');
            return [];
        }

        // Fetch 10-K reports from EDGAR
        $reports = $this->edgarApiClient->get10KReports($company->getTickerSymbol(), $limit);

        if (empty($reports)) {
            $this->logger->warning('No 10-K reports found for ' . $company->getTickerSymbol());
            return [];
        }

        $importedFilings = [];

        foreach ($reports as $report) {
            // Check if we already have this filing
            $existingFiling = $this->secFilingRepository->findByAccessionNumber($report['accessionNumber']);

            if ($existingFiling) {
                $this->logger->info('SEC filing already exists for accession number: ' . $report['accessionNumber']);
                $importedFilings[] = $existingFiling;
                continue;
            }

            // Create new filing entity
            $filing = new SecFiling();
            $filing->setCompany($company);
            $filing->setFormType($report['formType']);
            $filing->setFilingDate(new \DateTime($report['filingDate']));

            if (isset($report['reportDate'])) {
                $filing->setReportDate(new \DateTime($report['reportDate']));
            }

            $filing->setAccessionNumber($report['accessionNumber']);
            $filing->setFileNumber($report['fileNumber'] ?? null);
            $filing->setDescription($report['description'] ?? null);
            $filing->setDocumentUrl($report['documentUrl']);
            $filing->setHtmlUrl($report['htmlUrl'] ?? null);
            $filing->setTextUrl($report['textUrl'] ?? null);

            // Extract fiscal year from filing date or report date
            $filingDate = new \DateTime($report['filingDate']);
            $fiscalYear = $filingDate->format('Y');

            // If filing is in Q1, it's likely for the previous year
            if ($filingDate->format('m') <= 3) {
                $fiscalYear = (int)$fiscalYear - 1;
            }

            $filing->setFiscalYear((string)$fiscalYear);

            // Download content if requested
            if ($downloadContent && !empty($report['textUrl'])) {
                $this->logger->info('Downloading content for 10-K: ' . $report['accessionNumber']);

                try {
                    $content = $this->edgarApiClient->downloadReport($report['textUrl'], 'text');
                    $filing->setContent($content);
                } catch (\Exception $e) {
                    $this->logger->error('Error downloading 10-K content: ' . $e->getMessage());
                }
            }

            // Save to database
            $this->entityManager->persist($filing);
            $importedFilings[] = $filing;
        }

        $this->entityManager->flush();

        return $importedFilings;
    }

    /**
     * Process SEC filing document to extract sections and generate summaries
     *
     * @param SecFiling $filing The SEC filing to process
     * @return bool True if processing was successful
     */
    public function processSecFiling(SecFiling $filing): bool
    {
        // Check if we have content to process
        if (!$filing->getContent()) {
            if ($filing->getTextUrl()) {
                try {
                    $content = $this->edgarApiClient->downloadReport($filing->getTextUrl(), 'text');
                    $filing->setContent($content);
                } catch (\Exception $e) {
                    $this->logger->error('Error downloading content for processing: ' . $e->getMessage());
                    return false;
                }
            } else {
                $this->logger->error('No content or URL available for processing filing: ' . $filing->getId());
                return false;
            }
        }

        // Extract sections
        $sections = $this->edgarApiClient->extractReportSections($filing->getContent());

        if (empty($sections)) {
            $this->logger->warning('No sections extracted from filing: ' . $filing->getId());
            return false;
        }

        // Set extracted sections
        $filing->setSections($sections);

        // Generate summaries using NeuronAI
        $keyFindings = [];
        $summaries = [];

        foreach ($sections as $key => $content) {
            if (empty(trim($content))) {
                continue;
            }

            $sectionTitle = match($key) {
                'item1' => 'Business',
                'item1a' => 'Risk Factors',
                'item7' => 'Management\'s Discussion and Analysis',
                'item8' => 'Financial Statements and Supplementary Data',
                default => 'Section ' . $key,
            };

            // Trim content to manageable size for AI processing
            $truncatedContent = substr($content, 0, 8000);

            try {
                // Generate summary
                $summary = $this->neuronAiService->generateText(
                    "Summarize the following section from a 10-K report for {$filing->getCompany()->getName()}: {$sectionTitle}\n\n{$truncatedContent}",
                    1000
                );

                // Extract key findings
                $findings = $this->neuronAiService->generateText(
                    "Extract 3-5 key findings from the following section of a 10-K report for {$filing->getCompany()->getName()}: {$sectionTitle}\n\n{$truncatedContent}",
                    500
                );

                $summaries[$key] = $summary;
                $keyFindings[$key] = $findings;
            } catch (\Exception $e) {
                $this->logger->error('Error generating AI summary: ' . $e->getMessage());
                // Continue with other sections if one fails
            }
        }

        // Generate overall summary
        $overallSummary = '';
        try {
            $combinedSummaries = implode("\n\n", $summaries);
            $overallSummary = $this->neuronAiService->generateText(
                "Create a concise executive summary of the following 10-K report highlights for {$filing->getCompany()->getName()} ({$filing->getFiscalYear()}):\n\n{$combinedSummaries}",
                1500
            );
        } catch (\Exception $e) {
            $this->logger->error('Error generating overall summary: ' . $e->getMessage());
        }

        // Update filing with summaries and key findings
        $filing->setSummary($overallSummary);
        $filing->setKeyFindings($keyFindings);
        $filing->setIsProcessed(true);
        $filing->setUpdatedAt(new \DateTimeImmutable());

        // Save changes
        $this->entityManager->persist($filing);
        $this->entityManager->flush();

        return true;
    }

    /**
     * Process all unprocessed SEC filings
     *
     * @param int $limit Maximum number of filings to process
     * @return int Number of successfully processed filings
     */
    public function processUnprocessedFilings(int $limit = 10): int
    {
        $unprocessedFilings = $this->secFilingRepository->findUnprocessedFilings($limit);

        if (empty($unprocessedFilings)) {
            $this->logger->info('No unprocessed SEC filings found');
            return 0;
        }

        $count = 0;

        foreach ($unprocessedFilings as $filing) {
            if ($this->processSecFiling($filing)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get latest 10-K report for a company
     *
     * @param Company $company The company
     * @param bool $processIfNeeded Whether to process the filing if not already processed
     * @return SecFiling|null The latest 10-K report or null if not found
     */
    public function getLatest10K(Company $company, bool $processIfNeeded = true): ?SecFiling
    {
        // Try to find in database first
        $filing = $this->secFilingRepository->findLatest10K($company);

        if (!$filing) {
            // If not found, try to import from EDGAR
            $filings = $this->import10KReports($company, true, 1);
            $filing = $filings[0] ?? null;
        }

        // If found but not processed, process it if requested
        if ($filing && !$filing->getIsProcessed() && $processIfNeeded) {
            $this->processSecFiling($filing);
        }

        return $filing;
    }

    /**
     * Get recommended key sections from 10-K for a company (for research reports)
     *
     * @param Company $company The company
     * @return array Extracted key sections
     */
    public function getKeyInsightsFrom10K(Company $company): array
    {
        $filing = $this->getLatest10K($company);

        if (!$filing || !$filing->getIsProcessed()) {
            return [
                'summary' => 'No processed 10-K report available',
                'business' => 'Information not available',
                'risks' => 'Information not available',
                'mda' => 'Information not available',
                'keyFindings' => [],
                'fiscalYear' => date('Y'),
            ];
        }

        // Extract key sections
        return [
            'summary' => $filing->getSummary(),
            'business' => $filing->getSection('item1') ? $this->shortenText($filing->getSection('item1'), 1000) : 'Not available',
            'risks' => $filing->getSection('item1a') ? $this->shortenText($filing->getSection('item1a'), 1000) : 'Not available',
            'mda' => $filing->getSection('item7') ? $this->shortenText($filing->getSection('item7'), 1000) : 'Not available',
            'keyFindings' => $filing->getKeyFindings(),
            'fiscalYear' => $filing->getFiscalYear(),
        ];
    }

    /**
     * Shorten text to a maximum length while preserving whole sentences
     *
     * @param string $text Text to shorten
     * @param int $maxLength Maximum length
     * @return string Shortened text
     */
    private function shortenText(string $text, int $maxLength): string
    {
        if (strlen($text) <= $maxLength) {
            return $text;
        }

        $shortenedText = substr($text, 0, $maxLength);
        $lastPeriod = strrpos($shortenedText, '.');

        if ($lastPeriod !== false) {
            $shortenedText = substr($shortenedText, 0, $lastPeriod + 1);
        }

        return $shortenedText . '...';
    }
}
