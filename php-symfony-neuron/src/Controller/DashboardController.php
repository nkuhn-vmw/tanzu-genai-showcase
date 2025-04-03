<?php

namespace App\Controller;

use App\Repository\CompanyRepository;
use App\Repository\ResearchReportRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    #[Route('/', name: 'homepage')]
    public function index(
        CompanyRepository $companyRepository,
        ResearchReportRepository $reportRepository
    ): Response {
        return $this->render('dashboard/index.html.twig', [
            'recentCompanies' => $companyRepository->findRecentlyAdded(5),
            'recentReports' => $reportRepository->findRecentReports(5),
        ]);
    }

    #[Route('/dashboard', name: 'dashboard')]
    public function dashboard(
        CompanyRepository $companyRepository,
        ResearchReportRepository $reportRepository
    ): Response {
        return $this->render('dashboard/dashboard.html.twig', [
            'companiesCount' => count($companyRepository->findAll()),
            'reportsCount' => count($reportRepository->findAll()),
            'recentCompanies' => $companyRepository->findRecentlyAdded(10),
            'recentReports' => $reportRepository->findRecentReports(10),
            'updatedCompanies' => $companyRepository->findRecentlyUpdated(5),
        ]);
    }

    #[Route('/about', name: 'about')]
    public function about(): Response
    {
        return $this->render('dashboard/about.html.twig');
    }
}
