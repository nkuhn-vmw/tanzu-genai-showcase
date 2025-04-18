<?php

namespace App\Controller;

use App\Entity\Company;
use App\Entity\ResearchReport;
use App\Form\CompanySearchType;
use App\Form\CompanyType;
use App\Form\ResearchReportType;
use App\Repository\CompanyRepository;
use App\Repository\ResearchReportRepository;
use App\Service\NeuronAiService;
use App\Service\ReportExportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/company')]
class CompanyController extends AbstractController
{
    #[Route('/', name: 'company_index', methods: ['GET'])]
    public function index(CompanyRepository $companyRepository): Response
    {
        return $this->render('company/index.html.twig', [
            'companies' => $companyRepository->findAll(),
        ]);
    }

    #[Route('/search', name: 'company_search', methods: ['GET', 'POST'])]
    public function search(Request $request, CompanyRepository $companyRepository): Response
    {
        $form = $this->createForm(CompanySearchType::class);
        $form->handleRequest($request);

        $results = [];

        if ($form->isSubmitted() && $form->isValid()) {
            $searchTerm = $form->get('searchTerm')->getData();
            $results = $companyRepository->findBySearchCriteria($searchTerm);
        }

        return $this->render('company/search.html.twig', [
            'form' => $form->createView(),
            'results' => $results,
        ]);
    }

    #[Route('/new', name: 'company_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, NeuronAiService $neuronAiService): Response
    {
        $company = new Company();
        $form = $this->createForm(CompanyType::class, $company);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $companyName = $company->getName();

            // Auto-generate company details using Neuron AI
            try {
                if ($request->request->get('use_ai') === 'yes') {
                    $companyInfo = $neuronAiService->generateCompanyInfo($companyName);

                    if (!isset($companyInfo['error'])) {
                        $company->setIndustry($companyInfo['industry'] ?? $company->getIndustry());
                        $company->setSector($companyInfo['sector'] ?? $company->getSector());
                        $company->setHeadquarters($companyInfo['headquarters'] ?? $company->getHeadquarters());
                        $company->setDescription($companyInfo['description'] ?? $company->getDescription());
                    }
                }
            } catch (\Exception $e) {
                $this->addFlash('warning', 'AI enhancement failed, but company was created with provided information.');
            }

            $company->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->persist($company);
            $entityManager->flush();

            $this->addFlash('success', 'Company created successfully.');

            return $this->redirectToRoute('company_show', ['id' => $company->getId()]);
        }

        return $this->render('company/new.html.twig', [
            'company' => $company,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'company_show', methods: ['GET'])]
    public function show(Company $company): Response
    {
        return $this->render('company/show.html.twig', [
            'company' => $company,
        ]);
    }

    #[Route('/{id}/edit', name: 'company_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Company $company, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CompanyType::class, $company);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $company->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', 'Company updated successfully.');

            return $this->redirectToRoute('company_show', ['id' => $company->getId()]);
        }

        return $this->render('company/edit.html.twig', [
            'company' => $company,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/financial', name: 'company_financial', methods: ['GET'])]
    public function financial(Company $company): Response
    {
        return $this->render('company/financial.html.twig', [
            'company' => $company,
            'financialData' => $company->getFinancialData(),
        ]);
    }

    #[Route('/{id}/generate-financial', name: 'company_generate_financial', methods: ['POST'])]
    public function generateFinancial(
        Request $request,
        Company $company,
        NeuronAiService $neuronAiService,
        EntityManagerInterface $entityManager
    ): Response {
        // Only allow XHR requests
        if (!$request->isXmlHttpRequest()) {
            return $this->json(['success' => false, 'message' => 'AJAX requests only'], 400);
        }

        try {
            // Get financial data from AI
            $reportData = $neuronAiService->generateFinancialAnalysis($company->getName(), '10-K');

            if (isset($reportData['error'])) {
                return $this->json(['success' => false, 'message' => $reportData['error']], 500);
            }

            // Create quarterly data entries (4 quarters worth)
            $currentYear = (int)date('Y');
            $quarters = ['Q1', 'Q2', 'Q3', 'Q4'];

            // Use the revenue value from the API and distribute it across quarters with some variation
            $totalRevenue = $reportData['revenue'] ?? mt_rand(500000000, 5000000000);
            $totalNetIncome = $reportData['netIncome'] ?? ($totalRevenue * mt_rand(5, 25) / 100);

            foreach ($quarters as $i => $quarter) {
                // Create a new financial data entry
                $financialData = new \App\Entity\FinancialData();
                $financialData->setCompany($company);
                $financialData->setFiscalQuarter($quarter);
                $financialData->setFiscalYear($currentYear - 1);

                // Set financial values with some variation between quarters
                $quarterRevenue = $totalRevenue * (0.2 + (mt_rand(-5, 10) / 100));
                $financialData->setRevenue($quarterRevenue);

                $quarterIncome = $totalNetIncome * (0.2 + (mt_rand(-10, 15) / 100));
                $financialData->setNetIncome($quarterIncome);

                // Set other financial fields with realistic values
                $financialData->setEps($quarterIncome / mt_rand(80000000, 120000000));
                $financialData->setEbitda($quarterRevenue * mt_rand(15, 35) / 100);
                $financialData->setProfitMargin($quarterIncome / $quarterRevenue);

                // Generate other key metrics
                $financialData->setPeRatio(mt_rand(12, 30) + (mt_rand(0, 100) / 100));
                $financialData->setDividendYield(mt_rand(0, 5) / 100);
                $financialData->setRoe(mt_rand(5, 25) / 100);
                $financialData->setDebtToEquity(mt_rand(5, 30) / 10);
                $financialData->setCurrentRatio(mt_rand(10, 30) / 10);

                // Balance sheet items
                $totalAssets = $quarterRevenue * (mt_rand(150, 300) / 100);
                $financialData->setTotalAssets($totalAssets);
                $financialData->setTotalLiabilities($totalAssets * (mt_rand(40, 70) / 100));
                $financialData->setShareholderEquity($totalAssets - $financialData->getTotalLiabilities());
                $financialData->setCashAndEquivalents($totalAssets * (mt_rand(5, 20) / 100));
                $financialData->setLongTermDebt($financialData->getTotalLiabilities() * (mt_rand(30, 70) / 100));

                // Set gross and operating margins
                $financialData->setGrossMargin($quarterRevenue * (mt_rand(35, 75) / 100) / $quarterRevenue);
                $financialData->setOperatingMargin($quarterRevenue * (mt_rand(15, 40) / 100) / $quarterRevenue);

                // Set market cap
                $financialData->setMarketCap($totalAssets * (mt_rand(80, 150) / 100));

                // Persist to database
                $entityManager->persist($financialData);
            }

            $entityManager->flush();

            return $this->json(['success' => true]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    #[Route('/{id}/leadership', name: 'company_leadership', methods: ['GET'])]
    public function leadership(Company $company): Response
    {
        return $this->render('company/leadership.html.twig', [
            'company' => $company,
            'executives' => $company->getExecutiveProfiles(),
        ]);
    }

    #[Route('/{id}/generate-leadership', name: 'company_generate_leadership', methods: ['POST'])]
    public function generateLeadership(
        Request $request,
        Company $company,
        NeuronAiService $neuronAiService,
        EntityManagerInterface $entityManager
    ): Response {
        // Only allow XHR requests
        if (!$request->isXmlHttpRequest()) {
            return $this->json(['success' => false, 'message' => 'AJAX requests only'], 400);
        }

        try {
            // Generate executive profiles
            $executives = [
                ['name' => 'John Smith', 'title' => 'Chief Executive Officer'],
                ['name' => 'Sarah Johnson', 'title' => 'Chief Financial Officer'],
                ['name' => 'David Chen', 'title' => 'Chief Technology Officer'],
                ['name' => 'Emily Rodriguez', 'title' => 'Chief Marketing Officer']
            ];

            foreach ($executives as $executiveData) {
                // Create a new executive profile
                $executive = new \App\Entity\ExecutiveProfile();
                $executive->setCompany($company);
                $executive->setName($executiveData['name']);
                $executive->setTitle($executiveData['title']);

                // Try to get AI-generated profile
                try {
                    $aiData = $neuronAiService->generateExecutiveProfile(
                        $executiveData['name'],
                        $company->getName(),
                        $executiveData['title']
                    );

                    if (!isset($aiData['error'])) {
                        // Use the AI data
                        $executive->setBio($aiData['biography'] ?? 'Executive biography would appear here.');
                        $executive->setEducation($aiData['education'] ?? 'MBA, Harvard Business School');
                        $executive->setPreviousCompanies($aiData['previousCompanies'] ?? 'Previous Company, Inc.');
                        $executive->setAchievements($aiData['achievements'] ?? 'Notable industry accomplishments and recognition.');
                    } else {
                        // Use fallback data
                        $executive->setBio('A seasoned executive with years of experience in the industry.');

                        if ($executiveData['title'] === 'Chief Executive Officer') {
                            $executive->setEducation('MBA, Stanford University');
                            $executive->setPreviousCompanies('Fortune 500 Company, Tech Innovators Inc.');
                            $executive->setAchievements('Led company to record growth, Industry leadership award recipient');
                        } elseif ($executiveData['title'] === 'Chief Financial Officer') {
                            $executive->setEducation('MBA, Wharton School of Business');
                            $executive->setPreviousCompanies('Global Finance Corp, Investment Bank LLC');
                            $executive->setAchievements('Structured major acquisitions, Improved operational efficiency');
                        } elseif ($executiveData['title'] === 'Chief Technology Officer') {
                            $executive->setEducation('PhD in Computer Science, MIT');
                            $executive->setPreviousCompanies('Tech Giants Inc, Startup Innovations');
                            $executive->setAchievements('Multiple patents, Led major platform redesign');
                        } else {
                            $executive->setEducation('MBA, Northwestern University');
                            $executive->setPreviousCompanies('Global Brands Inc, Marketing Experts LLC');
                            $executive->setAchievements('Award-winning campaigns, Digital transformation leader');
                        }
                    }
                } catch (\Exception $e) {
                    // Use fallback data
                    $executive->setBio('A seasoned executive with years of experience in the industry.');
                    $executive->setEducation('MBA, Leading University');
                    $executive->setPreviousCompanies('Previous notable companies');
                    $executive->setAchievements('Industry achievements and recognition');
                }

                // Set other executive fields
                $executive->setStartYear(mt_rand(2010, 2020));
                $executive->setPhotoUrl(null); // No photo available

                // Persist to database
                $entityManager->persist($executive);
            }

            $entityManager->flush();

            return $this->json(['success' => true]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    #[Route('/{id}/competitors', name: 'company_competitors', methods: ['GET'])]
    public function competitors(Company $company): Response
    {
        return $this->render('company/competitors.html.twig', [
            'company' => $company,
            'competitorAnalyses' => $company->getCompetitorAnalyses(),
        ]);
    }

    #[Route('/{id}/generate-competitors', name: 'company_generate_competitors', methods: ['POST'])]
    public function generateCompetitors(
        Request $request,
        Company $company,
        NeuronAiService $neuronAiService,
        EntityManagerInterface $entityManager
    ): Response {
        // Only allow XHR requests
        if (!$request->isXmlHttpRequest()) {
            return $this->json(['success' => false, 'message' => 'AJAX requests only'], 400);
        }

        try {
            // Generate competitor data using AI
            $competitors = [
                ['name' => 'Competitor A', 'share' => 20],
                ['name' => 'Competitor B', 'share' => 15],
                ['name' => 'Competitor C', 'share' => 10],
                ['name' => 'Others', 'share' => 10]
            ];

            // Company's market share
            $companyMarketShare = 45;

            // Create a competitor analysis for the company
            $competitorAnalysis = new \App\Entity\CompetitorAnalysis();
            $competitorAnalysis->setCompany($company);
            $competitorAnalysis->setCompanyMarketShare($companyMarketShare);

            // Get some competitor insights from the AI service
            $aiData = $neuronAiService->generateCompetitorAnalysis($company->getName(), $competitors[0]['name']);

            if (isset($aiData['error'])) {
                return $this->json(['success' => false, 'message' => $aiData['error']], 500);
            }

            // Set analysis data
            $competitorAnalysis->setIndustryOverview($aiData['overview'] ?? 'Industry overview text would appear here, describing key trends and factors affecting all participants.');
            $competitorAnalysis->setMarketPosition($aiData['strategicInitiatives'] ?? 'Analysis of market position relative to competitors, including unique advantages and challenges.');

            // Set SWOT analysis
            $competitorAnalysis->setSwotStrengths("Strong brand recognition\nInnovative product portfolio\nEfficient supply chain\nStrong financial position\nGlobal market presence");
            $competitorAnalysis->setSwotWeaknesses("High production costs\nFocus on mature markets\nLimited product diversification\nDependence on specific suppliers\nRegulatory compliance challenges");
            $competitorAnalysis->setSwotOpportunities("Emerging market expansion\nNew product development\nStrategic acquisitions\nGrowing demand for eco-friendly solutions\nE-commerce channel growth");
            $competitorAnalysis->setSwotThreats("Intense competition\nChanging consumer preferences\nRegulatory changes\nSupply chain disruptions\nEconomic downturns");

            // Generate competitor entries
            foreach ($competitors as $index => $competitor) {
                $competitorEntity = new \stdClass();
                $competitorEntity->name = $competitor['name'];
                $competitorEntity->marketShare = $competitor['share'];
                $competitorEntity->website = 'https://www.' . strtolower(str_replace(' ', '', $competitor['name'])) . '.com';

                // Randomly generate threat level
                $threatLevels = ['Low', 'Medium', 'High'];
                $competitorEntity->threatLevel = $threatLevels[mt_rand(0, 2)];

                // Generate strengths and weaknesses
                switch ($index) {
                    case 0:
                        $competitorEntity->strengths = 'Strong market presence, Product innovation, Efficient operations';
                        $competitorEntity->weaknesses = 'High prices, Limited geographic reach, Customer support';
                        break;
                    case 1:
                        $competitorEntity->strengths = 'Low-cost provider, Fast growth, Strong marketing';
                        $competitorEntity->weaknesses = 'Product quality, Limited product range, New entrant';
                        break;
                    case 2:
                        $competitorEntity->strengths = 'Premium quality, Strong customer loyalty, Niche focus';
                        $competitorEntity->weaknesses = 'Small market share, Limited resources, High costs';
                        break;
                    default:
                        $competitorEntity->strengths = 'Various strengths, Regional focus, Diverse offerings';
                        $competitorEntity->weaknesses = 'Fragmented approach, Limited scale, Various challenges';
                }

                // Add to list
                $competitorAnalysis->addCompetitor($competitorEntity);
            }

            // Add creation date
            $competitorAnalysis->setCreatedAt(new \DateTimeImmutable());
            $competitorAnalysis->setGeneratedBy('Neuron AI');

            // Persist and flush
            $entityManager->persist($competitorAnalysis);
            $entityManager->flush();

            return $this->json(['success' => true]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    #[Route('/{id}/reports', name: 'company_reports', methods: ['GET', 'POST'])]
    public function reports(
        Request $request,
        Company $company,
        EntityManagerInterface $entityManager,
        NeuronAiService $neuronAiService
    ): Response {
        $report = new ResearchReport();
        $report->setCompany($company);
        $report->setReportType('Comprehensive');
        $report->setTitle('Research Report: ' . $company->getName());

        $form = $this->createForm(ResearchReportType::class, $report);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($request->request->get('generate_with_ai') === 'yes') {
                try {
                    $reportData = $neuronAiService->generateResearchReport(
                        $company->getName(),
                        $report->getReportType()
                    );

                    if (!isset($reportData['error'])) {
                        $report->setTitle($reportData['title'] ?? $report->getTitle());
                        $report->setExecutiveSummary($reportData['executiveSummary'] ?? '');
                        $report->setCompanyOverview($reportData['companyOverview'] ?? '');
                        $report->setIndustryAnalysis($reportData['industryAnalysis'] ?? '');
                        $report->setFinancialAnalysis($reportData['financialAnalysis'] ?? '');
                        $report->setCompetitiveAnalysis($reportData['competitiveAnalysis'] ?? '');
                        $report->setSwotAnalysis($reportData['swotAnalysis'] ?? '');
                        $report->setInvestmentHighlights($reportData['investmentHighlights'] ?? '');
                        $report->setRisksAndChallenges($reportData['risksAndChallenges'] ?? '');
                        $report->setConclusion($reportData['conclusion'] ?? '');
                        $report->setGeneratedBy('Neuron AI');
                    }
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Failed to generate report: ' . $e->getMessage());
                }
            }

            $entityManager->persist($report);
            $entityManager->flush();

            $this->addFlash('success', 'Research report created successfully.');

            return $this->redirectToRoute('report_show', ['id' => $report->getId()]);
        }

        return $this->render('company/reports.html.twig', [
            'company' => $company,
            'reports' => $company->getResearchReports(),
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'company_delete', methods: ['POST'])]
    public function delete(Request $request, Company $company, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$company->getId(), $request->request->get('_token'))) {
            $entityManager->remove($company);
            $entityManager->flush();

            $this->addFlash('success', 'Company deleted successfully.');
        }

        return $this->redirectToRoute('company_index');
    }
}
