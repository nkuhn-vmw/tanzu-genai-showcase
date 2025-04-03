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

    #[Route('/{id}/leadership', name: 'company_leadership', methods: ['GET'])]
    public function leadership(Company $company): Response
    {
        return $this->render('company/leadership.html.twig', [
            'company' => $company,
            'executives' => $company->getExecutiveProfiles(),
        ]);
    }

    #[Route('/{id}/competitors', name: 'company_competitors', methods: ['GET'])]
    public function competitors(Company $company): Response
    {
        return $this->render('company/competitors.html.twig', [
            'company' => $company,
            'competitorAnalyses' => $company->getCompetitorAnalyses(),
        ]);
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
